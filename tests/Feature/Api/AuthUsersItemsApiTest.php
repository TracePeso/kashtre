<?php

namespace Tests\Feature\Api;

use App\Models\Business;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthUsersItemsApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeBusiness(): Business
    {
        return Business::query()->create([
            'name' => 'API Test Business',
            'email' => 'biz@example.com',
            'type' => 'clinic',
        ]);
    }

    public function test_user_can_login_with_email_and_password(): void
    {
        $business = $this->makeBusiness();
        $user = User::factory()->create([
            'email' => 'api.user@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
            'business_id' => $business->id,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'api.user@example.com',
            'password' => 'password',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'token',
                    'token_type',
                    'user' => ['uuid', 'email', 'name'],
                ],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertSame($user->email, $response->json('data.user.email'));
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $business = $this->makeBusiness();

        User::factory()->create([
            'email' => 'api.user@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
            'business_id' => $business->id,
            'two_factor_confirmed_at' => null,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'api.user@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    public function test_authenticated_user_can_list_users_and_items(): void
    {
        $business = $this->makeBusiness();
        $user = User::factory()->create([
            'status' => 'active',
            'business_id' => $business->id,
            'two_factor_confirmed_at' => null,
        ]);

        User::factory()->create([
            'status' => 'active',
            'business_id' => $business->id,
            'two_factor_confirmed_at' => null,
        ]);

        $item = Item::query()->create([
            'name' => 'Paracetamol',
            'generic_name' => 'Paracetamol',
            'category' => 'drug',
            'type' => 'good',
            'business_id' => $business->id,
            'default_price' => 1000,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'data', 'meta']);

        $this->getJson('/api/v1/items')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.uuid', $item->uuid);

        $this->getJson('/api/v1/items/'.$item->uuid)
            ->assertOk()
            ->assertJsonPath('data.name', 'Paracetamol');

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.uuid', $user->uuid);
    }
}
