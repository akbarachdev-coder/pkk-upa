<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
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
            'name' => fake()->randomElement(['Kas Utama', 'Bank BCA', 'Bank Mandiri', 'Petty Cash']),
            'balance' => 0,
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function withBalance(int|float $balance): static
    {
        return $this->state(fn (array $attributes): array => ['balance' => $balance]);
    }
}
