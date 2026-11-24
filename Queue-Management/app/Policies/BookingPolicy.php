<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

// "A student cannot view or change another student's booking": anything else answers 403.
class BookingPolicy
{
    public function cancel(User $user, Booking $booking): bool
    {
        return $user->role === 'student' && $booking->user_id === $user->id;
    }
}
