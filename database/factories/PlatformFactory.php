<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'slug' => fake()->unique()->slug(),
            'manufacturer' => fake()->company(),
            'status' => 'active',
        ];
    }
}
