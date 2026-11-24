<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingCall;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// "Call next": the driver calls the next students for a trip.
//
// Seat rule (section 6.6 of the overview):
//   1. At most bus.priority_seats of the seats go to the priority lane, taken in queue order.
//   2. All remaining seats go to the general lane. Priority seats nobody needs are released to the general lane.
//   3. Priority students beyond the priority seats are NOT moved into general seats; they keep their priority
//      numbers and take priority seats on the next bus.
class CallBatchService
{
    /**
     * @return Collection<int, BookingCall> the calls made now (empty when nobody is waiting)
     */
    public function callNext(Trip $trip, User $driver, ?int $count = null): Collection
    {
        try {
            // Up to 3 attempts if MySQL reports a deadlock.
            return DB::transaction(fn () => $this->callInsideTransaction($trip, $driver, $count), 3);
        } catch (QueryException $e) {
            // Last line of defence: the unique index on booking_calls.pending_booking_id refused a student
            // who was called by another driver in the same instant. Nobody is called twice.
            if (($e->errorInfo[1] ?? null) === 1062) {
                abort(409, 'Some students were just called by another driver. Please try again.');
            }

            throw $e;
        }
    }

    private function callInsideTransaction(Trip $trip, User $driver, ?int $count): Collection
    {
        // Lock the trip: two "call next" taps on the same trip run one after the other, never together,
        // so the seat count below cannot be read by both at the same time.
        $trip = Trip::lockForUpdate()->findOrFail($trip->id);
        $trip->load('bus');

        if (! in_array($trip->status, ['scheduled', 'boarding'], true)) {
            abort(409, "Students cannot be called for this trip because it is {$trip->status}.");
        }
        if ($trip->bus->status !== 'active') {
            abort(409, 'This bus is not active.');
        }

        // A seat is taken by a pending or boarded call. A skipped call gives its seat back.
        $seatCalls = BookingCall::where('trip_id', $trip->id)->whereIn('outcome', ['pending', 'boarded']);
        $seatsTaken = (clone $seatCalls)->count();
        $prioritySeatsTaken = (clone $seatCalls)->where('seat_type', 'priority')->count();

        $seatsFree = $trip->bus->capacity - $seatsTaken;
        if ($seatsFree <= 0) {
            abort(409, 'The bus is full.');
        }

        $batchSize = $count ? min($count, $seatsFree) : $seatsFree; // the driver may ask for fewer
        $prioritySeatsFree = max(0, $trip->bus->priority_seats - $prioritySeatsTaken);

        // Priority lane first, limited to the priority seats still free.
        $priority = $this->waiting($trip, 'priority', min($batchSize, $prioritySeatsFree));
        // Every seat not given to the priority lane goes to the general lane.
        $general = $this->waiting($trip, 'general', $batchSize - $priority->count());

        $students = $priority->concat($general);
        if ($students->isEmpty()) {
            return collect();
        }

        $students->load('user:id,name,student_id');
        $calledAt = now();
        $deadline = $calledAt->copy()->addMinutes(Setting::getInt('call_deadline_minutes', 5));

        $calls = collect();
        foreach ($students as $booking) {
            $call = BookingCall::create([
                'booking_id' => $booking->id,
                'trip_id' => $trip->id,
                'called_by' => $driver->id,
                'seat_type' => $booking->lane, // a priority-lane student sits in a priority seat
                'called_at' => $calledAt,
                'deadline_at' => $deadline,
                'outcome' => 'pending',
            ]);

            $booking->forceFill(['status' => 'called'])->save();
            $calls->push($call->setRelation('booking', $booking));
        }

        if ($trip->status === 'scheduled') {
            $trip->update(['status' => 'boarding']);
        }

        return $calls;
    }

    // Confirmed students of this trip's route and day, in queue order, who have not already had a call on THIS trip
    // (a student skipped on this trip cannot be called on it again; they are included in the next bus's call).
    // lockForUpdate: these rows stay locked until the transaction ends, so a second driver waits and then
    // no longer finds them as "confirmed".
    // Always returns an Eloquent collection, even when empty, because the two lanes are joined and then
    // load('user') is called on the result, which only an Eloquent collection can do.
    private function waiting(Trip $trip, string $lane, int $limit): EloquentCollection
    {
        if ($limit <= 0) {
            return new EloquentCollection();
        }

        return Booking::where('route_id', $trip->route_id)
            ->where('queue_date', $trip->departs_at->toDateString())
            ->where('lane', $lane)
            ->where('status', 'confirmed')
            ->whereDoesntHave('calls', fn ($q) => $q->where('trip_id', $trip->id))
            ->orderBy('queue_number')
            ->limit($limit)
            ->lockForUpdate()
            ->get();
    }
}
