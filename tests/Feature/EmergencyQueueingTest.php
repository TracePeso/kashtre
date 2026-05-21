<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CallingModuleConfig;
use App\Models\EmergencyAlert;
use App\Models\User;
use App\Services\EmergencyAlertService;
use App\Services\CallingServiceClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class EmergencyQueueingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $callingServiceClient = Mockery::mock(CallingServiceClient::class);
        $callingServiceClient->shouldReceive('syncEmergency')->andReturnNull();
        $this->app->instance(CallingServiceClient::class, $callingServiceClient);
    }

    public function test_multiple_emergencies_queue_properly(): void
    {
        $suffix = Str::lower(Str::random(8));
        $now = Carbon::parse('2026-04-04 10:00:00');
        Carbon::setTestNow($now);

        try {
            $business = Business::unguarded(function () use ($suffix) {
                return Business::create([
                    'uuid' => (string) Str::uuid(),
                    'name' => 'City Health Clinic Testing',
                    'email' => 'clinic+' . $suffix . '@example.com',
                    'phone' => '0700000000',
                    'address' => 'Kampala',
                    'account_number' => 'ACC002',
                    'currency_code' => 'UGX',
                    'default_payment_terms_days' => 30,
                    'date' => now()->toDateString(),
                ]);
            });

            $user = User::unguarded(function () use ($business, $suffix) {
                return User::create([
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Cashier',
                    'email' => 'cashier+' . $suffix . '@example.com',
                    'email_verified_at' => now(),
                    'two_factor_confirmed_at' => now(),
                    'password' => \Illuminate\Support\Facades\Hash::make('password'),
                    'status' => 'active',
                    'business_id' => $business->id,
                    'branch_id' => 1,
                    'permissions' => ['Cashier'],
                ]);
            });

            // Temporarily put something in session since trigger_global resolves room via session
            session(['room_id' => 1]);

            $config = CallingModuleConfig::create([
                'business_id' => $business->id,
                'is_active' => true,
                'emergency_display_duration' => 30,
                'tts_speed' => 1.0,
                'emergency_repeat_count' => 1,
                'emergency_repeat_interval' => 0,
            ]);

            // First emergency
            $response1 = $this->actingAs($user)->post(route('emergency.trigger.global'), [
                'message' => 'First Emergency',
                'button_index' => 1,
            ]);
            $response1->assertOk();

            $alert1 = EmergencyAlert::where('business_id', $business->id)
                ->where('message', 'First Emergency')
                ->first();

            $this->assertNotNull($alert1);
            $this->assertTrue((bool) $alert1->is_active);
            $this->assertNotNull($alert1->activated_at);

            $response2 = $this->actingAs($user)->post(route('emergency.trigger.global'), [
                'message' => 'Second Emergency',
                'button_index' => 2,
            ]);
            $response2->assertOk();

            $alert2 = EmergencyAlert::where('business_id', $business->id)
                ->where('message', 'Second Emergency')
                ->first();

            $this->assertNotNull($alert2);
            $this->assertFalse((bool) $alert2->is_active);
            $this->assertNull($alert2->activated_at);

            $diffSeconds = Carbon::parse($alert2->scheduled_announce_at)->diffInSeconds($now);
            $this->assertGreaterThanOrEqual(40, $diffSeconds);

            $service = app(EmergencyAlertService::class);

            $activeNow = $service->resolveActiveAlertForBusiness($business->id);
            $this->assertNotNull($activeNow);
            $this->assertSame($alert1->id, $activeNow->id);

            Carbon::setTestNow($now->copy()->addSeconds(31));
            $expired = $service->resolveActiveAlertForBusiness($business->id);
            $this->assertNull($expired);

            $alert2->refresh();
            $this->assertFalse((bool) $alert2->is_active);

            Carbon::setTestNow($now->copy()->addSeconds(41));
            $activated = $service->resolveActiveAlertForBusiness($business->id);
            $this->assertNotNull($activated);
            $this->assertSame($alert2->id, $activated->id);
        } finally {
            Carbon::setTestNow();
        }
    }
}
