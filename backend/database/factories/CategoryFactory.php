<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Gaji', 'Operasional', 'Transportasi', 'Makan & Minum']),
            'type' => fake()->randomElement(['income', 'expense']),
        ];
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => 'income']);
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => 'expense']);
    }

    public function global(): static
    {
        return $this->state(fn (array $attributes): array => ['user_id' => null]);
    }
}
