<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\ServiceCharge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupDemoServiceChargesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sets_service_charge_tiers_for_the_three_facilities_and_does_not_duplicate_them(): void
    {
        $facilities = [
            'MCC' => 'Metropolitan Care Clinic',
            'LSH' => 'London Surgery Hospital',
            'CCTH' => 'Central City Teaching Hospital',
        ];

        foreach ($facilities as $code => $name) {
            $business = Business::query()->create([
                'name' => $name,
                'email' => strtolower($code).'@example.com',
                'address' => 'Demo City',
                'account_number' => 'KS-'.$code,
                'entity_code' => $code,
            ]);
            User::factory()->create([
                'business_id' => $business->id,
                'email' => strtolower($code).'-user@example.com',
            ]);
        }

        $this->artisan('kashtre:setup-demo-service-charges')->assertSuccessful();
        $this->artisan('kashtre:setup-demo-service-charges')->assertSuccessful();

        foreach (array_keys($facilities) as $code) {
            $businessId = Business::query()->where('entity_code', $code)->value('id');
            $charges = ServiceCharge::query()
                ->where('business_id', $businessId)
                ->where('entity_type', 'business')
                ->orderBy('lower_bound')
                ->get();

            $this->assertCount(3, $charges);
            $this->assertSame('2.00', $charges[0]->amount);
            $this->assertSame('percentage', $charges[0]->type);
            $this->assertSame('1.00', $charges[2]->amount);
            $this->assertTrue((float) $charges[2]->upper_bound > 500000);
        }
    }
}
