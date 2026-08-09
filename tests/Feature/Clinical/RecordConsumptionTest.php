<?php

namespace Tests\Feature\Clinical;

use App\Livewire\Clinical\RecordConsumption;
use App\Models\ClinicalConsumptionEvent;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class RecordConsumptionTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = [null, 'clinical'];

    public function test_a_user_without_permission_is_blocked(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'record-consumption-test-'.uniqid().'@example.test',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'email_verified_at' => now(),
            'business_id' => 1,
            'permissions' => [],
        ]);

        Livewire::actingAs($user)
            ->test(RecordConsumption::class, ['clientId' => 'CLIENT-001'])
            ->assertForbidden();
    }

    public function test_recording_consumption_depletes_stock_and_shows_a_result_message(): void
    {
        InventoryModuleConfig::firstOrCreate(['business_id' => 1], ['is_active' => true]);
        $store = Store::create(['business_id' => 1, 'name' => 'RC Store '.uniqid()]);
        $item = Item::create(['business_id' => 1, 'code' => 'RC_ITEM', 'name' => 'Test Consumable', 'type' => 'good', 'default_price' => 100]);
        InventoryStockLevel::create(['business_id' => 1, 'store_id' => $store->id, 'item_id' => $item->id, 'quantity_suom' => 20]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'record-consumption-test-'.uniqid().'@example.test',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'email_verified_at' => now(),
            'business_id' => 1,
            'default_store_id' => $store->id,
            'permissions' => ['Add Clinical Observations'],
        ]);

        Livewire::actingAs($user)
            ->test(RecordConsumption::class, ['clientId' => 'CLIENT-001'])
            ->set('itemCode', 'RC_ITEM')
            ->set('quantity', 3)
            ->set('factToken', ClinicalConsumptionEvent::TOKEN_MEDICATION_ADMINISTERED)
            ->set('usageContext', 'PATIENT')
            ->call('record')
            ->assertSee('Recorded (SCENARIO_A_APPROVED_POOL)');

        $stock = InventoryStockLevel::where('store_id', $store->id)->where('item_id', $item->id)->value('quantity_suom');
        $this->assertEqualsWithDelta(17.0, (float) $stock, 0.001);
    }
}
