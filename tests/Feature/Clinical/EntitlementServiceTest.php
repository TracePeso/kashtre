<?php

namespace Tests\Feature\Clinical;

use App\Models\ClinicalEntitlement;
use App\Services\Clinical\EntitlementService;
use Tests\TestCase;

class EntitlementServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make('db')->connection('clinical')->beginTransaction();
        $this->beforeApplicationDestroyed(fn () => $this->app->make('db')->connection('clinical')->rollBack());
    }

    public function test_consuming_within_the_allocated_balance_decrements_it(): void
    {
        $entitlement = ClinicalEntitlement::create([
            'business_id' => 1,
            'client_id' => 'CLIENT-ENT-1',
            'package_id' => 'ANC_PACKAGE',
            'service_code' => 'OBSTETRIC_ULTRASOUND',
            'allocated_qty' => 3,
        ]);

        $result = app(EntitlementService::class)->consume(1, 'CLIENT-ENT-1', 'OBSTETRIC_ULTRASOUND');

        $this->assertTrue($result['consumed_from_entitlement']);
        $this->assertFalse($result['billing_required']);
        $this->assertSame(2, $entitlement->fresh()->remaining_qty);
    }

    public function test_exhausted_entitlement_routes_to_billing(): void
    {
        ClinicalEntitlement::create([
            'business_id' => 1,
            'client_id' => 'CLIENT-ENT-2',
            'package_id' => 'ANC_PACKAGE',
            'service_code' => 'OBSTETRIC_ULTRASOUND',
            'allocated_qty' => 1,
            'used_qty' => 1,
        ]);

        $result = app(EntitlementService::class)->consume(1, 'CLIENT-ENT-2', 'OBSTETRIC_ULTRASOUND');

        $this->assertFalse($result['consumed_from_entitlement']);
        $this->assertTrue($result['billing_required']);
    }

    public function test_no_entitlement_at_all_routes_to_billing(): void
    {
        $result = app(EntitlementService::class)->consume(1, 'CLIENT-ENT-3', 'SOME_SERVICE');

        $this->assertFalse($result['consumed_from_entitlement']);
        $this->assertTrue($result['billing_required']);
        $this->assertNull($result['entitlement_id']);
    }
}
