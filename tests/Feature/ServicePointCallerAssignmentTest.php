<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Caller;
use App\Models\CallingModuleConfig;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\CallingServiceClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ServicePointCallerAssignmentTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $callingServiceClient = Mockery::mock(CallingServiceClient::class);
        $callingServiceClient->shouldReceive('syncCaller')->andReturnNull();
        $this->app->instance(CallingServiceClient::class, $callingServiceClient);
    }

    public function test_caller_can_be_created_and_assigned_to_current_business_service_points(): void
    {
        $suffix = Str::lower(Str::random(8));
        [$business, $branch] = $this->createBusinessBranchPair($suffix);
        [$otherBusiness, $otherBranch] = $this->createBusinessBranchPair('other-' . $suffix);

        CallingModuleConfig::create([
            'business_id' => $business->id,
            'is_active' => true,
        ]);

        $user = User::unguarded(function () use ($business, $branch, $suffix) {
            return User::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Caller Admin',
                'email' => 'caller-admin+' . $suffix . '@example.com',
                'email_verified_at' => now(),
                'two_factor_confirmed_at' => now(),
                'password' => bcrypt('password'),
                'status' => 'active',
                'business_id' => $business->id,
                'branch_id' => $branch->id,
                'permissions' => ['Manage Callers'],
            ]);
        });

        $servicePointA = ServicePoint::unguarded(function () use ($business, $branch) {
            return ServicePoint::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Pharmacy',
                'business_id' => $business->id,
                'branch_id' => $branch->id,
            ]);
        });

        $servicePointB = ServicePoint::unguarded(function () use ($business, $branch) {
            return ServicePoint::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Laboratory',
                'business_id' => $business->id,
                'branch_id' => $branch->id,
            ]);
        });

        $foreignServicePoint = ServicePoint::unguarded(function () use ($otherBusiness, $otherBranch) {
            return ServicePoint::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Foreign Desk',
                'business_id' => $otherBusiness->id,
                'branch_id' => $otherBranch->id,
            ]);
        });

        $response = $this->actingAs($user)->post(route('service-point-callers.store'), [
            'name' => 'Front Desk Caller',
            'service_point_ids' => [$servicePointA->id, $servicePointB->id, $foreignServicePoint->id],
        ]);

        $response->assertRedirect(route('service-point-callers.index'));
        $response->assertSessionHas('success');

        $caller = Caller::where('business_id', $business->id)
            ->where('name', 'Front Desk Caller')
            ->first();

        $this->assertNotNull($caller);
        $this->assertSame('active', $caller->status);
        $this->assertDatabaseHas('caller_service_points', [
            'caller_id' => $caller->id,
            'service_point_id' => $servicePointA->id,
        ]);
        $this->assertDatabaseHas('caller_service_points', [
            'caller_id' => $caller->id,
            'service_point_id' => $servicePointB->id,
        ]);
        $this->assertDatabaseMissing('caller_service_points', [
            'caller_id' => $caller->id,
            'service_point_id' => $foreignServicePoint->id,
        ]);
    }

    public function test_fix_existing_tables_command_is_safe_for_current_calling_schema(): void
    {
        $this->assertTrue(Schema::hasTable('callers'));
        $this->assertTrue(Schema::hasTable('caller_service_points'));
        $this->assertTrue(Schema::hasTable('calling_module_configs'));
        $this->assertTrue(Schema::hasColumn('callers', 'display_token'));
        $this->assertTrue(Schema::hasColumn('callers', 'announcement_message'));
        $this->assertTrue(Schema::hasColumn('callers', 'speech_rate'));
        $this->assertTrue(Schema::hasColumn('callers', 'speech_volume'));
        $this->assertTrue(Schema::hasColumn('calling_module_configs', 'audio_enabled'));
        $this->assertTrue(Schema::hasColumn('calling_module_configs', 'video_enabled'));
        $this->assertTrue(Schema::hasColumn('calling_module_configs', 'default_emergency_message'));

        $this->artisan('fix:existing-tables')->assertExitCode(0);
    }

    protected function createBusinessBranchPair(string $suffix): array
    {
        $business = Business::unguarded(function () use ($suffix) {
            return Business::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Business ' . $suffix,
                'email' => 'business+' . $suffix . '@example.com',
                'phone' => '0700000000',
                'address' => 'Kampala',
                'account_number' => 'ACC' . strtoupper(Str::random(6)),
                'currency_code' => 'UGX',
                'default_payment_terms_days' => 30,
                'date' => now()->toDateString(),
            ]);
        });

        $branch = Branch::unguarded(function () use ($business, $suffix) {
            return Branch::create([
                'uuid' => (string) Str::uuid(),
                'business_id' => $business->id,
                'name' => 'Branch ' . $suffix,
                'email' => 'branch+' . $suffix . '@example.com',
                'phone' => '0700000001',
                'address' => 'Kampala',
            ]);
        });

        return [$business, $branch];
    }
}
