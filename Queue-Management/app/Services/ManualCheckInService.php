<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PriorityClaim;
use App\Models\Route;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// An operator checks a student in by hand (no smartphone, or the phone failed).
// Same queue rules as a phone check-in, but: no GPS reading, the arrival time is the moment the operator submits,
// the operator is recorded in checked_in_by, and the action is written to the audit log with the reason.
class ManualCheckInService
{
    public function __construct(private AuditLogger $audit)
    {
    }

    /**
     * @return array{0: Booking, 1: bool} the booking, and true if it was newly created
     */
    public function checkIn(User $operator, array $data, ?string $ip): array
    {
        $student = User::where('student_id', $data['student_id'])->where('role', 'student')->first();
        if (! $student || ! $student->is_active) {
            throw ValidationException::withMessages(['student_id' => ['No active student was found with this student ID.']]);
        }

        // The operator's screen may send a request_id so a double click is counted once. Otherwise we make one.
        $requestId = $data['request_id'] ?? (string) Str::uuid();

        $existing = Booking::where('request_id', $requestId)->first();
        if ($existing) {
            return [$this->sameStudentOrFail($existing, $student), false];
        }

        if (Booking::where('user_id', $student->id)->whereIn('status', Booking::ACTIVE_STATUSES)->exists()) {
            abort(409, 'This student already has an active booking.');
        }

        $route = Route::with('origin')->findOrFail($data['route_id']);
        if (! $route->origin->is_active) {
            throw ValidationException::withMessages(['route_id' => ['This station is not active.']]);
        }

        $claim = PriorityClaim::usable()->where('user_id', $student->id)->latest('id')->first();
        $now = now(); // no phone clock here: the server time is the arrival time

        try {
            $booking = DB::transaction(function () use ($operator, $student, $route, $claim, $requestId, $now, $data, $ip) {
                $booking = new Booking();
                $booking->forceFill([
                    'user_id' => $student->id, // the student being served, never the operator
                    'route_id' => $route->id,
                    'lane' => $claim ? 'priority' : 'general',
                    'priority_claim_id' => $claim?->id,
                    'request_id' => $requestId,
                    'arrived_at' => $now,
                    'received_at' => $now,
                    'latitude' => null, // no GPS reading for a manual check-in
                    'longitude' => null,
                    'accuracy_m' => null,
                    'status' => 'provisional',
                    'queue_date' => $now->toDateString(),
                    'checked_in_by' => $operator->id,
                ])->save();

                // The booking and its audit entry are saved together or not at all.
                $this->audit->log($operator, 'booking.manual_checkin', $booking, $data['reason'], [
                    'student_id' => $student->student_id,
                    'route_id' => $route->id,
                ], $ip);

                return $booking;
            });
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) !== 1062) {
                throw $e;
            }

            // Two requests at the same instant: the database unique indexes decided.
            if (str_contains($e->getMessage(), 'request_id')) {
                return [$this->sameStudentOrFail(Booking::where('request_id', $requestId)->firstOrFail(), $student), false];
            }

            abort(409, 'This student already has an active booking.');
        }

        return [$booking->refresh(), true];
    }

    private function sameStudentOrFail(Booking $existing, User $student): Booking
    {
        if ($existing->user_id !== $student->id) {
            abort(409, 'This request id has already been used.');
        }

        return $existing;
    }
}
