<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Trip;

// Builds what the driver sees. Privacy: name, student ID, queue number and a "Priority" flag only.
// The priority CATEGORY (pregnant, sick...) is never loaded or returned here.
class ManifestService
{
    public function for(Trip $trip): array
    {
        $trip->load(['route.origin', 'route.destination', 'bus']);

        // Everyone already called on this trip (waiting for the student, boarded, or skipped).
        $calls = $trip->bookingCalls()
            ->with('booking.user:id,name,student_id')
            ->orderBy('called_at')->orderBy('id')
            ->get();

        $taken = $calls->whereIn('outcome', ['pending', 'boarded'])->count();

        // Confirmed students still waiting for this route and day: priority lane first, each lane in queue order.
        $waiting = Booking::where('route_id', $trip->route_id)
            ->where('queue_date', $trip->departs_at->toDateString())
            ->where('status', 'confirmed')
            ->whereDoesntHave('calls', fn ($q) => $q->where('trip_id', $trip->id))
            ->with(['user:id,name,student_id', 'calls'])
            ->get()
            ->sortBy(fn ($booking) => [$booking->lane === 'priority' ? 0 : 1, $booking->queue_number])
            ->values();

        return [
            'seats' => [
                'capacity' => $trip->bus->capacity,
                'priority_seats' => $trip->bus->priority_seats,
                'taken' => $taken,
                'available' => max(0, $trip->bus->capacity - $taken),
            ],
            'calls' => $calls,
            'waiting' => $waiting,
        ];
    }
}
