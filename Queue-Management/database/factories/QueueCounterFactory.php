<?php

namespace Database\Factories;

use App\Models\QueueCounter;
use App\Models\Route;
use Illuminate\Database\Eloquent\Factories\Factory;

class QueueCounterFactory extends Factory
{
    protected $model = QueueCounter::class;

    public function definition(): array
    {
        return [
            'route_id' => Route::factory(),
            'queue_date' => now()->toDateString(),
            'lane' => 'general',
            'last_number' => 0,
        ];
    }
}
