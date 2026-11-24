<?php

namespace Database\Factories;

use App\Models\Bus;
use App\Models\Route;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TripFactory extends Factory
{
    protected $model = Trip::class;

    public function definition(): array
    {
        return [
            'route_id' => Route::factory(),
            'bus_id' => Bus::factory(),
            'driver_id' => User::factory()->driver(),
            'departs_at' => now()->addHours(fake()->numberBetween(1, 48))->startOfMinute(),
            'status' => 'scheduled',
        ];
    }
}
