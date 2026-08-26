<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return ['name' => $name, 'slug' => fake()->unique()->slug(), 'status' => 'active'];
    }
}
