<?php

namespace Tests\Feature\Clinical;

use App\Services\Clinical\Api\ClinicalMainNotifier;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The outbound half of §9.1 — the facts only Main knows and Clinical needs.
 *
 * The behaviour these lock down is the failure mode, not the happy path: a
 * clinical outage must not be able to stop a visit from opening.
 */
class ClinicalMainNotifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.clinical.url' => 'https://clinical.kashtre.test',
            'services.clinical.service_key' => 'test-service-key',
            'services.clinical.default_tenant' => 'FACILITY_ALPHA',
            'services.clinical.retry_times' => 1,
        ]);
    }

    public function test_it_carries_the_previous_visit_so_pending_orders_follow_the_patient(): void
    {
        Http::fake(['clinical.kashtre.test/*' => Http::response(['data' => []])]);

        $sent = app(ClinicalMainNotifier::class)->encounterCreated(
            'CL-00001234',
            'VIS-2026-001399',
            'VIS-2026-001245',
        );

        $this->assertTrue($sent);

        Http::assertSent(function (Request $request) {
            // Without previous_visit_id, a returning outpatient's already
            // printed barcodes stop matching anything.
            return str_ends_with($request->url(), '/api/v1/clinical/encounters/created')
                && $request['previous_visit_id'] === 'VIS-2026-001245'
                && $request->hasHeader('X-Idempotency-Key', 'encounter-created-VIS-2026-001399');
        });
    }

    public function test_it_sends_no_clinician_identity_for_module_traffic(): void
    {
        Http::fake(['clinical.kashtre.test/*' => Http::response(['data' => []])]);

        app(ClinicalMainNotifier::class)->encounterCreated('CL-1', 'VIS-1');

        Http::assertSent(function (Request $request) {
            // §3.2: no identity means "this is module traffic, not a person".
            // A registration desk opening a visit has no acting clinician, and
            // inventing one would misattribute it in Clinical's audit trail.
            return ! $request->hasHeader('X-User-Id')
                && $request->hasHeader('X-Service-Key', 'test-service-key');
        });
    }

    public function test_a_clinical_outage_does_not_break_the_main_side_workflow(): void
    {
        Http::fake(['clinical.kashtre.test/*' => Http::response(['message' => 'down'], 503)]);

        // Returns false rather than throwing: refusing to open a visit because
        // the clinical module is unavailable would take the registration desk
        // down with it.
        $this->assertFalse(
            app(ClinicalMainNotifier::class)->encounterCreated('CL-1', 'VIS-1')
        );
    }

    public function test_it_stays_quiet_when_the_clinical_module_is_not_configured(): void
    {
        config(['services.clinical.url' => null]);
        Http::fake();

        // CLINICAL_DRIVER=local — the clinical data is in this database and
        // there is nobody to notify. Not an error, and not a request.
        $this->assertFalse(app(ClinicalMainNotifier::class)->encounterCreated('CL-1', 'VIS-1'));

        Http::assertNothingSent();
    }

    public function test_entitlements_are_posted_with_their_allocations(): void
    {
        Http::fake(['clinical.kashtre.test/*' => Http::response(['data' => []])]);

        app(ClinicalMainNotifier::class)->entitlementGranted(
            'CL-00001234',
            'PKG-ANC-0012',
            [['service_code' => 'SVC-CBC', 'allocated_qty' => 3]],
        );

        Http::assertSent(function (Request $request) {
            return $request['patient_id'] === 'CL-00001234'
                && $request['allocations'][0]['service_code'] === 'SVC-CBC'
                && $request['allocations'][0]['allocated_qty'] === 3;
        });
    }

    public function test_linking_an_infant_targets_the_birth_record(): void
    {
        Http::fake(['clinical.kashtre.test/*' => Http::response(['data' => []])]);

        app(ClinicalMainNotifier::class)->linkInfant(42, 'CL-00009001', 'VIS-2026-009001');

        Http::assertSent(fn (Request $request) => str_ends_with(
            $request->url(),
            '/api/v1/clinical/maternity/birth-records/42/link-infant'
        ) && $request['infant_patient_id'] === 'CL-00009001');
    }
}
