<?php

namespace Tests\Feature\Inventory;

use App\Livewire\Inventory\AiAdvicePanel;
use App\Models\Business;
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

    private function hospitalUser(): User
    {
        $business = Business::query()->where('id', '!=', 1)->first();
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
