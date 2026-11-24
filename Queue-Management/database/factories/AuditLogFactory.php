<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->operator(),
            'action' => 'trip.updated',
            'reason' => fake()->sentence(),
            'ip_address' => fake()->ipv4(),
        ];
    }
}
