<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BrandFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->company(), 'slug' => fake()->unique()->slug(), 'status' => 'active'];
    }
}
