<?php

namespace Tests\Feature\Clinical;

use App\Services\AiGateway\AiGatewayClientService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiGatewayClientServiceTest extends TestCase
{
    public function test_it_is_unavailable_when_unconfigured(): void
    {
        config(['services.ai_gateway.url' => null]);

        $this->assertFalse(app(AiGatewayClientService::class)->isAvailable());

        $result = app(AiGatewayClientService::class)->extractObservations('CLIENT-AI-1', null, 'some notes');

        $this->assertFalse($result['available']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_it_degrades_gracefully_when_the_gateway_is_unreachable(): void
    {
        config(['services.ai_gateway.url' => 'https://ai-gateway.kashtre.test']);

        Http::fake([
            'ai-gateway.kashtre.test/*' => function () {
                throw new ConnectionException('Connection refused');
            },
        ]);

        $result = app(AiGatewayClientService::class)->extractObservations('CLIENT-AI-2', null, 'some notes');

        $this->assertFalse($result['available']);
    }

    public function test_it_degrades_gracefully_on_a_non_2xx_response(): void
    {
        config(['services.ai_gateway.url' => 'https://ai-gateway.kashtre.test']);

        Http::fake([
            'ai-gateway.kashtre.test/*' => Http::response(['error' => 'internal'], 500),
        ]);

        $result = app(AiGatewayClientService::class)->extractObservations('CLIENT-AI-3', null, 'some notes');

        $this->assertFalse($result['available']);
    }

    public function test_a_successful_call_parses_the_response_and_sends_the_right_headers(): void
    {
        config([
            'services.ai_gateway.url' => 'https://ai-gateway.kashtre.test',
            'services.ai_gateway.api_key' => 'secret-key',
        ]);

        Http::fake([
            'ai-gateway.kashtre.test/*' => Http::response([
                'intent_id' => 'abc-123',
                'observations' => [
                    ['cde_code' => 'GLUCOSE_RANDOM', 'dataElement' => 'Glucose', 'value' => 7.2, 'unit' => 'mmol/L'],
                ],
                'requiresValidation' => true,
            ], 200),
        ]);

        $result = app(AiGatewayClientService::class)->extractObservations('CLIENT-AI-4', 'VISIT-1', 'glucose was 7.2');

        $this->assertTrue($result['available']);
        $this->assertSame('abc-123', $result['intent_id']);
        $this->assertCount(1, $result['observations']);
        $this->assertTrue($result['requiresValidation']);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer secret-key')
                && $request->hasHeader('X-Module-Code', 'CLINICAL_ORCHESTRATOR')
                && $request->hasHeader('X-Request-ID');
        });
    }
}
