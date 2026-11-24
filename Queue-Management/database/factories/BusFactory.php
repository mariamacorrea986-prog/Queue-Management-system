<?php

namespace Database\Factories;

use App\Models\Bus;
use Illuminate\Database\Eloquent\Factories\Factory;

class BusFactory extends Factory
{
    protected $model = Bus::class;

    public function definition(): array
    {
        return [
            'plate_number' => strtoupper(fake()->unique()->bothify('B ####??')),
            'capacity' => 50,
            'priority_seats' => 5,
            'status' => 'active',
        ];
    }
}
