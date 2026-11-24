<?php

namespace App\Policies;

use App\Models\BookingCall;
use App\Models\User;

// A call belongs to the trip it was made on, so only that trip's driver may board or skip it.
class BookingCallPolicy
{
    public function manage(User $user, BookingCall $call): bool
    {
        return $user->role === 'driver' && $call->trip->driver_id === $user->id;
    }
}
