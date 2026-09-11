<?php

namespace Tests\Feature\Clinical;

use App\Models\CdeObservation;
use App\Models\ClinicalWorkOrder;
use Database\Seeders\ClinicalMasterDictionariesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Unlike LimsIntegrationTest (which calls StubLimsClient in-process),
 * this hits the real signed HTTP route — proving a genuinely external
 * LIMS could actually call back into Clinical, not just that the local
 * dispatch driver can reach LimsIntegrationProxyService directly.
 */
class LimsWebhookControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = [null, 'clinical'];

    protected function setUp(): void
    {
        parent::setUp();

        (new ClinicalMasterDictionariesSeeder())->run();
        config(['services.module_endpoints.lims.secret' => 'test-lims-secret']);
    }

    private function signedPost(string $eventType, array $payload)
    {
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, 'test-lims-secret');

        return $this->call(
            'POST',
            "/api/v1/clinical/lab-proxy/{$eventType}",
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X-KashTre-Signature' => $signature],
            $body,
        );
    }

    public function test_a_correctly_signed_result_validated_call_lands_as_an_observation(): void
    {
        $workOrder = ClinicalWorkOrder::create([
            'business_id' => 1,
            'client_id' => 'CLIENT-WEBHOOK-1',
            'order_type' => 'LAB_GLUCOSE',
            'ordering_user_id' => 1,
            'status' => ClinicalWorkOrder::STATUS_PENDING,
            'external_module' => 'lims',
            'external_reference' => 'LAB-ORDER-UUID-1',
        ]);

        $response = $this->signedPost('result-validated', [
            'business_id' => 1,
            'branch_id' => null,
            'client_id' => 'CLIENT-WEBHOOK-1',
            'visit_id' => null,
            'lab_order_uuid' => 'LAB-ORDER-UUID-1',
            'test_code' => 'GLUCOSE',
            'cde_code' => 'GLUCOSE_RANDOM',
            'value_numeric' => 6.5,
            'unit_label' => 'mmol/L',
            'is_abnormal' => false,
            'validated_by_user_id' => 1,
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'RECORDED');

        $this->assertNotNull(CdeObservation::where('client_id', 'CLIENT-WEBHOOK-1')->where('cde_code', 'GLUCOSE_RANDOM')->first());
        $this->assertSame(ClinicalWorkOrder::STATUS_COMPLETED, $workOrder->fresh()->status);
    }

    public function test_a_missing_signature_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/clinical/lab-proxy/result-validated', [
            'business_id' => 1, 'client_id' => 'CLIENT-WEBHOOK-2', 'lab_order_uuid' => 'x',
            'test_code' => 'GLUCOSE', 'cde_code' => 'GLUCOSE_RANDOM', 'value_numeric' => 6.5,
            'unit_label' => null, 'is_abnormal' => false, 'validated_by_user_id' => 1,
        ]);

        $response->assertStatus(401);
        $this->assertSame(0, CdeObservation::where('client_id', 'CLIENT-WEBHOOK-2')->count());
    }

    public function test_a_tampered_body_fails_signature_verification(): void
    {
        $payload = [
            'business_id' => 1, 'client_id' => 'CLIENT-WEBHOOK-3', 'lab_order_uuid' => 'x',
            'test_code' => 'GLUCOSE', 'cde_code' => 'GLUCOSE_RANDOM', 'value_numeric' => 6.5,
            'unit_label' => null, 'is_abnormal' => false, 'validated_by_user_id' => 1,
        ];

        // Sign one payload but send a different one — the classic tamper case.
        $signature = hash_hmac('sha256', json_encode($payload), 'test-lims-secret');
        $tampered = json_encode(array_merge($payload, ['value_numeric' => 99999]));

        $response = $this->call('POST', '/api/v1/clinical/lab-proxy/result-validated', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-KashTre-Signature' => $signature,
        ], $tampered);

        $response->assertStatus(401);
    }

    public function test_an_unknown_event_type_returns_404_after_signature_passes(): void
    {
        $this->signedPost('not-a-real-event', ['foo' => 'bar'])->assertStatus(404);
    }
}
