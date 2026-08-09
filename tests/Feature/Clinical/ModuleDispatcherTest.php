<?php

namespace Tests\Feature\Clinical;

use App\Contracts\ModuleDispatcher;
use App\Services\Clinical\Dispatch\HttpModuleDispatcher;
use App\Services\Clinical\Dispatch\LocalFactReceiverRegistry;
use App\Services\Clinical\Dispatch\LocalModuleDispatcher;
use App\Services\Clinical\Facts\DiagnosticOrderPlacedFact;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ModuleDispatcherTest extends TestCase
{
    private function fact(): DiagnosticOrderPlacedFact
    {
        return new DiagnosticOrderPlacedFact(
            businessId: 1,
            branchId: 1,
            globalClientId: 'CLIENT-1',
            visitId: 'VISIT-1',
            protocolCode: 'CT_HEAD_PLAIN',
            orderingClinicianId: 99,
            modality: 'CT',
        );
    }

    public function test_default_binding_resolves_to_local_dispatcher(): void
    {
        $this->assertInstanceOf(LocalModuleDispatcher::class, $this->app->make(ModuleDispatcher::class));
    }

    public function test_local_dispatcher_round_trips_through_registered_receiver(): void
    {
        $fact = $this->fact();

        app(LocalFactReceiverRegistry::class)->register(
            $fact->targetModule(),
            $fact->factType(),
            function (array $payload) {
                return [
                    'status' => 'ORDER_RECEIVED',
                    'echoed_protocol_code' => $payload['protocol_code'],
                ];
            }
        );

        $response = app(ModuleDispatcher::class)->dispatch($fact);

        $this->assertSame('ORDER_RECEIVED', $response['status']);
        $this->assertSame('CT_HEAD_PLAIN', $response['echoed_protocol_code']);
    }

    public function test_http_dispatcher_posts_the_same_payload_shape_to_the_module_endpoint(): void
    {
        config([
            'services.module_endpoints.imaging' => ['url' => 'https://imaging.kashtre.test'],
            'services.imaging_module.api_key' => 'secret-key',
        ]);

        Http::fake([
            'imaging.kashtre.test/*' => Http::response(['status' => 'ORDER_RECEIVED'], 201),
        ]);

        $fact = $this->fact();
        $response = $this->app->make(HttpModuleDispatcher::class)->dispatch($fact);

        $this->assertSame('ORDER_RECEIVED', $response['status']);

        Http::assertSent(function ($request) use ($fact) {
            return $request->url() === 'https://imaging.kashtre.test/api/v1/imaging/facts/diagnostic-order-placed'
                && $request->hasHeader('X-Imaging-API-Key', 'secret-key')
                && $request['protocol_code'] === $fact->toPayload()['protocol_code'];
        });
    }
}
