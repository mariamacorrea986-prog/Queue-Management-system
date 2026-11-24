<?php

namespace Database\Factories;

use App\Models\Station;
use Illuminate\Database\Eloquent\Factories\Factory;

class StationFactory extends Factory
{
    protected $model = Station::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->streetName() . ' Station',
            'latitude' => fake()->latitude(13.2, 13.5),
            'longitude' => fake()->longitude(-16.8, -16.5),
            'radius_m' => 100,
            'is_active' => true,
        ];
    }
}
