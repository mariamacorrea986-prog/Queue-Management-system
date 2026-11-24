<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;

// Read-side helpers for what a student sees: their active booking and their provisional position.
class BookingQueueService
{
    public function currentFor(User $user): ?Booking
    {
        return Booking::where('user_id', $user->id)
            ->whereIn('status', Booking::ACTIVE_STATUSES)
            ->with(['route.origin', 'route.destination', 'calls.trip'])
            ->latest('id')
            ->first();
    }

    // "You are number N in the line right now": 1 + the people ahead of this booking in the same
    // route, day and lane who are still waiting. It is provisional because an earlier arrival can still
    // reach the server within the grace window. Order is arrived_at, then received_at, then id.
    public function positionOf(Booking $booking): int
    {
        // Dates are passed as text with milliseconds. If a Carbon object were passed, Laravel would
        // format it as 'Y-m-d H:i:s' and silently drop the milliseconds, giving wrong comparisons.
        $arrived = $booking->arrived_at->format('Y-m-d H:i:s.v');
        $received = $booking->received_at->format('Y-m-d H:i:s.v');

        $ahead = Booking::where('route_id', $booking->route_id)
            ->where('queue_date', $booking->queue_date)
            ->where('lane', $booking->lane)
            ->whereIn('status', ['provisional', 'confirmed'])
            ->where(function ($q) use ($arrived, $received, $booking) {
                $q->where('arrived_at', '<', $arrived)
                    ->orWhere(function ($q) use ($arrived, $received, $booking) {
                        $q->where('arrived_at', $arrived)
                            ->where(function ($q) use ($received, $booking) {
                                $q->where('received_at', '<', $received)
                                    ->orWhere(function ($q) use ($received, $booking) {
                                        $q->where('received_at', $received)->where('id', '<', $booking->id);
                                    });
                            });
                    });
            })
            ->count();

        return $ahead + 1;
    }
}
