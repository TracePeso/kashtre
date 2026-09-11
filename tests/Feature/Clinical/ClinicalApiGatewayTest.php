<?php

namespace Tests\Feature\Clinical;

use App\Services\Clinical\Api\Exceptions\ClinicalSafetyBlockException;
use App\Services\Clinical\Gateways\Api\ApiCareAccessGateway;
use App\Services\Clinical\Gateways\Api\ApiMarGateway;
use App\Services\Clinical\Gateways\Api\ApiMedicationOrdersGateway;
use App\Services\Clinical\Gateways\Api\ApiObservationsGateway;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Verifies the API gateways speak the shapes the API Integration Guide
 * documents — the right paths, the right payload keys, and above all the
 * idempotency keys that make a retry safe.
 *
 * No database: the tenant translation is pre-warmed into the cache, which is
 * the same code path a running system takes on its second request.
 */
class ClinicalApiGatewayTest extends TestCase
{
    private ClinicalActor $actor;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.clinical.url' => 'https://clinical.kashtre.test',
            'services.clinical.service_key' => 'test-service-key',
            'services.clinical.retry_times' => 1,
        ]);

        $this->actor = new ClinicalActor(
            userId: 104,
            businessId: 7,
            branchId: 3,
            name: 'Dr Aine Kato',
            permissions: ['Act As Consultant (Clinical)'],
        );

        // Avoids a Business lookup; the resolved value is what matters here.
        Cache::put('clinical:tenant:7', 'FACILITY_ALPHA', now()->addMinutes(10));
    }

    public function test_administering_a_dose_carries_a_key_derived_from_the_dose(): void
    {
        Http::fake(['clinical.kashtre.test/*' => Http::response(['data' => ['status' => 'ADMINISTERED']])]);

        app(ApiMarGateway::class)->administer($this->actor, 4821, [
            'patient_barcode' => 'CL-00001234',
            'drug_barcode' => 'DRUG-CEFTRIAXONE-1G',
            'dose_administered' => 1,
        ]);

        Http::assertSent(function (Request $request) {
            // Derived from the dose, not the attempt — this is what makes a
            // retry after a dropped connection replay rather than administer
            // the dose a second time.
            return str_ends_with($request->url(), '/api/v1/clinical/mar/doses/4821/administer')
                && $request->hasHeader('X-Idempotency-Key', 'mar-dose-4821-administer')
                && $request['patient_barcode'] === 'CL-00001234';
        });
    }

    public function test_a_hold_and_an_administration_do_not_share_an_idempotency_key(): void
    {
        Http::fake(['clinical.kashtre.test/*' => Http::response(['data' => []])]);

        app(ApiMarGateway::class)->hold($this->actor, 4821, 'MAR_HOLD_NPO');

        Http::assertSent(fn (Request $request) => $request->hasHeader('X-Idempotency-Key', 'mar-dose-4821-hold'));
    }

    public function test_prescribing_sends_the_generic_term_for_clinical_to_resolve(): void
    {
        Http::fake([
            'clinical.kashtre.test/*' => Http::response([
                'data' => [
                    'order_uuid' => 'MEDORD-2026-000123',
                    'status' => 'ACTIVE',
                    'fulfilment' => 'INTERNAL',
                    'items' => [[
                        'display_name' => 'Ceftriaxone 1g vial',
                        'inventory_sku' => 'DRUG-CEFTRIAXONE-1G',
                        'dose_quantity' => 1,
                        'route_code' => 'IV',
                        'frequency_code' => 'QD',
                    ]],
                ],
            ]),
        ]);

        $order = app(ApiMedicationOrdersGateway::class)->prescribe(
            actor: $this->actor,
            patientId: 'CL-00001234',
            visitId: 'VIS-2026-001245',
            draft: [
                'requested_term' => 'Ceftriaxone',
                'strength_descriptor' => '1g vial',
                'dose_amount' => 1.0,
                'route_code' => 'IV',
                'frequency_code' => 'QD',
            ],
            idempotencyKey: 'prescription-attempt-1',
        );

        $this->assertSame('MEDORD-2026-000123', $order->id);
        $this->assertSame('DRUG-CEFTRIAXONE-1G', $order->drug_code);
        $this->assertFalse($order->is_external);

        Http::assertSent(function (Request $request) {
            $item = $request['items'][0] ?? [];

            // We send the generic term, never a SKU — Clinical resolves the
            // brand by calling back into Main's catalogue lookup.
            return $item['requested_term'] === 'Ceftriaxone'
                && $item['dose_quantity'] === 1.0
                && $request['ordering_clinician_id'] === 104
                && $request->hasHeader('X-Idempotency-Key', 'prescription-attempt-1');
        });
    }

    public function test_an_override_retry_reuses_the_original_idempotency_key(): void
    {
        Http::fake([
            'clinical.kashtre.test/*' => Http::response([
                'data' => ['order_uuid' => 'MEDORD-1', 'status' => 'ACTIVE', 'items' => []],
            ]),
        ]);

        app(ApiMedicationOrdersGateway::class)->prescribe(
            actor: $this->actor,
            patientId: 'CL-00001234',
            visitId: null,
            draft: ['requested_term' => 'Warfarin', 'dose_amount' => 5.0, 'route_code' => 'PO', 'frequency_code' => 'QD'],
            overrideReasonCode: 'OVERRIDE_CLINICAL_JUDGEMENT',
            overrideNote: 'Benefit outweighs interaction risk.',
            idempotencyKey: 'prescription-attempt-1',
        );

        Http::assertSent(function (Request $request) {
            // The override retry is a continuation of one clinical decision,
            // so it must not place a second order.
            return $request['cdss_override_reason_code'] === 'OVERRIDE_CLINICAL_JUDGEMENT'
                && $request->hasHeader('X-Idempotency-Key', 'prescription-attempt-1');
        });
    }

    public function test_a_safety_block_propagates_with_its_blocks_intact(): void
    {
        Http::fake([
            'clinical.kashtre.test/*' => Http::response([
                'message' => 'This prescription is blocked by a clinical safety rule.',
                'errors' => [
                    'error_code' => 'CDSS_HARD_BLOCK',
                    'hard_blocks' => [['type' => 'DRUG_ALLERGY', 'detail' => 'Recorded penicillin allergy.']],
                ],
            ], 422),
        ]);

        try {
            app(ApiMedicationOrdersGateway::class)->prescribe(
                actor: $this->actor,
                patientId: 'CL-00001234',
                visitId: null,
                draft: ['requested_term' => 'Amoxicillin', 'dose_amount' => 500.0, 'route_code' => 'PO', 'frequency_code' => 'TDS'],
            );
            $this->fail('Expected a ClinicalSafetyBlockException.');
        } catch (ClinicalSafetyBlockException $e) {
            // The detail has to survive to the UI — it is what the clinician
            // reads before deciding whether to override.
            $this->assertSame('Recorded penicillin allergy.', $e->hardBlocks()[0]['detail']);
        }
    }

    public function test_charting_an_observation_omits_the_unit_when_none_is_chosen(): void
    {
        Http::fake([
            'clinical.kashtre.test/*' => Http::response([
                'data' => ['observation_id' => 9912, 'base_value_normalized' => 88, 'is_panic_high' => false],
            ]),
        ]);

        $record = app(ApiObservationsGateway::class)->capture(
            $this->actor,
            'CL-00001234',
            'VIS-2026-001245',
            ['cde_code' => 'PULSE_RATE', 'value_numeric' => 88.0, 'input_uom_id' => null],
        );

        $this->assertSame(9912, $record->id);
        $this->assertSame(88.0, $record->base_value_numeric);

        Http::assertSent(function (Request $request) {
            // §10.2: omitting input_uom_id means "use the CDE's base unit".
            // Sending an explicit null is a validation failure instead.
            return ! array_key_exists('input_uom_id', $request->data())
                && $request['cde_code'] === 'PULSE_RATE';
        });
    }

    public function test_a_flowsheet_read_asks_for_rescaled_display_units(): void
    {
        Http::fake(['clinical.kashtre.test/*' => Http::response(['data' => []])]);

        app(ApiObservationsGateway::class)->recentForPatient(
            $this->actor,
            'CL-00001234',
            limit: 50,
            cdeCode: 'GLUCOSE_RANDOM',
            displayUomId: 7,
        );

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'display_uom_id=7')
            && str_contains($request->url(), 'cde_code=GLUCOSE_RANDOM'));
    }

    public function test_a_care_relationship_check_that_fails_denies_rather_than_assumes(): void
    {
        Http::fake(['clinical.kashtre.test/*' => Http::response(['message' => 'boom'], 500)]);

        // Failing closed on display: we would rather hide an action the
        // clinician can perform than offer one Clinical will refuse.
        $this->assertFalse(
            app(ApiCareAccessGateway::class)->hasActiveRelationship($this->actor, 'CL-00001234')
        );
    }

    public function test_off_premises_detection_defaults_to_restricting_when_unknown(): void
    {
        Http::fake(['clinical.kashtre.test/*' => Http::response(['message' => 'boom'], 503)]);

        $this->assertFalse(app(ApiCareAccessGateway::class)->canMutateFromCurrentLocation());
    }
}
