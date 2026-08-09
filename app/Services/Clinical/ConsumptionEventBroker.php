<?php

namespace App\Services\Clinical;

use App\Contracts\ModuleDispatcher;
use App\Models\ClinicalBillingEvent;
use App\Models\ClinicalConsumptionEvent;
use App\Models\ClinicalConsumptionException;
use App\Models\ClinicalWard;
use App\Models\InventoryDailyConsumption;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Models\User;
use App\Services\Clinical\Facts\ConsumptionFactEmittedFact;

/**
 * Clinical-to-Inventory ICD's unified point-of-care consumption endpoint
 * (POST /api/v1/inventory/consumption/emit), scoped to Chunk 4: the
 * fact_tokens a clinician triggers directly (MEDICATION_ADMINISTERED,
 * MEDICATION_WASTED, NON_APPROVED_FLOOR_STOCK_USAGE,
 * CRASH_CART_CONSUMPTION). LAB_CONSUMPTION_FACT/RADIOLOGY_CONSUMPTION_FACT
 * aren't proxied here — Imaging already depletes its own stock directly
 * via RadiologyRecipeEngine (Chunk 3 didn't need to route that through
 * this broker), and LIMS gets the equivalent direct treatment in Chunk 7.
 *
 * Demand-ledger capture-intent and the ward tote handshake token flow
 * (separate ICD endpoints) are deferred — out of scope for "administering
 * an item decrements stock and flags excess usage for billing."
 */
class ConsumptionEventBroker
{
    public function __construct(private readonly ModuleDispatcher $dispatcher)
    {
    }

    /**
     * @param array{business_id: int, branch_id: ?int, client_id: string, visit_id: ?string, ward_id: ?int, item_code: string, quantity: float, fact_token: string, usage_context: string, notes?: ?string} $payload
     * @return array{status: string, reconciliation_scenario?: string, physical_stock_reduced?: bool, approved_pool_reduced?: bool, billing_triggered?: bool, audit_node_hash?: string, exception_reason?: string}
     */
    public function emitConsumptionFact(array $payload, int $userId): array
    {
        $businessId = $payload['business_id'];

        if (! InventoryModuleConfig::forBusiness($businessId)->active()->exists()) {
            return ['status' => 'INVENTORY_MODULE_INACTIVE'];
        }

        $item = Item::where('business_id', $businessId)->where('code', $payload['item_code'])->first();

        if (! $item) {
            return $this->reject($businessId, $payload['client_id'], $payload['item_code'], "No item found for code {$payload['item_code']}.");
        }

        $storeId = $this->resolveStore($businessId, $item->id, $userId, $payload['ward_id'] ?? null);

        if (! $storeId) {
            return $this->reject($businessId, $payload['client_id'], $payload['item_code'], "No inventory store could be resolved for {$item->name}.");
        }

        $available = InventoryStockLevel::where('business_id', $businessId)
            ->where('store_id', $storeId)
            ->where('item_id', $item->id)
            ->value('quantity_suom');

        if (! $available || $available <= 0) {
            return $this->reject($businessId, $payload['client_id'], $payload['item_code'], "Resolved store has no available stock of {$item->name}.");
        }

        $scenario = $this->determineScenario($payload['fact_token'], $payload['usage_context']);

        $this->dispatcher->dispatch(new ConsumptionFactEmittedFact(
            businessId: $businessId,
            storeId: $storeId,
            itemId: $item->id,
            quantity: (float) $payload['quantity'],
            recordedByUserId: $userId,
            source: InventoryDailyConsumption::SOURCE_CLINICAL,
            notes: $payload['notes'] ?? "Clinical: {$payload['fact_token']} for client {$payload['client_id']}",
        ));

        $event = ClinicalConsumptionEvent::create([
            'business_id' => $businessId,
            'branch_id' => $payload['branch_id'] ?? null,
            'client_id' => $payload['client_id'],
            'visit_id' => $payload['visit_id'] ?? null,
            'fact_token' => $payload['fact_token'],
            'usage_context' => $payload['usage_context'],
            'item_code' => $payload['item_code'],
            'quantity' => $payload['quantity'],
            'inventory_store_id' => $storeId,
            'reconciliation_scenario' => $scenario['scenario'],
            'physical_stock_reduced' => true,
            'approved_pool_reduced' => $scenario['approved_pool_reduced'],
            'billing_triggered' => $scenario['billing_triggered'],
            'recorded_by_user_id' => $userId,
            'occurred_at' => now(),
        ]);

        if ($scenario['billing_triggered']) {
            ClinicalBillingEvent::create([
                'business_id' => $businessId,
                'branch_id' => $payload['branch_id'] ?? null,
                'client_id' => $payload['client_id'],
                'visit_id' => $payload['visit_id'] ?? null,
                'consumption_event_id' => $event->id,
                'reason' => $scenario['scenario'],
                'item_code' => $payload['item_code'],
                'quantity' => $payload['quantity'],
                'amount' => $item->default_price ? round($item->default_price * $payload['quantity'], 2) : null,
            ]);
        }

        return [
            'status' => 'RECONCILED',
            'reconciliation_scenario' => $scenario['scenario'],
            'physical_stock_reduced' => true,
            'approved_pool_reduced' => $scenario['approved_pool_reduced'],
            'billing_triggered' => $scenario['billing_triggered'],
            'audit_node_hash' => hash('sha256', $event->id.'|'.$businessId.'|'.$payload['client_id'].'|'.$payload['item_code'].'|'.$payload['quantity'].'|'.$event->occurred_at),
        ];
    }

    /**
     * Priority mirrors RadiologyRecipeEngine::resolveStoreId(): the
     * caller's own space mapping first (here, the ward's inventory_store_id
     * — Chunk 1's clinical_wards column, built for exactly this), then the
     * acting user's default store, then whichever store holds the most
     * stock of the item.
     */
    private function resolveStore(int $businessId, int $itemId, int $userId, ?int $wardId): ?int
    {
        if ($wardId) {
            $storeId = ClinicalWard::where('business_id', $businessId)->whereKey($wardId)->value('inventory_store_id');

            if ($storeId) {
                return (int) $storeId;
            }
        }

        $defaultStoreId = User::whereKey($userId)->value('default_store_id');

        if ($defaultStoreId) {
            return (int) $defaultStoreId;
        }

        $fallback = InventoryStockLevel::where('business_id', $businessId)
            ->where('item_id', $itemId)
            ->where('quantity_suom', '>', 0)
            ->orderByDesc('quantity_suom')
            ->value('store_id');

        return $fallback ? (int) $fallback : null;
    }

    /**
     * @return array{scenario: string, approved_pool_reduced: bool, billing_triggered: bool}
     */
    private function determineScenario(string $factToken, string $usageContext): array
    {
        return match (true) {
            // Reagent cost is bundled into the lab test's own fee (billed
            // by the Main Module separately) — treated like approved-pool
            // patient-care consumption, not excess/postpaid.
            $factToken === ClinicalConsumptionEvent::TOKEN_LAB_CONSUMPTION_FACT => [
                'scenario' => ClinicalConsumptionEvent::SCENARIO_A_APPROVED_POOL,
                'approved_pool_reduced' => true,
                'billing_triggered' => false,
            ],
            $factToken === ClinicalConsumptionEvent::TOKEN_CRASH_CART_CONSUMPTION => [
                'scenario' => ClinicalConsumptionEvent::SCENARIO_D_CRASH_CART,
                'approved_pool_reduced' => false,
                'billing_triggered' => true,
            ],
            $factToken === ClinicalConsumptionEvent::TOKEN_NON_APPROVED_FLOOR_STOCK_USAGE => [
                'scenario' => ClinicalConsumptionEvent::SCENARIO_B_NON_APPROVED_FLOOR_STOCK,
                'approved_pool_reduced' => false,
                'billing_triggered' => true,
            ],
            $factToken === ClinicalConsumptionEvent::TOKEN_MEDICATION_WASTED => [
                'scenario' => ClinicalConsumptionEvent::SCENARIO_WASTAGE,
                'approved_pool_reduced' => false,
                'billing_triggered' => false,
            ],
            $usageContext === 'ADMINISTRATIVE' => [
                'scenario' => ClinicalConsumptionEvent::SCENARIO_C_ADMINISTRATIVE,
                'approved_pool_reduced' => false,
                'billing_triggered' => false,
            ],
            default => [
                'scenario' => ClinicalConsumptionEvent::SCENARIO_A_APPROVED_POOL,
                'approved_pool_reduced' => true,
                'billing_triggered' => false,
            ],
        };
    }

    private function reject(int $businessId, string $clientId, string $itemCode, string $reason): array
    {
        ClinicalConsumptionException::create([
            'business_id' => $businessId,
            'client_id' => $clientId,
            'item_code' => $itemCode,
            'exception_reason' => $reason,
        ]);

        return ['status' => 'EXCEPTION', 'exception_reason' => $reason];
    }
}
