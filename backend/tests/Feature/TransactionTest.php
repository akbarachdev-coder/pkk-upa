<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_income_increases_wallet_balance(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create(['balance' => 100000]);
        $category = Category::factory()->for($user)->income()->create();

        $response = $this->authenticatedAs($user)->postJson('/api/transactions', $this->payload([
            'type' => 'income',
            'amount' => 50000,
            'wallet_id' => $wallet->id,
            'category_id' => $category->id,
        ]));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'income')
            ->assertJsonPath('data.amount', '50000.00')
            ->assertJsonPath('data.transaction_date', '2026-10-01')
            ->assertJsonPath('data.wallet.name', $wallet->name);

        $this->assertSame('150000.00', $wallet->refresh()->balance);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_store_expense_decreases_wallet_balance(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create(['balance' => 100000]);
        $category = Category::factory()->for($user)->expense()->create();

        $this->authenticatedAs($user)->postJson('/api/transactions', $this->payload([
            'type' => 'expense',
            'amount' => 30000,
            'wallet_id' => $wallet->id,
            'category_id' => $category->id,
        ]))->assertCreated();

        $this->assertSame('70000.00', $wallet->refresh()->balance);
    }

    public function test_store_rejects_expense_exceeding_wallet_balance(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create(['balance' => 10000]);
        $category = Category::factory()->for($user)->expense()->create();

        $response = $this->authenticatedAs($user)->postJson('/api/transactions', $this->payload([
            'type' => 'expense',
            'amount' => 50000,
            'wallet_id' => $wallet->id,
            'category_id' => $category->id,
        ]));

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['amount']]);

        $this->assertStringContainsString(
            'tidak mencukupi',
            $response->json('errors.amount.0'),
        );

        $this->assertSame('10000.00', $wallet->refresh()->balance);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_store_rejects_wallet_owned_by_another_user(): void
    {
        $user = User::factory()->create();
        $foreignWallet = Wallet::factory()->create(['balance' => 1000000]);
        $category = Category::factory()->for($user)->expense()->create();

        $this->authenticatedAs($user)->postJson('/api/transactions', $this->payload([
            'type' => 'expense',
            'wallet_id' => $foreignWallet->id,
            'category_id' => $category->id,
        ]))->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['wallet_id']]);

        $this->assertSame('1000000.00', $foreignWallet->refresh()->balance);
    }

    public function test_store_rejects_category_with_mismatched_type(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create(['balance' => 100000]);
        $expenseCategory = Category::factory()->for($user)->expense()->create();

        $this->authenticatedAs($user)->postJson('/api/transactions', $this->payload([
            'type' => 'income',
            'wallet_id' => $wallet->id,
            'category_id' => $expenseCategory->id,
        ]))->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['category_id']]);
    }

    public function test_store_allows_global_category(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create(['balance' => 10000]);
        $globalCategory = Category::factory()->global()->expense()->create();

        $this->authenticatedAs($user)->postJson('/api/transactions', $this->payload([
            'type' => 'expense',
            'wallet_id' => $wallet->id,
            'category_id' => $globalCategory->id,
        ]))->assertCreated();
    }

    public function test_store_transfer_moves_funds_between_wallets(): void
    {
        $user = User::factory()->create();
        $source = Wallet::factory()->for($user)->create(['name' => 'Kas Utama', 'balance' => 100000]);
        $destination = Wallet::factory()->for($user)->create(['name' => 'Bank BCA', 'balance' => 0]);
        $category = Category::factory()->for($user)->expense()->create();

        $response = $this->authenticatedAs($user)->postJson('/api/transactions', $this->payload([
            'type' => 'transfer',
            'amount' => 25000,
            'wallet_id' => $source->id,
            'destination_wallet_id' => $destination->id,
            'category_id' => $category->id,
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.type', 'transfer')
            ->assertJsonPath('data.destination_wallet_id', $destination->id);

        $this->assertSame('75000.00', $source->refresh()->balance);
        $this->assertSame('25000.00', $destination->refresh()->balance);
    }

    public function test_store_transfer_requires_destination_wallet(): void
    {
        $user = User::factory()->create();
        $source = Wallet::factory()->for($user)->create(['balance' => 100000]);
        $category = Category::factory()->for($user)->expense()->create();

        $this->authenticatedAs($user)->postJson('/api/transactions', $this->payload([
            'type' => 'transfer',
            'wallet_id' => $source->id,
            'category_id' => $category->id,
        ]))->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['destination_wallet_id']]);
    }

    public function test_store_validates_transaction_date_format(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $category = Category::factory()->for($user)->expense()->create();

        $this->authenticatedAs($user)->postJson('/api/transactions', $this->payload([
            'wallet_id' => $wallet->id,
            'category_id' => $category->id,
            'transaction_date' => '31/12/2026',
        ]))->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['transaction_date']]);
    }

    public function test_store_accepts_attachment_upload(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create(['balance' => 100000]);
        $category = Category::factory()->for($user)->expense()->create();

        $response = $this->authenticatedAs($user)->postJson('/api/transactions', $this->payload([
            'wallet_id' => $wallet->id,
            'category_id' => $category->id,
            'attachment' => $this->pdfFile(),
        ]));

        $response->assertCreated();

        $path = $response->json('data.attachment_path');
        $this->assertNotNull($path);
        $this->assertStringStartsWith('attachments/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_index_filters_by_period_type_and_wallet(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $emptyWallet = Wallet::factory()->for($user)->create();
        $incomeCategory = Category::factory()->for($user)->income()->create();
        $expenseCategory = Category::factory()->for($user)->expense()->create();

        Transaction::factory()->for($user)->for($wallet, 'wallet')->create([
            'type' => 'income',
            'amount' => 50000,
            'transaction_date' => '2026-10-01',
            'category_id' => $incomeCategory->id,
        ]);
        Transaction::factory()->for($user)->for($wallet, 'wallet')->create([
            'type' => 'expense',
            'amount' => 20000,
            'transaction_date' => '2026-10-15',
            'category_id' => $expenseCategory->id,
        ]);
        Transaction::factory()->for($user)->for($wallet, 'wallet')->create([
            'type' => 'expense',
            'amount' => 30000,
            'transaction_date' => '2026-09-20',
            'category_id' => $expenseCategory->id,
        ]);

        $byPeriod = $this->authenticatedAs($user)->getJson(
            '/api/transactions?start_date=2026-10-01&end_date=2026-10-31',
        );

        $byPeriod->assertOk()->assertJsonCount(2, 'data.items');

        $byType = $this->authenticatedAs($user)->getJson('/api/transactions?type=income');
        $byType->assertOk()->assertJsonCount(1, 'data.items');
        $this->assertSame('income', $byType->json('data.items.0.type'));

        $byWallet = $this->authenticatedAs($user)->getJson("/api/transactions?wallet_id={$emptyWallet->id}");
        $byWallet->assertOk()->assertJsonCount(0, 'data.items');

        $this->authenticatedAs($user)
            ->getJson('/api/transactions?start_date=2026-10-31&end_date=2026-10-01')
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['end_date']]);
    }

    public function test_index_paginates_results(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $category = Category::factory()->for($user)->expense()->create();

        Transaction::factory()->for($user)->for($wallet, 'wallet')->count(10)->create([
            'transaction_date' => '2026-10-05',
            'category_id' => $category->id,
        ]);

        $response = $this->authenticatedAs($user)->getJson('/api/transactions?per_page=5');

        $response->assertOk()
            ->assertJsonCount(5, 'data.items')
            ->assertJsonPath('data.pagination.total', 10)
            ->assertJsonPath('data.pagination.per_page', 5)
            ->assertJsonPath('data.pagination.last_page', 2);
    }

    public function test_index_returns_only_own_transactions(): void
    {
        $user = User::factory()->create();
        Transaction::factory()->count(2)->create();
        Transaction::factory()->for($user)->create();

        $response = $this->authenticatedAs($user)->getJson('/api/transactions');

        $response->assertOk()->assertJsonCount(1, 'data.items');
        $this->assertSame($user->id, $response->json('data.items.0.user_id'));
    }

    public function test_delete_restores_wallet_balance(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create(['balance' => 100000]);
        $incomeCategory = Category::factory()->for($user)->income()->create();

        $created = $this->authenticatedAs($user)->postJson('/api/transactions', $this->payload([
            'type' => 'income',
            'amount' => 50000,
            'wallet_id' => $wallet->id,
            'category_id' => $incomeCategory->id,
        ]));

        $this->assertSame('150000.00', $wallet->refresh()->balance);

        $this->authenticatedAs($user)
            ->deleteJson("/api/transactions/{$created->json('data.id')}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('100000.00', $wallet->refresh()->balance);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_delete_returns_404_for_other_users_transaction(): void
    {
        $user = User::factory()->create();
        $foreignTransaction = Transaction::factory()->create();

        $this->authenticatedAs($user)
            ->deleteJson("/api/transactions/{$foreignTransaction->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Data tidak ditemukan.');

        $this->assertDatabaseHas('transactions', ['id' => $foreignTransaction->id]);
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/transactions')->assertUnauthorized();
        $this->postJson('/api/transactions', [])->assertUnauthorized();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'expense',
            'amount' => 1000,
            'transaction_date' => '2026-10-01',
            'wallet_id' => 0,
            'category_id' => 0,
        ], $overrides);
    }

    private function authenticatedAs(User $user): static
    {
        return $this->withToken($user->createToken('api')->plainTextToken);
    }

    private function pdfFile(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'nota');
        file_put_contents($path, "%PDF-1.4\n1 0 obj\n<< >>\nendobj\ntrailer\n<< >>\n%%EOF\n");

        return new UploadedFile($path, 'nota.pdf', 'application/pdf', null, true);
    }
}
