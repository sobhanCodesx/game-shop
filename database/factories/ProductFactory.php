<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->words(4, true),
            'slug' => fake()->unique()->slug(),
            'sku' => fake()->unique()->bothify('GAME-####-??'),
            'product_type' => 'physical_game',
            'price' => fake()->numberBetween(100_000, 10_000_000),
            'stock' => fake()->numberBetween(0, 100),
            'low_stock_threshold' => 5,
            'availability' => 'in_stock',
            'minimum_quantity' => 1,
            'status' => 'published',
            'visibility' => 'public',
        ];
    }
}
