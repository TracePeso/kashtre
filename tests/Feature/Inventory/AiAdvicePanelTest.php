<?php

namespace Tests\Feature\Inventory;

use App\Http\Middleware\RequireTwoFactorForKashtre;
use App\Livewire\Inventory\AiAdviceBriefing;
use App\Livewire\Inventory\AiAdviceLogTable;
use App\Livewire\Inventory\AiAdvicePanel;
use App\Models\Business;
use App\Models\InventoryAiAdviceLog;
use App\Models\InventoryModuleConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AiAdvicePanelTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unconfigured_panel_asks_for_the_inventory_token(): void
    {
        config([
            'services.ai_gateway.inventory.token' => '',
            'services.ai_gateway.token' => '',
            'services.ai_gateway.api_key' => '',
        ]);

        $this->actingAs($this->hospitalUser());

        Livewire::test(AiAdvicePanel::class, ['useCase' => 'consumption'])
            ->assertSee('AI advice')
            ->assertSee('AI_GATEWAY_INVENTORY_TOKEN')
            ->assertDontSee('Ask before ordering');
    }

    public function test_configured_panel_shows_ask_and_history_readiness(): void
    {
        config([
            'services.ai_gateway.url' => 'https://ai.kashtre.com',
            'services.ai_gateway.inventory.token' => 'inventory-token',
            'services.ai_gateway.tenant_id' => '11111111-1111-4111-8111-111111111111',
        ]);

        $this->actingAs($this->hospitalUser());

        Livewire::test(AiAdvicePanel::class, ['useCase' => 'stockout', 'allowAsk' => true])
            ->assertSee('Check stockout risk')
            ->assertSee('Ask before ordering')
            ->assertSee('weeks have numbers');
    }

    public function test_check_keeps_advice_draft_only_when_history_is_thin(): void
    {
        config([
            'services.ai_gateway.inventory.token' => 'inventory-token',
            'services.ai_gateway.tenant_id' => '11111111-1111-4111-8111-111111111111',
        ]);
        Http::fake();

        $this->actingAs($this->hospitalUser());

        Livewire::test(AiAdvicePanel::class, [
            'useCase' => 'demand',
            'storeId' => 9_999_990,
            'itemId' => 9_999_990,
        ])
            ->call('check')
            ->assertSet('advice.ok', false)
            ->assertSee('three weeks');

        Http::assertNothingSent();
    }

    public function test_briefing_page_explains_history_and_units(): void
    {
        $this->actingAs($this->hospitalUser());

        Livewire::test(AiAdviceBriefing::class)
            ->assertSee('What you are looking at')
            ->assertSee('Last 12 weeks')
            ->assertSee('Sale units')
            ->assertSee('Next 4 weeks')
            ->assertSee('AI request log');
    }

    public function test_shared_log_table_renders_on_inventory_ai_surfaces(): void
    {
        $user = $this->hospitalUser();
        $this->actingAs($user);

        InventoryAiAdviceLog::query()->create([
            'business_id' => $user->business_id,
            'use_case' => 'consumption',
            'capability' => 'CONSUMPTION_FORECAST',
            'title' => 'Ask for a consumption forecast',
            'ok' => true,
            'summary' => 'Draft only',
            'request_payload' => ['input' => ['grain' => 'WEEK']],
            'response_payload' => ['ok' => true],
        ]);

        Livewire::test(AiAdviceLogTable::class)
            ->assertSee('AI request log')
            ->assertSee('Feedback')
            ->assertSee('View')
            ->assertSee('Ask for a consumption forecast');
    }

    public function test_log_view_page_shows_sent_and_returned_payloads(): void
    {
        $user = $this->hospitalUser();
        if (! InventoryModuleConfig::query()->where('business_id', $user->business_id)->where('is_active', true)->exists()) {
            $this->markTestSkipped('Inventory must be enabled for this hospital.');
        }

        $this->withoutMiddleware(RequireTwoFactorForKashtre::class);
        $this->actingAs($user);

        $log = InventoryAiAdviceLog::query()->create([
            'business_id' => $user->business_id,
            'use_case' => 'consumption',
            'capability' => 'CONSUMPTION_FORECAST',
            'title' => 'Ask for a consumption forecast',
            'ok' => true,
            'summary' => 'Draft consumption forecast for the next four weeks.',
            'request_payload' => ['capability' => 'CONSUMPTION_FORECAST', 'input' => ['grain' => 'WEEK']],
            'response_payload' => [
                'ok' => true,
                'result' => [
                    'series' => [
                        ['period' => '2026-W41', 'central' => 12, 'lower' => 8, 'upper' => 16],
                    ],
                    'assumptions' => ['History stays stable'],
                ],
            ],
        ]);

        $this->get(route('inventory.ai.logs.show', $log))
            ->assertOk()
            ->assertSee('What we sent')
            ->assertSee('What AI returned')
            ->assertSee('2026-W41')
            ->assertSee('History stays stable')
            ->assertSee('CONSUMPTION_FORECAST');

        $otherBusinessId = Business::query()->whereKeyNot($user->business_id)->value('id');
        if (! $otherBusinessId) {
            return;
        }

        $foreign = InventoryAiAdviceLog::query()->create([
            'business_id' => $otherBusinessId,
            'use_case' => 'demand',
            'capability' => 'DEMAND_FORECAST',
            'title' => 'Other organisation ask',
            'ok' => true,
            'summary' => 'Should stay hidden',
            'request_payload' => ['input' => []],
            'response_payload' => ['ok' => true],
        ]);

        $this->get(route('inventory.ai.logs.show', $foreign))->assertNotFound();
    }

    private function hospitalUser(): User
    {
        $businessId = InventoryModuleConfig::query()
            ->where('is_active', true)
            ->where('business_id', '!=', 1)
            ->value('business_id');
        $business = $businessId ? Business::query()->find($businessId) : Business::query()->where('id', '!=', 1)->first();
        if (! $business) {
            $this->markTestSkipped('A hospital business is required.');
        }

        return User::factory()->create([
            'business_id' => $business->id,
            'status' => 'active',
            'employment_type' => 'employee',
            'permissions' => [],
        ]);
    }
}
