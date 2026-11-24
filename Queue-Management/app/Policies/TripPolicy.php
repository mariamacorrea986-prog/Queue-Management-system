<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;

// "A driver sees only their own trips": anything else answers 403.
class TripPolicy
{
    public function manage(User $user, Trip $trip): bool
    {
        return $user->role === 'driver' && $trip->driver_id === $user->id;
    }
}
