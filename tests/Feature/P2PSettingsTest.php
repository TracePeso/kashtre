<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class P2PSettingsTest extends TestCase
{
    use DatabaseTransactions;

    protected function createTestUser($suffix, $businessId, $branchId)
    {
        return User::unguarded(function () use ($suffix, $businessId, $branchId) {
            return User::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'User ' . $suffix,
                'email' => 'user+' . $suffix . '@example.com',
                'email_verified_at' => now(),
                'two_factor_confirmed_at' => now(),
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'status' => 'active',
                'business_id' => $businessId,
                'branch_id' => $branchId,
                'permissions' => ['View Callers', 'Edit Callers', 'Manage Callers'],
            ]);
        });
    }

    public function test_user_can_save_p2p_settings()
    {
        $suffix = Str::random(8);
        $business = Business::unguarded(function () use ($suffix) {
            return Business::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Test Business',
                'email' => 'business+' . $suffix . '@example.com',
                'phone' => '0700000000',
                'address' => 'Test',
                'account_number' => 'ACC002',
                'currency_code' => 'UGX',
                'default_payment_terms_days' => 30,
                'date' => now()->toDateString(),
            ]);
        });

        $branch = Branch::unguarded(function () use ($business, $suffix) {
            return Branch::create([
                'uuid' => (string) Str::uuid(),
                'business_id' => $business->id,
                'name' => 'Main Branch',
                'email' => 'branch+' . $suffix . '@example.com',
                'phone' => '0700000001',
                'address' => 'Test',
            ]);
        });

        \App\Models\CallingModuleConfig::create([
            'business_id' => $business->id,
            'is_active' => true,
        ]);

        $user = $this->createTestUser($suffix, $business->id, $branch->id);

        $response = $this->actingAs($user)->post(route('service-point-callers.save-p2p-settings'), [
            'p2p_display_name' => 'Alias ' . $suffix,
            'p2p_ringtone' => 'urgent',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'p2p_display_name' => 'Alias ' . $suffix,
            'p2p_ringtone' => 'urgent',
        ]);
    }

    public function test_p2p_online_users_returns_preferred_display_name()
    {
        $suffix = Str::random(8);
        $business = Business::unguarded(function () use ($suffix) {
            return Business::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Test Business',
                'email' => 'business+' . $suffix . '@example.com',
                'phone' => '0700000000',
                'address' => 'Test',
                'account_number' => 'ACC002',
                'currency_code' => 'UGX',
                'default_payment_terms_days' => 30,
                'date' => now()->toDateString(),
            ]);
        });

        $branch = Branch::unguarded(function () use ($business, $suffix) {
            return Branch::create([
                'uuid' => (string) Str::uuid(),
                'business_id' => $business->id,
                'name' => 'Main Branch',
                'email' => 'branch-online+' . $suffix . '@example.com',
                'phone' => '0700000001',
                'address' => 'Test',
            ]);
        });

        \App\Models\CallingModuleConfig::create([
            'business_id' => $business->id,
            'is_active' => true,
        ]);

        $user1 = $this->createTestUser('Alice' . $suffix, $business->id, $branch->id);
        $user2 = $this->createTestUser('Bob' . $suffix, $business->id, $branch->id);

        $this->actingAs($user2)->post(route('service-point-callers.save-p2p-settings'), [
            'p2p_display_name' => 'Bobby P2P',
            'p2p_ringtone' => 'deep',
        ]);

        $response = $this->actingAs($user1)->getJson(route('calls.online-users'));

        $response->assertOk();
        $data = $response->json();

        $bobRecord = collect($data)->firstWhere('uuid', $user2->uuid);
        $this->assertNotNull($bobRecord);
        $this->assertEquals('Bobby P2P', $bobRecord['name']);
    }
}
