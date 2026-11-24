<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'student_id' => (string) fake()->unique()->numerify('20#####'),
            'password' => 'Password123!', // the model's 'hashed' cast hashes it
            'is_active' => true,
        ];
    }

    // role is not fillable, so factories set it through these states instead.
    public function driver(): static
    {
        return $this->state(['role' => 'driver', 'student_id' => null]);
    }

    public function operator(): static
    {
        return $this->state(['role' => 'operator', 'student_id' => null]);
    }

    public function admin(): static
    {
        return $this->state(['role' => 'admin', 'student_id' => null]);
    }
}
