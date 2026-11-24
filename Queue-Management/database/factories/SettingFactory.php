<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(2),
            'value' => (string) fake()->numberBetween(1, 10),
            'description' => fake()->sentence(),
        ];
    }
}
