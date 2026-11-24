<?php

namespace Database\Factories;

use App\Models\PriorityClaim;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PriorityClaimFactory extends Factory
{
    protected $model = PriorityClaim::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category' => fake()->randomElement(['pregnant', 'with_child', 'disability', 'sick']),
            'status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'reviewed_by' => User::factory()->operator(),
            'reviewed_at' => now(),
            'valid_until' => now()->addMonths(3)->toDateString(),
        ]);
    }
}
