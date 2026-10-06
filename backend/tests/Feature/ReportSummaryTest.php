<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_calculates_income_expense_and_net_cashflow(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $incomeCategory = Category::factory()->for($user)->income()->create(['name' => 'Gaji']);
        $expenseCategory = Category::factory()->for($user)->expense()->create(['name' => 'Operasional']);

        Transaction::factory()->for($user)->for($wallet, 'wallet')->create([
            'type' => 'income',
            'amount' => 100000,
            'transaction_date' => '2026-10-01',
            'category_id' => $incomeCategory->id,
        ]);
        Transaction::factory()->for($user)->for($wallet, 'wallet')->create([
            'type' => 'expense',
            'amount' => 40000,
            'transaction_date' => '2026-10-05',
            'category_id' => $expenseCategory->id,
        ]);
        Transaction::factory()->for($user)->for($wallet, 'wallet')->create([
            'type' => 'transfer',
            'amount' => 10000,
            'transaction_date' => '2026-10-03',
            'category_id' => $expenseCategory->id,
        ]);

        $response = $this->authenticatedAs($user)->getJson(
            '/api/reports/summary?start_date=2026-10-01&end_date=2026-10-31',
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.period.start_date', '2026-10-01')
            ->assertJsonPath('data.period.end_date', '2026-10-31')
            ->assertJsonPath('data.total_income', '100000.00')
            ->assertJsonPath('data.total_expense', '40000.00')
            ->assertJsonPath('data.net_cashflow', '60000.00')
            ->assertJsonPath('data.transaction_count', 2);

        $this->assertCount(2, $response->json('data.by_category'));
        $this->assertSame('Gaji', $response->json('data.by_category.0.name'));
        $this->assertSame('100000.00', $response->json('data.by_category.0.total'));
    }

    public function test_summary_excludes_transactions_outside_period(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $category = Category::factory()->for($user)->income()->create();

        Transaction::factory()->for($user)->for($wallet, 'wallet')->create([
            'type' => 'income',
            'amount' => 50000,
            'transaction_date' => '2026-09-15',
            'category_id' => $category->id,
        ]);
        Transaction::factory()->for($user)->for($wallet, 'wallet')->create([
            'type' => 'income',
            'amount' => 30000,
            'transaction_date' => '2026-10-10',
            'category_id' => $category->id,
        ]);

        $response = $this->authenticatedAs($user)->getJson(
            '/api/reports/summary?start_date=2026-10-01&end_date=2026-10-31',
        );

        $response->assertOk()
            ->assertJsonPath('data.total_income', '30000.00')
            ->assertJsonPath('data.transaction_count', 1);
    }

    public function test_summary_can_filter_by_wallet(): void
    {
        $user = User::factory()->create();
        $walletA = Wallet::factory()->for($user)->create();
        $walletB = Wallet::factory()->for($user)->create();
        $category = Category::factory()->for($user)->income()->create();

        Transaction::factory()->for($user)->for($walletA, 'wallet')->create([
            'type' => 'income',
            'amount' => 25000,
            'transaction_date' => '2026-10-02',
            'category_id' => $category->id,
        ]);
        Transaction::factory()->for($user)->for($walletB, 'wallet')->create([
            'type' => 'income',
            'amount' => 75000,
            'transaction_date' => '2026-10-02',
            'category_id' => $category->id,
        ]);

        $response = $this->authenticatedAs($user)->getJson(
            "/api/reports/summary?start_date=2026-10-01&end_date=2026-10-31&wallet_id={$walletA->id}",
        );

        $response->assertOk()
            ->assertJsonPath('data.total_income', '25000.00')
            ->assertJsonPath('data.transaction_count', 1);
    }

    public function test_summary_defaults_to_current_month(): void
    {
        $user = User::factory()->create();

        $response = $this->authenticatedAs($user)->getJson('/api/reports/summary');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.period.start_date', now()->startOfMonth()->format('Y-m-d'))
            ->assertJsonPath('data.period.end_date', now()->format('Y-m-d'))
            ->assertJsonPath('data.total_income', '0.00')
            ->assertJsonPath('data.total_expense', '0.00')
            ->assertJsonPath('data.net_cashflow', '0.00');
    }

    public function test_summary_does_not_leak_other_users_data(): void
    {
        $user = User::factory()->create();
        Transaction::factory()->create([
            'type' => 'income',
            'amount' => 999999,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $response = $this->authenticatedAs($user)->getJson('/api/reports/summary');

        $response->assertOk()->assertJsonPath('data.total_income', '0.00');
    }

    public function test_summary_requires_authentication(): void
    {
        $this->getJson('/api/reports/summary')->assertUnauthorized();
    }

    private function authenticatedAs(User $user): static
    {
        return $this->withToken($user->createToken('api')->plainTextToken);
    }
}
