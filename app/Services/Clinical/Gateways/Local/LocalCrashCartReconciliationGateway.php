<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\ModuleDispatcher;
use App\Models\ClinicalBillingEvent;
use App\Models\ClinicalConsumptionEvent;
use App\Models\InventoryDailyConsumption;
use App\Models\Item;
use App\Models\Store;
use App\Services\Clinical\Facts\ConsumptionFactEmittedFact;
use App\Contracts\Clinical\CrashCartReconciliationGateway;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\CrashCartReconciliationRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CLINICAL_DRIVER=local: no separate clinical-record table for this — the
 * effect *is* the record (SRD v6.0 §13.2's whole point is one submission
 * that does everything, not a document that says it happened).
 *
 * Deliberately does not call ConsumptionEventBroker::emitConsumptionFact()
 * directly: that method re-*resolves* which store to decrement (ward
 * mapping, then the user's default store, then whichever store holds the
 * most stock) — right for the general floor-stock case, where nothing else
 * names a store, but wrong here, where the nurse just told this form
 * exactly which physical cart it was. Re-resolving could silently decrement
 * a different store than the one actually used. This replicates that
 * method's effects past its own resolution step, against the store id
 * already known.
 */
class LocalCrashCartReconciliationGateway implements CrashCartReconciliationGateway
{
    public function __construct(private readonly ModuleDispatcher $dispatcher)
    {
    }

    public function reconcile(
        ClinicalActor $actor,
        string $resuscitationEventId,
        string $patientId,
        ?string $visitId,
        string $crashCartStoreId,
        array $items,
        ?string $narrative = null,
    ): CrashCartReconciliationRecord {
        $store = Store::where('business_id', $actor->businessId)->findOrFail((int) $crashCartStoreId);

        if (! $store->isCrashCart()) {
            throw ValidationException::withMessages(['crash_cart_store_id' => 'This store is not marked as a crash cart.']);
        }

        DB::transaction(function () use ($actor, $resuscitationEventId, $patientId, $visitId, $store, $items, $narrative) {
            foreach ($items as $line) {
                $item = Item::where('business_id', $actor->businessId)->where('code', $line['inventory_sku'])->first();

                if (! $item) {
                    throw ValidationException::withMessages([
                        'items' => "No catalogue item matches [{$line['inventory_sku']}].",
                    ]);
                }

                $quantity = (float) $line['quantity_used'];

                $this->dispatcher->dispatch(new ConsumptionFactEmittedFact(
                    businessId: $actor->businessId,
                    storeId: $store->id,
                    itemId: $item->id,
                    quantity: $quantity,
                    recordedByUserId: $actor->userId,
                    source: InventoryDailyConsumption::SOURCE_CLINICAL,
                    notes: $narrative ?: "Crash cart reconciliation ({$resuscitationEventId}) for client {$patientId}",
                ));

                $event = ClinicalConsumptionEvent::create([
                    'business_id' => $actor->businessId,
                    'branch_id' => $actor->branchId,
                    'client_id' => $patientId,
                    'visit_id' => $visitId,
                    'fact_token' => ClinicalConsumptionEvent::TOKEN_CRASH_CART_CONSUMPTION,
                    'usage_context' => 'CRASH_CART',
                    'item_code' => $item->code,
                    'quantity' => $quantity,
                    'inventory_store_id' => $store->id,
                    'reconciliation_scenario' => ClinicalConsumptionEvent::SCENARIO_D_CRASH_CART,
                    'physical_stock_reduced' => true,
                    'approved_pool_reduced' => false,
                    'billing_triggered' => true,
                    'recorded_by_user_id' => $actor->userId,
                    'occurred_at' => now(),
                ]);

                ClinicalBillingEvent::create([
                    'business_id' => $actor->businessId,
                    'branch_id' => $actor->branchId,
                    'client_id' => $patientId,
                    'visit_id' => $visitId,
                    'consumption_event_id' => $event->id,
                    'reason' => ClinicalConsumptionEvent::SCENARIO_D_CRASH_CART,
                    'item_code' => $item->code,
                    'quantity' => $quantity,
                    'amount' => $item->default_price ? round($item->default_price * $quantity, 2) : null,
                ]);
            }
        });

        return new CrashCartReconciliationRecord(
            id: $resuscitationEventId,
            resuscitationEventId: $resuscitationEventId,
            patientId: $patientId,
            visitId: $visitId,
            crashCartStoreId: $crashCartStoreId,
            narrative: $narrative,
            items: $items,
            reconciledAt: now()->toIso8601String(),
        );
    }
}
