<?php

namespace Tests\Feature\Clinical;

use App\Models\ImagingOrder;
use App\Models\ImagingProtocol;
use App\Services\Clinical\Facts\DiagnosticOrderPlacedFact;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Unlike ModuleDispatcherTest (which fakes the HTTP call entirely), this
 * hits the real route with a real request — proving an endpoint actually
 * exists at the path HttpModuleDispatcher builds, not just that the
 * dispatcher builds a plausible-looking URL.
 */
class ImagingFactsControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = [null, 'clinical'];

    public function test_a_correctly_signed_request_creates_a_real_imaging_order(): void
    {
        ImagingProtocol::create([
            'business_id' => 1,
            'code' => 'FACTS_TEST_PROTOCOL',
            'name' => 'Facts Endpoint Test Protocol',
            'modality_type' => 'CT',
            'is_active' => true,
            'requires_consent' => false,
            'requires_preparation' => false,
        ]);

        $fact = new DiagnosticOrderPlacedFact(
            businessId: 1,
            branchId: null,
            globalClientId: 'CLIENT-FACTS-1',
            visitId: 'VISIT-FACTS-1',
            protocolCode: 'FACTS_TEST_PROTOCOL',
            orderingClinicianId: 1,
        );

        $response = $this->postJson(
            '/api/v1/imaging/facts/diagnostic-order-placed',
            $fact->toPayload(),
            ['X-Imaging-API-Key' => config('services.imaging_module.api_key')]
        );

        $response->assertOk();
        $response->assertJsonPath('status', 'ORDER_RECEIVED');

        $this->assertSame(1, ImagingOrder::where('client_id', 'CLIENT-FACTS-1')->count());
    }

    public function test_a_missing_or_wrong_api_key_is_rejected(): void
    {
        $fact = new DiagnosticOrderPlacedFact(
            businessId: 1,
            branchId: null,
            globalClientId: 'CLIENT-FACTS-2',
            visitId: null,
            protocolCode: 'FACTS_TEST_PROTOCOL',
            orderingClinicianId: 1,
        );

        $this->postJson('/api/v1/imaging/facts/diagnostic-order-placed', $fact->toPayload())
            ->assertStatus(401);

        $this->postJson(
            '/api/v1/imaging/facts/diagnostic-order-placed',
            $fact->toPayload(),
            ['X-Imaging-API-Key' => 'wrong-key']
        )->assertStatus(401);

        $this->assertSame(0, ImagingOrder::where('client_id', 'CLIENT-FACTS-2')->count());
    }

    public function test_an_unknown_fact_type_returns_404(): void
    {
        $this->postJson(
            '/api/v1/imaging/facts/not-a-real-fact',
            [],
            ['X-Imaging-API-Key' => config('services.imaging_module.api_key')]
        )->assertStatus(404);
    }
}
