<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Route;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $arrived = now()->subMinutes(fake()->numberBetween(0, 4));

        return [
            'user_id' => User::factory(),
            'route_id' => Route::factory(),
            'lane' => 'general',
            'request_id' => (string) Str::uuid(),
            'arrived_at' => $arrived,
            'received_at' => $arrived->copy()->addSeconds(1),
            'latitude' => 13.4549,
            'longitude' => -16.5790,
            'accuracy_m' => 12,
            'status' => 'provisional',
            'queue_date' => now()->toDateString(),
        ];
    }

    public function confirmed(int $number = 1): static
    {
        return $this->state(['status' => 'confirmed', 'queue_number' => $number, 'confirmed_at' => now()]);
    }
}
