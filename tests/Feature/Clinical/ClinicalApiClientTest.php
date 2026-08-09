<?php

namespace Tests\Feature\Clinical;

use App\Services\Clinical\Api\ClinicalApiClient;
use App\Services\Clinical\Api\Exceptions\ClinicalAccessDeniedException;
use App\Services\Clinical\Api\Exceptions\ClinicalAuthException;
use App\Services\Clinical\Api\Exceptions\ClinicalBiometricRequiredException;
use App\Services\Clinical\Api\Exceptions\ClinicalChartLockedException;
use App\Services\Clinical\Api\Exceptions\ClinicalRuleRefusedException;
use App\Services\Clinical\Api\Exceptions\ClinicalSafetyBlockException;
use App\Services\Clinical\Api\Exceptions\ClinicalUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the cross-cutting contract from the API Integration Guide — the
 * envelope, the headers and the §6 status-to-exception mapping — without a
 * live Clinical service.
 *
 * Deliberately no database: with no authenticated user the request context
 * resolves to the DEFAULT tenant without a lookup, which is exactly the
 * "module traffic, not a person" case §3.2 describes.
 */
class ClinicalApiClientTest extends TestCase
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

    private function client(): ClinicalApiClient
    {
        return app(ClinicalApiClient::class);
    }

    public function test_it_sends_the_service_key_and_tenant_on_every_request(): void
    {
        Http::fake(['clinical.kashtre.test/*' => Http::response(['data' => []])]);

        $this->client()->get('clinical/patients/CL-1/observations');

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('X-Service-Key', 'test-service-key')
                && $request->hasHeader('X-Tenant-Id', 'FACILITY_ALPHA')
                && $request->hasHeader('X-Request-Id');
        });
    }

    public function test_it_unwraps_the_data_envelope(): void
    {
        Http::fake([
            'clinical.kashtre.test/*' => Http::response([
                'data' => ['observation_id' => 9912, 'base_value_normalized' => 88],
                'meta' => ['count' => 1],
            ]),
        ]);

        $data = $this->client()->post('clinical/observations', ['cde_code' => 'PULSE_RATE']);

        $this->assertSame(9912, $data['observation_id']);
    }

    public function test_it_returns_meta_when_the_caller_asks_for_the_envelope(): void
    {
        Http::fake([
            'clinical.kashtre.test/*' => Http::response(['data' => [1, 2, 3], 'meta' => ['count' => 3]]),
        ]);

        $envelope = $this->client()->getEnvelope('clinical/patients/CL-1/orders');

        $this->assertSame(3, $envelope['meta']['count']);
        $this->assertCount(3, $envelope['data']);
    }

    public function test_it_forwards_an_idempotency_key_when_one_is_supplied(): void
    {
        Http::fake(['clinical.kashtre.test/*' => Http::response(['data' => []])]);

        $this->client()->post('clinical/mar/doses/4821/administer', [], [
            'idempotency_key' => 'mar-dose-4821-administer',
        ]);

        Http::assertSent(fn (Request $request) => $request->hasHeader('X-Idempotency-Key', 'mar-dose-4821-administer'));
    }

    public function test_a_cdss_hard_block_becomes_a_safety_block_exception(): void
    {
        Http::fake([
            'clinical.kashtre.test/*' => Http::response([
                'message' => 'This prescription is blocked by a clinical safety rule.',
                'errors' => [
                    'error_code' => 'CDSS_HARD_BLOCK',
                    'hard_blocks' => [['type' => 'DRUG_ALLERGY', 'detail' => 'Recorded penicillin allergy.']],
                ],
                'request_id' => 'req-abc',
            ], 422),
        ]);

        try {
            $this->client()->post('clinical/orders/medications', []);
            $this->fail('Expected a ClinicalSafetyBlockException.');
        } catch (ClinicalSafetyBlockException $e) {
            $this->assertSame('CDSS_HARD_BLOCK', $e->errorCode());
            $this->assertSame('req-abc', $e->requestId());
            $this->assertSame('DRUG_ALLERGY', $e->hardBlocks()[0]['type']);
            // A refusal must never be retried — the answer will not change.
            $this->assertFalse($e->isRetryable());
        }
    }

    public function test_an_unmatched_catalogue_item_is_distinguishable_from_a_safety_block(): void
    {
        Http::fake([
            'clinical.kashtre.test/*' => Http::response([
                'message' => 'Not in the catalogue.',
                'errors' => ['error_code' => 'EXTERNAL_FULFILMENT_REQUIRED', 'unmatched' => ['Ceftriaxone']],
            ], 422),
        ]);

        try {
            $this->client()->post('clinical/orders/medications', []);
            $this->fail('Expected a ClinicalRuleRefusedException.');
        } catch (ClinicalRuleRefusedException $e) {
            $this->assertTrue($e->requiresExternalFulfilment());
            $this->assertSame(['Ceftriaxone'], $e->unmatched());
        }
    }

    public function test_a_rebac_denial_reports_whether_break_glass_would_help(): void
    {
        Http::fake([
            'clinical.kashtre.test/*' => Http::response([
                'message' => 'No care relationship with this patient.',
                'errors' => ['error_code' => 'REBAC_ACCESS_DENIED', 'requires_break_glass' => true],
            ], 403),
        ]);

        try {
            $this->client()->get('clinical/patients/CL-1/observations');
            $this->fail('Expected a ClinicalAccessDeniedException.');
        } catch (ClinicalAccessDeniedException $e) {
            $this->assertTrue($e->requiresBreakGlass());
            $this->assertFalse($e->isOffPremisesRestriction());
        }
    }

    public function test_an_off_premises_restriction_is_not_offered_break_glass(): void
    {
        Http::fake([
            'clinical.kashtre.test/*' => Http::response([
                'message' => 'Barred off-premises.',
                'errors' => ['error_code' => 'ZTNA_OFFSITE_MUTATION_RESTRICTED'],
            ], 403),
        ]);

        try {
            $this->client()->post('clinical/mar/doses/1/administer', []);
            $this->fail('Expected a ClinicalAccessDeniedException.');
        } catch (ClinicalAccessDeniedException $e) {
            $this->assertTrue($e->isOffPremisesRestriction());
            $this->assertFalse($e->requiresBreakGlass());
        }
    }

    public function test_a_locked_chart_is_never_retryable(): void
    {
        Http::fake([
            'clinical.kashtre.test/*' => Http::response([
                'message' => 'Chart frozen for mortality audit.',
                'errors' => ['error_code' => 'CHART_LOCKED'],
            ], 409),
        ]);

        try {
            $this->client()->post('clinical/observations', []);
            $this->fail('Expected a ClinicalChartLockedException.');
        } catch (ClinicalChartLockedException $e) {
            // A deceased patient's chart takes no writes, ever. Retrying is
            // pointless and presenting it as fixable misleads the clinician.
            $this->assertTrue($e->isChartLocked());
            $this->assertFalse($e->isRetryable());
        }
    }

    public function test_an_in_flight_idempotent_request_is_worth_retrying(): void
    {
        Http::fake([
            'clinical.kashtre.test/*' => Http::response([
                'message' => 'Still running.',
                'errors' => ['error_code' => 'IDEMPOTENCY_REQUEST_IN_FLIGHT'],
            ], 409),
        ]);

        try {
            $this->client()->post('clinical/observations', []);
            $this->fail('Expected a ClinicalChartLockedException.');
        } catch (ClinicalChartLockedException $e) {
            $this->assertTrue($e->isIdempotencyConflict());
            $this->assertTrue($e->isRetryable());
        }
    }

    public function test_a_biometric_challenge_requirement_surfaces_as_its_own_type(): void
    {
        Http::fake([
            'clinical.kashtre.test/*' => Http::response([
                'message' => 'Confirm your identity.',
                'errors' => ['error_code' => 'BIOMETRIC_REAUTH_REQUIRED'],
            ], 428),
        ]);

        $this->expectException(ClinicalBiometricRequiredException::class);

        $this->client()->get('clinical/patients/CL-1/observations');
    }

    public function test_it_flags_when_clinical_has_made_identity_tokens_mandatory(): void
    {
        Http::fake([
            'clinical.kashtre.test/*' => Http::response([
                'message' => 'Identity token required.',
                'errors' => ['error_code' => 'IDENTITY_TOKEN_REQUIRED'],
            ], 401),
        ]);

        try {
            $this->client()->get('clinical/patients/CL-1/observations');
            $this->fail('Expected a ClinicalAuthException.');
        } catch (ClinicalAuthException $e) {
            // The fix is CLINICAL_IDENTITY_TRANSPORT=jwt, not a retry.
            $this->assertTrue($e->requiresIdentityToken());
            $this->assertFalse($e->isRetryable());
        }
    }

    public function test_a_degraded_dependency_is_retryable(): void
    {
        Http::fake([
            'clinical.kashtre.test/*' => Http::response([
                'message' => 'The catalogue lookup is unavailable.',
                'errors' => ['error_code' => 'CATALOGUE_UNAVAILABLE'],
            ], 503),
        ]);

        try {
            $this->client()->post('clinical/orders/medications', []);
            $this->fail('Expected a ClinicalUnavailableException.');
        } catch (ClinicalUnavailableException $e) {
            $this->assertTrue($e->isRetryable());
        }
    }

    public function test_an_unreachable_service_does_not_leak_a_transport_exception(): void
    {
        Http::fake([
            'clinical.kashtre.test/*' => fn () => throw new ConnectionException('Connection refused'),
        ]);

        $this->expectException(ClinicalUnavailableException::class);

        $this->client()->get('clinical/patients/CL-1/observations');
    }

    public function test_an_unconfigured_module_fails_as_unavailable_rather_than_calling_nowhere(): void
    {
        config(['services.clinical.url' => null]);

        $this->assertFalse($this->client()->isConfigured());
        $this->expectException(ClinicalUnavailableException::class);

        $this->client()->get('clinical/patients/CL-1/observations');
    }

    public function test_health_reports_ok_when_the_service_is_up(): void
    {
        Http::fake([
            'clinical.kashtre.test/*' => Http::response([
                'data' => ['status' => 'ok', 'checks' => ['database' => true]],
            ]),
        ]);

        $health = $this->client()->health();

        $this->assertTrue($health['ok']);
        $this->assertTrue($health['checks']['database']);
    }

    public function test_health_answers_rather_than_throwing_when_the_service_is_down(): void
    {
        Http::fake([
            'clinical.kashtre.test/*' => fn () => throw new ConnectionException('down'),
        ]);

        // The probe is how a caller tells "Clinical is down" from "my service
        // key is wrong", so it has to return an answer, not raise.
        $health = $this->client()->health();

        $this->assertFalse($health['ok']);
        $this->assertSame('unreachable', $health['status']);
    }
}
