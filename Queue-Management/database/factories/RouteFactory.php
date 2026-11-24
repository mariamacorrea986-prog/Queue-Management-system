<?php

namespace Database\Factories;

use App\Models\Route;
use App\Models\Station;
use Illuminate\Database\Eloquent\Factories\Factory;

class RouteFactory extends Factory
{
    protected $model = Route::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'origin_station_id' => Station::factory(),
            'destination_station_id' => Station::factory(),
            'is_active' => true,
        ];
    }
}
