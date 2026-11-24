<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PriorityClaim;
use App\Models\Route;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

// Check-in only INSERTS a provisional booking. It never assigns a queue number:
// numbers are given later, in order, by the confirmation command (Stage 5).
class BookingCheckInService
{
    public function __construct(private GeoService $geo)
    {
    }

    /**
     * @return array{0: Booking, 1: bool} the booking, and true if it was newly created
     */
    public function checkIn(User $user, array $data): array
    {
        // 1. The same request_id again (a retry after a network failure) returns the booking we already made.
        $existing = Booking::where('request_id', $data['request_id'])->first();
        if ($existing) {
            return $this->existingFor($user, $existing);
        }

        // 2. The student must have accepted the location notice before the first check-in (FR-05).
        if ($user->consent_given_at === null) {
            abort(403, 'Please accept the location notice before checking in.');
        }

        // 3. One active booking per student. The database enforces this too (checked again below).
        if ($this->hasActiveBooking($user)) {
            abort(409, 'You already have an active booking.');
        }

        // 4. Validate arrival time, GPS accuracy and position against the ORIGIN station of the route.
        $route = Route::with('origin')->findOrFail($data['route_id']);
        $received = now();
        $arrived = Carbon::parse($data['arrived_at'])->setTimezone(config('app.timezone'));
        $this->assertCheckInIsValid($route, $data, $arrived, $received);

        // 5. Insert the provisional booking. user_id always comes from the logged-in user, never from the request.
        $claim = $this->usablePriorityClaim($user);

        $booking = new Booking();
        $booking->forceFill([ // forceFill: these fields are deliberately not mass-assignable
            'user_id' => $user->id,
            'route_id' => $route->id,
            'lane' => $claim ? 'priority' : 'general',
            'priority_claim_id' => $claim?->id,
            'request_id' => $data['request_id'],
            'arrived_at' => $arrived,
            'received_at' => $received,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'accuracy_m' => $data['accuracy_m'],
            'status' => 'provisional',
            'queue_date' => $arrived->toDateString(),
        ]);

        try {
            $booking->save();
        } catch (QueryException $e) {
            return $this->handleDuplicate($e, $user, $data['request_id']);
        }

        return [$booking->refresh(), true];
    }

    private function hasActiveBooking(User $user): bool
    {
        return Booking::where('user_id', $user->id)->whereIn('status', Booking::ACTIVE_STATUSES)->exists();
    }

    // A request_id that belongs to somebody else must never reveal or return their booking.
    private function existingFor(User $user, Booking $existing): array
    {
        if ($existing->user_id !== $user->id) {
            abort(409, 'This request id has already been used.');
        }

        return [$existing, false];
    }

    // Two requests can pass the checks above at the same instant. The database unique indexes are the
    // final guard, so we translate their errors (MySQL error 1062 = duplicate entry) into the right answer.
    private function handleDuplicate(QueryException $e, User $user, string $requestId): array
    {
        if (($e->errorInfo[1] ?? null) !== 1062) {
            throw $e; // not a duplicate: a real error, let Laravel report it
        }

        if (str_contains($e->getMessage(), 'request_id')) {
            return $this->existingFor($user, Booking::where('request_id', $requestId)->firstOrFail());
        }

        abort(409, 'You already have an active booking.');
    }

    private function assertCheckInIsValid(Route $route, array $data, Carbon $arrived, Carbon $received): void
    {
        $errors = [];

        if (! $route->origin->is_active) {
            $errors['route_id'] = ['Check-in is not available at this station right now.'];
        }

        // arrived_at: not in the future, and not older than the grace window.
        $grace = Setting::getInt('grace_window_minutes', 5);
        if ($arrived->gt($received)) {
            $errors['arrived_at'] = ['The arrival time cannot be in the future. Check your phone clock.'];
        } elseif ($arrived->lt($received->copy()->subMinutes($grace))) {
            $errors['arrived_at'] = ["This check-in is older than {$grace} minutes and can no longer be accepted."];
        }

        // GPS accuracy: a larger number is a worse reading.
        $maxAccuracy = Setting::getInt('max_gps_accuracy_m', 50);
        if ($data['accuracy_m'] > $maxAccuracy) {
            $errors['accuracy_m'] = ["Your GPS signal is too weak (accuracy {$maxAccuracy} m or better is needed). Move to an open area and try again."];
        }

        // Geofence: distance from the phone to the route's origin station against that station's radius.
        $distance = $this->geo->distanceMeters(
            (float) $data['latitude'], (float) $data['longitude'],
            (float) $route->origin->latitude, (float) $route->origin->longitude,
        );
        if ($distance > $route->origin->radius_m) {
            $errors['location'] = ['You are outside the station area. Go to ' . $route->origin->name . ' to check in.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors); // becomes a 422 response
        }
    }

    // Priority lane only with an approved claim that has not expired.
    private function usablePriorityClaim(User $user): ?PriorityClaim
    {
        return PriorityClaim::usable()->where('user_id', $user->id)->latest('id')->first();
    }
}
