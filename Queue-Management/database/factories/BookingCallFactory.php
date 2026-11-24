<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingCall;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingCallFactory extends Factory
{
    protected $model = BookingCall::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory()->confirmed(),
            'trip_id' => Trip::factory(),
            'called_by' => User::factory()->driver(),
            'seat_type' => 'general',
            'called_at' => now(),
            'deadline_at' => now()->addMinutes(5),
            'outcome' => 'pending',
        ];
    }
}
