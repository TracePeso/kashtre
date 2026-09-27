<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryAiAdviceLog;
use App\Services\AiGateway\CapabilityInvokeClient;
use App\Services\Inventory\InventoryAiAdvisor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InventoryAiAdvisorTest extends TestCase
{
    use DatabaseTransactions;
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ai_gateway.url' => 'https://ai.kashtre.com',
            'services.ai_gateway.inventory.token' => 'inventory-token',
            'services.ai_gateway.inventory.module_code' => 'INVENTORY_ORCHESTRATOR',
            'services.ai_gateway.tenant_id' => '11111111-1111-4111-8111-111111111111',
            'services.ai_gateway.timeout' => 90,
        ]);
    }

    public function test_invoke_posts_to_the_live_gateway_with_inventory_identity(): void
    {
        Http::fake([
            'https://ai.kashtre.com/api/ai/v1/capabilities/STOCKOUT_RISK:invoke' => Http::response([
                'requestId' => 'req-1',
                'status' => 'REQUIRES_REVIEW',
                'result' => ['risks' => ['Paracetamol may run out in 4 days'], 'warnings' => []],
                'warnings' => [],
                'requiresHumanReview' => true,
                'error' => null,
            ], 200),
        ]);

        $result = app(CapabilityInvokeClient::class)->invoke(
            'STOCKOUT_RISK',
            ['input' => ['measure' => 'Stockout risk', 'grain' => 'WEEK', 'horizon' => 'P4W', 'history' => [
                ['period' => '2026-W34', 'value' => 10, 'missing' => false],
            ]]],
            'inventory',
        );

        $this->assertTrue($result['ok']);
        $this->assertSame('Paracetamol may run out in 4 days', $result['result']['risks'][0]);
        $this->assertTrue($result['requiresHumanReview']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://ai.kashtre.com/api/ai/v1/capabilities/STOCKOUT_RISK:invoke'
                && $request->hasHeader('Authorization', 'Bearer inventory-token')
                && $request->hasHeader('X-Module-Code', 'INVENTORY_ORCHESTRATOR')
                && $request->hasHeader('X-Tenant-ID', '11111111-1111-4111-8111-111111111111')
                && $request->hasHeader('X-Request-ID')
                && $request['purposeOfUse'] === 'REPLENISHMENT_PLANNING'
                && $request['dataClassification'] === 'CONFIDENTIAL'
                && $request['contractVersion'] === '1.0.0'
                && $request['responseMode'] === 'SYNC'
                && $request['input']['measure'] === 'Stockout risk';
        });
    }

    public function test_invoke_explains_missing_tenant_context(): void
    {
        Http::fake([
            'https://ai.kashtre.com/*' => Http::response([
                'error' => [
                    'code' => 'CALLER_CONTEXT_REQUIRED',
                    'message' => 'X-Tenant-ID is required when this app can assume more than one business.',
                ],
            ], 400),
        ]);

        $result = app(CapabilityInvokeClient::class)->invoke('STOCKOUT_RISK', ['measure' => 'x'], 'inventory');

        $this->assertFalse($result['ok']);
        $this->assertSame('CALLER_CONTEXT_REQUIRED', $result['errorCode']);
        $this->assertStringContainsString('AI_GATEWAY_TENANT_ID', (string) $result['error']);
    }

    public function test_invoke_explains_a_queued_forecast(): void
    {
        Http::fake([
            'https://ai.kashtre.com/*' => Http::response([
                'requestId' => 'req-queued',
                'status' => 'QUEUED',
                'result' => null,
                'warnings' => [],
                'requiresHumanReview' => true,
            ], 202),
        ]);

        $result = app(CapabilityInvokeClient::class)->invoke('DEMAND_FORECAST', ['measure' => 'x'], 'inventory');

        $this->assertFalse($result['ok']);
        $this->assertSame('QUEUED', $result['errorCode']);
        $this->assertStringContainsString('still working', (string) $result['error']);
    }

    public function test_invoke_does_not_call_the_gateway_without_a_token(): void
    {
        config(['services.ai_gateway.inventory.token' => '', 'services.ai_gateway.token' => '', 'services.ai_gateway.api_key' => '']);
        Http::fake();

        $result = app(CapabilityInvokeClient::class)->invoke('STOCKOUT_RISK', ['input' => ['measure' => 'x']], 'inventory');

        $this->assertFalse($result['ok']);
        $this->assertFalse($result['available']);
        $this->assertStringContainsString('AI_GATEWAY_INVENTORY_TOKEN', (string) $result['error']);
        Http::assertNothingSent();
    }

    public function test_invoke_surfaces_gateway_error_details(): void
    {
        Http::fake([
            'https://ai.kashtre.com/*' => Http::response([
                'error' => [
                    'code' => 'RESPONSE_SCHEMA_INVALID',
                    'message' => 'Payload failed schema validation.',
                    'details' => ['/' => ['required']],
                ],
                'requestId' => 'req-bad',
            ], 502),
        ]);

        $result = app(CapabilityInvokeClient::class)->invoke('DEMAND_FORECAST', ['input' => ['measure' => 'x']], 'inventory');

        $this->assertFalse($result['ok']);
        $this->assertSame('RESPONSE_SCHEMA_INVALID', $result['errorCode']);
        $this->assertSame('Payload failed schema validation.', $result['error']);
        $this->assertSame('req-bad', $result['requestId']);
    }

    public function test_empty_url_still_points_at_the_live_gateway(): void
    {
        config(['services.ai_gateway.url' => '']);

        $this->assertSame('https://ai.kashtre.com', app(CapabilityInvokeClient::class)->url());
    }

    public function test_advisor_does_not_call_ai_without_three_weeks_of_history(): void
    {
        Http::fake();

        $advisor = \Mockery::mock(InventoryAiAdvisor::class, [app(CapabilityInvokeClient::class)])
            ->makePartial();
        $advisor->shouldReceive('weeklyHistory')->andReturn([
            ['period' => '2026-W34', 'value' => 4, 'missing' => false],
            ['period' => '2026-W35', 'value' => 5, 'missing' => false],
        ]);
        $advisor->shouldReceive('stockSnapshot')->andReturn([]);

        $advice = $advisor->advise('stockout', 4);

        $this->assertFalse($advice['ok']);
        $this->assertSame('STOCKOUT_RISK', $advice['capability']);
        $this->assertStringContainsString('three weeks', (string) $advice['error']);
        Http::assertNothingSent();
    }

    public function test_advisor_sends_history_and_keeps_the_result_advisory(): void
    {
        Http::fake([
            'https://ai.kashtre.com/api/ai/v1/capabilities/DEMAND_FORECAST:invoke' => Http::response([
                'requestId' => 'req-forecast',
                'status' => 'REQUIRES_REVIEW',
                'result' => [
                    'series' => [
                        ['period' => '2026-W38', 'central' => 12.5, 'lower' => 8, 'upper' => 16],
                    ],
                    'assumptions' => ['Peak period next month'],
                    'warnings' => [],
                    'requiresHumanReview' => true,
                ],
                'warnings' => [],
                'requiresHumanReview' => true,
                'error' => null,
            ], 200),
        ]);

        $advisor = $this->partialAdvisorWithHistory();
        $advice = $advisor->advise('demand', 4);

        $this->assertTrue($advice['ok']);
        $this->assertTrue($advice['requiresHumanReview']);
        $this->assertSame('DEMAND_FORECAST', $advice['capability']);
        $this->assertContains('2026-W38: 12.50 (8.00–16.00)', $advice['lines']);
        $this->assertContains('Assumption: Peak period next month', $advice['lines']);
        $this->assertContains('Peak period next month', $advice['assumptions']);
        $this->assertSame('WEEK', $advice['sent']['input']['grain']);
        $this->assertDatabaseHas('inventory_ai_advice_logs', [
            'capability' => 'DEMAND_FORECAST',
            'ok' => 1,
            'business_id' => 4,
        ]);
        $this->assertSame(
            'Peak period next month',
            InventoryAiAdviceLog::query()->latest('id')->value('response_payload')['result']['assumptions'][0] ?? null
        );
        $this->assertStringContainsString('Draft demand forecast for the next four weeks', (string) $advice['summary']);
        $this->assertNotEmpty($advice['series']);
        $this->assertSame('2026-W38', $advice['series'][0]['period']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'DEMAND_FORECAST:invoke')
                && $request['purposeOfUse'] === 'REPLENISHMENT_PLANNING'
                && $request['input']['grain'] === 'WEEK'
                && $request['input']['horizon'] === 'P4W'
                && isset($request['input']['timezone'])
                && count($request['input']['history']) >= 3;
        });
    }

    public function test_fill_weekly_window_keeps_gaps_as_missing(): void
    {
        $advisor = app(InventoryAiAdvisor::class);
        $end = \Carbon\Carbon::parse('2026-09-21')->startOfWeek(\Carbon\Carbon::MONDAY);

        $history = $advisor->fillWeeklyWindow([
            '2026-W36' => 4.0,
            '2026-W38' => 9.0,
        ], $end, 4);

        $this->assertCount(4, $history);
        $this->assertSame('2026-W36', $history[0]['period']);
        $this->assertSame(4.0, $history[0]['value']);
        $this->assertFalse($history[0]['missing']);
        $this->assertSame('2026-W37', $history[1]['period']);
        $this->assertTrue($history[1]['missing']);
        $this->assertSame('2026-W38', $history[2]['period']);
        $this->assertSame(9.0, $history[2]['value']);
        $this->assertSame('2026-W39', $history[3]['period']);
        $this->assertTrue($history[3]['missing']);
    }

    public function test_ask_before_ordering_uses_agent_run(): void
    {
        Http::fake([
            'https://ai.kashtre.com/api/ai/v1/capabilities/AGENT_RUN:invoke' => Http::response([
                'requestId' => 'req-ask',
                'status' => 'REQUIRES_REVIEW',
                'result' => [
                    'summary' => 'Check inbound LPOs and sister-store stock before ordering.',
                    'toolCalls' => [],
                    'requiresHumanReview' => true,
                ],
                'warnings' => [],
                'requiresHumanReview' => true,
                'error' => null,
            ], 200),
        ]);

        $advisor = $this->partialAdvisorWithHistory();
        $advice = $advisor->advise('ask', 4, null, null, 'Paracetamol is running low.');

        $this->assertTrue($advice['ok']);
        $this->assertSame('AGENT_RUN', $advice['capability']);
        $this->assertSame('Check inbound LPOs and sister-store stock before ordering.', $advice['summary']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'AGENT_RUN:invoke')
                && $request['purposeOfUse'] === 'REPLENISHMENT_PLANNING'
                && $request['input']['profileCode'] === 'DEFAULT'
                && str_contains((string) $request['input']['goal'], 'Paracetamol is running low.');
        });
    }

    private function partialAdvisorWithHistory(): InventoryAiAdvisor
    {
        $advisor = \Mockery::mock(InventoryAiAdvisor::class, [app(CapabilityInvokeClient::class)])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $advisor->shouldReceive('weeklyHistory')->andReturn([
            ['period' => '2026-W32', 'value' => 4, 'missing' => false],
            ['period' => '2026-W33', 'value' => 6, 'missing' => false],
            ['period' => '2026-W34', 'value' => 5, 'missing' => false],
        ]);
        $advisor->shouldReceive('stockSnapshot')->andReturn([
            ['name' => 'Paracetamol', 'code' => 'PARA', 'on_hand' => 12, 'ma_15' => 2.5],
        ]);

        return $advisor;
    }
}
