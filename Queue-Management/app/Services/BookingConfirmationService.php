<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\QueueCounter;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// The ONLY place that assigns queue numbers.
//
// Why this is fair: the grace window and the confirmation window are both 5 minutes. A check-in can reach
// the server at most 5 minutes after the student tapped. So when a booking is 5 minutes old, every student
// who arrived before it has already been received, and nobody can still jump ahead of it.
class BookingConfirmationService
{
    /** Confirms every due booking and returns how many were confirmed. */
    public function confirmDue(): int
    {
        $cutoff = now()->subMinutes(Setting::getInt('confirmation_window_minutes', 5));
        $cutoffText = $this->dbTime($cutoff);

        // Numbers are separate per route, day and lane, so find which groups have bookings that are due.
        $groups = Booking::where('status', 'provisional')
            ->where('arrived_at', '<=', $cutoffText)
            ->select('route_id', 'queue_date', 'lane')
            ->distinct()
            ->orderBy('route_id')->orderBy('queue_date')->orderBy('lane')
            ->get();

        $confirmed = 0;
        foreach ($groups as $group) {
            $confirmed += $this->confirmGroup($group->route_id, $group->queue_date, $group->lane, $cutoffText);
        }

        return $confirmed;
    }

    private function confirmGroup(int $routeId, string $queueDate, string $lane, string $cutoffText): int
    {
        $this->ensureCounterExists($routeId, $queueDate, $lane);

        // Everything below is one transaction. The second argument (3) means: if MySQL reports a deadlock,
        // Laravel rolls back and tries the whole block again, up to 3 times.
        return DB::transaction(function () use ($routeId, $queueDate, $lane, $cutoffText) {
            // lockForUpdate: any other process that wants this counter must wait until we commit,
            // so two processes can never hand out the same number.
            $counter = QueueCounter::where('route_id', $routeId)
                ->where('queue_date', $queueDate)
                ->where('lane', $lane)
                ->lockForUpdate()
                ->firstOrFail();

            // Read the due bookings AFTER taking the lock, so one cancelled a moment ago is not confirmed.
            // Order: arrived_at, then received_at, then id (the project's ordering rule).
            $due = Booking::where('route_id', $routeId)
                ->where('queue_date', $queueDate)
                ->where('lane', $lane)
                ->where('status', 'provisional')
                ->where('arrived_at', '<=', $cutoffText)
                ->orderBy('arrived_at')->orderBy('received_at')->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($due as $booking) {
                $counter->last_number++;
                $booking->forceFill([ // forceFill: only this service may set these fields
                    'status' => 'confirmed',
                    'queue_number' => $counter->last_number,
                    'confirmed_at' => now(),
                ])->save();
            }

            if ($due->isNotEmpty()) {
                $counter->save();
            }

            return $due->count();
        }, 3);
    }

    // The counter row for a route, day and lane is created the first time it is needed.
    // Two processes may try at the same instant: the unique index lets only one win, and the loser
    // simply carries on, because the row now exists.
    private function ensureCounterExists(int $routeId, string $queueDate, string $lane): void
    {
        try {
            QueueCounter::firstOrCreate(
                ['route_id' => $routeId, 'queue_date' => $queueDate, 'lane' => $lane],
                ['last_number' => 0],
            );
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) !== 1062) { // 1062 = MySQL "duplicate entry"
                throw $e;
            }
        }
    }

    // Dates are compared as text with milliseconds, because Laravel would otherwise drop the milliseconds.
    private function dbTime(Carbon $time): string
    {
        return $time->format('Y-m-d H:i:s.v');
    }
}
