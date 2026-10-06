<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_own_wallets(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create(['name' => 'Kas Utama']);
        Wallet::factory()->create(['name' => 'Dompet Orang Lain']);

        $response = $this->authenticatedAs($user)->getJson('/api/wallets');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Kas Utama')
            ->assertJsonPath('data.0.balance', '0.00');
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/wallets')->assertUnauthorized();
    }

    public function test_store_creates_wallet_with_zero_balance(): void
    {
        $user = User::factory()->create();

        $response = $this->authenticatedAs($user)->postJson('/api/wallets', [
            'name' => 'Bank BCA',
            'description' => 'Rekening pribadi',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Bank BCA')
            ->assertJsonPath('data.balance', '0.00');

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'name' => 'Bank BCA',
            'description' => 'Rekening pribadi',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $this->authenticatedAs($user)
            ->postJson('/api/wallets', [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['name']]);
    }

    public function test_show_returns_wallet_with_transaction_history(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create(['name' => 'Petty Cash']);
        Transaction::factory()->for($user)->for($wallet, 'wallet')->count(2)->create();

        $response = $this->authenticatedAs($user)->getJson("/api/wallets/{$wallet->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.wallet.name', 'Petty Cash')
            ->assertJsonCount(2, 'data.transactions.items')
            ->assertJsonPath('data.transactions.pagination.total', 2)
            ->assertJsonPath('data.transactions.pagination.per_page', 15);
    }

    public function test_show_returns_404_for_other_users_wallet(): void
    {
        $user = User::factory()->create();
        $otherWallet = Wallet::factory()->create();

        $this->authenticatedAs($user)
            ->getJson("/api/wallets/{$otherWallet->id}")
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Data tidak ditemukan.');
    }

    public function test_update_changes_name_and_description_only(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create([
            'name' => 'Kas Lama',
            'balance' => 75000,
        ]);

        $response = $this->authenticatedAs($user)->putJson("/api/wallets/{$wallet->id}", [
            'name' => 'Kas Baru',
            'description' => 'Diperbarui',
            'balance' => 9999999,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Kas Baru')
            ->assertJsonPath('data.balance', '75000.00');

        $this->assertDatabaseHas('wallets', [
            'id' => $wallet->id,
            'name' => 'Kas Baru',
            'balance' => 75000,
        ]);
    }

    public function test_update_returns_404_for_other_users_wallet(): void
    {
        $user = User::factory()->create();
        $otherWallet = Wallet::factory()->create();

        $this->authenticatedAs($user)
            ->putJson("/api/wallets/{$otherWallet->id}", ['name' => 'Hacked'])
            ->assertNotFound();

        $this->assertDatabaseHas('wallets', [
            'id' => $otherWallet->id,
            'name' => $otherWallet->name,
        ]);
    }

    private function authenticatedAs(User $user): static
    {
        return $this->withToken($user->createToken('api')->plainTextToken);
    }
}
