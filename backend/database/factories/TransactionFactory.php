<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['income', 'expense']),
            'user_id' => User::factory(),
            'wallet_id' => fn (array $attributes): int => Wallet::factory()
                ->create(['user_id' => $attributes['user_id']])
                ->getKey(),
            'category_id' => fn (array $attributes): int => Category::factory()
                ->create([
                    'user_id' => $attributes['user_id'],
                    'type' => $attributes['type'],
                ])
                ->getKey(),
            'amount' => fake()->randomFloat(2, 10000, 500000),
            'transaction_date' => fake()->dateTimeBetween('-30 days')->format('Y-m-d'),
            'description' => fake()->optional()->sentence(),
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
}
