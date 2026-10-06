<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_global_and_own_categories_only(): void
    {
        $user = User::factory()->create();

        Category::factory()->global()->expense()->create(['name' => 'Operasional']);
        Category::factory()->for($user)->income()->create(['name' => 'Gaji Pribadi']);
        Category::factory()->expense()->create(['name' => 'Milik Orang Lain']);

        $response = $this->authenticatedAs($user)->getJson('/api/categories');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'user_id', 'name', 'type', 'is_global']]]);
    }

    public function test_index_can_filter_by_type(): void
    {
        $user = User::factory()->create();

        Category::factory()->for($user)->income()->create();
        Category::factory()->for($user)->expense()->create();

        $response = $this->authenticatedAs($user)->getJson('/api/categories?type=income');

        $response->assertOk()->assertJsonCount(1, 'data');

        $this->assertSame('income', $response->json('data.0.type'));
    }

    public function test_index_rejects_invalid_type_filter(): void
    {
        $user = User::factory()->create();

        $this->authenticatedAs($user)
            ->getJson('/api/categories?type=transfer')
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['type']]);
    }

    public function test_store_creates_custom_category(): void
    {
        $user = User::factory()->create();

        $response = $this->authenticatedAs($user)->postJson('/api/categories', [
            'name' => 'Langganan Software',
            'type' => 'expense',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Langganan Software')
            ->assertJsonPath('data.type', 'expense')
            ->assertJsonPath('data.is_global', false)
            ->assertJsonPath('data.user_id', $user->id);

        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'Langganan Software',
            'type' => 'expense',
        ]);
    }

    public function test_store_rejects_transfer_type(): void
    {
        $user = User::factory()->create();

        $this->authenticatedAs($user)
            ->postJson('/api/categories', ['name' => 'Transfer', 'type' => 'transfer'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['type']]);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/categories')->assertUnauthorized();
    }

    private function authenticatedAs(User $user): static
    {
        return $this->withToken($user->createToken('api')->plainTextToken);
    }
}
