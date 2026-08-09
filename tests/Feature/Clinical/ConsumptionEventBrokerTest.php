<?php

namespace Tests\Feature\Clinical;

use App\Models\ClinicalBed;
use App\Models\ClinicalBillingEvent;
use App\Models\ClinicalConsumptionEvent;
use App\Models\ClinicalConsumptionException;
use App\Models\ClinicalWard;
use App\Models\InventoryDailyConsumption;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Models\Store;
use App\Models\User;
use App\Services\Clinical\ConsumptionEventBroker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ConsumptionEventBrokerTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = [null, 'clinical'];

    private function activateInventoryModule(): void
    {
        InventoryModuleConfig::firstOrCreate(['business_id' => 1], ['is_active' => true]);
    }

    private function storeWithStock(float $quantity = 100): Store
    {
        $store = Store::create([
            'business_id' => 1,
            'name' => 'Ward Store '.uniqid(),
        ]);

        $item = $this->item();

        InventoryStockLevel::create([
            'business_id' => 1,
            'store_id' => $store->id,
            'item_id' => $item->id,
            'quantity_suom' => $quantity,
        ]);

        return $store;
    }

    private function item(): Item
    {
        return Item::firstOrCreate(
            ['business_id' => 1, 'code' => 'PARA500'],
            ['name' => 'Paracetamol 500mg', 'type' => 'good', 'default_price' => 500]
        );
    }

    private function user(): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => 'consumption-test-'.uniqid().'@example.test',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'email_verified_at' => now(),
            'business_id' => 1,
        ]);
    }

    public function test_medication_administered_depletes_stock_without_triggering_billing(): void
    {
        $this->activateInventoryModule();
        $store = $this->storeWithStock();
        $user = $this->user();
        $user->update(['default_store_id' => $store->id]);

        $response = app(ConsumptionEventBroker::class)->emitConsumptionFact([
            'business_id' => 1,
            'branch_id' => null,
            'client_id' => 'CLIENT-CONS-1',
            'visit_id' => null,
            'ward_id' => null,
            'item_code' => 'PARA500',
            'quantity' => 2,
            'fact_token' => ClinicalConsumptionEvent::TOKEN_MEDICATION_ADMINISTERED,
            'usage_context' => 'PATIENT',
        ], $user->id);

        $this->assertSame('RECONCILED', $response['status']);
        $this->assertSame(ClinicalConsumptionEvent::SCENARIO_A_APPROVED_POOL, $response['reconciliation_scenario']);
        $this->assertTrue($response['approved_pool_reduced']);
        $this->assertFalse($response['billing_triggered']);
        $this->assertNotEmpty($response['audit_node_hash']);

        $stock = InventoryStockLevel::where('store_id', $store->id)->where('item_id', $this->item()->id)->value('quantity_suom');
        $this->assertEqualsWithDelta(98.0, (float) $stock, 0.001);

        $daily = InventoryDailyConsumption::where('store_id', $store->id)->where('source', InventoryDailyConsumption::SOURCE_CLINICAL)->first();
        $this->assertNotNull($daily);

        $this->assertSame(0, ClinicalBillingEvent::where('client_id', 'CLIENT-CONS-1')->count());
    }

    public function test_non_approved_floor_stock_usage_triggers_a_billing_event(): void
    {
        $this->activateInventoryModule();
        $store = $this->storeWithStock();
        $user = $this->user();
        $user->update(['default_store_id' => $store->id]);

        $response = app(ConsumptionEventBroker::class)->emitConsumptionFact([
            'business_id' => 1,
            'branch_id' => null,
            'client_id' => 'CLIENT-CONS-2',
            'visit_id' => null,
            'ward_id' => null,
            'item_code' => 'PARA500',
            'quantity' => 1,
            'fact_token' => ClinicalConsumptionEvent::TOKEN_NON_APPROVED_FLOOR_STOCK_USAGE,
            'usage_context' => 'PATIENT',
        ], $user->id);

        $this->assertSame(ClinicalConsumptionEvent::SCENARIO_B_NON_APPROVED_FLOOR_STOCK, $response['reconciliation_scenario']);
        $this->assertTrue($response['billing_triggered']);
        $this->assertFalse($response['approved_pool_reduced']);

        $billing = ClinicalBillingEvent::where('client_id', 'CLIENT-CONS-2')->first();
        $this->assertNotNull($billing);
        $this->assertEqualsWithDelta(500.0, (float) $billing->amount, 0.01); // 500 default_price * 1
    }

    public function test_crash_cart_consumption_triggers_billing(): void
    {
        $this->activateInventoryModule();
        $store = $this->storeWithStock();
        $user = $this->user();
        $user->update(['default_store_id' => $store->id]);

        $response = app(ConsumptionEventBroker::class)->emitConsumptionFact([
            'business_id' => 1,
            'branch_id' => null,
            'client_id' => 'CLIENT-CONS-3',
            'visit_id' => null,
            'ward_id' => null,
            'item_code' => 'PARA500',
            'quantity' => 1,
            'fact_token' => ClinicalConsumptionEvent::TOKEN_CRASH_CART_CONSUMPTION,
            'usage_context' => 'CRASH_CART',
        ], $user->id);

        $this->assertSame(ClinicalConsumptionEvent::SCENARIO_D_CRASH_CART, $response['reconciliation_scenario']);
        $this->assertTrue($response['billing_triggered']);
    }

    public function test_administrative_usage_does_not_trigger_billing(): void
    {
        $this->activateInventoryModule();
        $store = $this->storeWithStock();
        $user = $this->user();
        $user->update(['default_store_id' => $store->id]);

        $response = app(ConsumptionEventBroker::class)->emitConsumptionFact([
            'business_id' => 1,
            'branch_id' => null,
            'client_id' => 'CLIENT-CONS-4',
            'visit_id' => null,
            'ward_id' => null,
            'item_code' => 'PARA500',
            'quantity' => 1,
            'fact_token' => ClinicalConsumptionEvent::TOKEN_MEDICATION_ADMINISTERED,
            'usage_context' => 'ADMINISTRATIVE',
        ], $user->id);

        $this->assertSame(ClinicalConsumptionEvent::SCENARIO_C_ADMINISTRATIVE, $response['reconciliation_scenario']);
        $this->assertFalse($response['billing_triggered']);
    }

    public function test_medication_wastage_reduces_stock_without_billing(): void
    {
        $this->activateInventoryModule();
        $store = $this->storeWithStock();
        $user = $this->user();
        $user->update(['default_store_id' => $store->id]);

        $response = app(ConsumptionEventBroker::class)->emitConsumptionFact([
            'business_id' => 1,
            'branch_id' => null,
            'client_id' => 'CLIENT-CONS-5',
            'visit_id' => null,
            'ward_id' => null,
            'item_code' => 'PARA500',
            'quantity' => 1,
            'fact_token' => ClinicalConsumptionEvent::TOKEN_MEDICATION_WASTED,
            'usage_context' => 'WASTAGE_OPERATIONAL',
        ], $user->id);

        $this->assertSame(ClinicalConsumptionEvent::SCENARIO_WASTAGE, $response['reconciliation_scenario']);
        $this->assertFalse($response['billing_triggered']);
    }

    public function test_unknown_item_code_creates_a_consumption_exception(): void
    {
        $this->activateInventoryModule();
        $user = $this->user();

        $response = app(ConsumptionEventBroker::class)->emitConsumptionFact([
            'business_id' => 1,
            'branch_id' => null,
            'client_id' => 'CLIENT-CONS-6',
            'visit_id' => null,
            'ward_id' => null,
            'item_code' => 'NOT_A_REAL_SKU',
            'quantity' => 1,
            'fact_token' => ClinicalConsumptionEvent::TOKEN_MEDICATION_ADMINISTERED,
            'usage_context' => 'PATIENT',
        ], $user->id);

        $this->assertSame('EXCEPTION', $response['status']);
        $this->assertSame(1, ClinicalConsumptionException::where('client_id', 'CLIENT-CONS-6')->count());
    }

    public function test_zero_stock_creates_a_consumption_exception_without_calling_inventory(): void
    {
        $this->activateInventoryModule();
        $store = Store::create(['business_id' => 1, 'name' => 'Empty Store '.uniqid()]);
        $item = $this->item();
        InventoryStockLevel::create(['business_id' => 1, 'store_id' => $store->id, 'item_id' => $item->id, 'quantity_suom' => 0]);

        $user = $this->user();
        $user->update(['default_store_id' => $store->id]);

        $response = app(ConsumptionEventBroker::class)->emitConsumptionFact([
            'business_id' => 1,
            'branch_id' => null,
            'client_id' => 'CLIENT-CONS-7',
            'visit_id' => null,
            'ward_id' => null,
            'item_code' => 'PARA500',
            'quantity' => 1,
            'fact_token' => ClinicalConsumptionEvent::TOKEN_MEDICATION_ADMINISTERED,
            'usage_context' => 'PATIENT',
        ], $user->id);

        $this->assertSame('EXCEPTION', $response['status']);
    }

    public function test_ward_mapped_store_is_preferred_over_the_users_default_store(): void
    {
        $this->activateInventoryModule();
        $wardStore = $this->storeWithStock(50);
        $defaultStore = $this->storeWithStock(999);

        $ward = ClinicalWard::create([
            'business_id' => 1,
            'ward_code' => 'CONS-WARD',
            'ward_name' => 'Consumption Test Ward',
            'inventory_store_id' => $wardStore->id,
        ]);

        $bed = ClinicalBed::create(['ward_id' => $ward->id, 'bed_code' => 'BED-01']);
        $bed->update(['operational_state' => ClinicalBed::STATE_OCCUPIED, 'current_client_id' => 'CLIENT-CONS-8']);

        $user = $this->user();
        $user->update(['default_store_id' => $defaultStore->id]);

        app(ConsumptionEventBroker::class)->emitConsumptionFact([
            'business_id' => 1,
            'branch_id' => null,
            'client_id' => 'CLIENT-CONS-8',
            'visit_id' => null,
            'ward_id' => $ward->id,
            'item_code' => 'PARA500',
            'quantity' => 1,
            'fact_token' => ClinicalConsumptionEvent::TOKEN_MEDICATION_ADMINISTERED,
            'usage_context' => 'PATIENT',
        ], $user->id);

        $wardStoreStock = InventoryStockLevel::where('store_id', $wardStore->id)->where('item_id', $this->item()->id)->value('quantity_suom');
        $defaultStoreStock = InventoryStockLevel::where('store_id', $defaultStore->id)->where('item_id', $this->item()->id)->value('quantity_suom');

        $this->assertEqualsWithDelta(49.0, (float) $wardStoreStock, 0.001);
        $this->assertEqualsWithDelta(999.0, (float) $defaultStoreStock, 0.001);
    }
}
