<?php

namespace App\Services\Inventory;

use App\Models\InventoryDailyConsumption;
use App\Models\InventoryFulfillmentLine;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryStockLevel;
use App\Models\PatientApprovedPoolLine;
use App\Models\ServiceDeliveryQueue;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryFulfillmentDispenseService
{
    public function __construct(
        private readonly InventoryStockAnalyticsService $analytics,
        private readonly InventoryMainModuleSyncService $mainModule,
        private readonly InventoryForensicAuditService $audit,
    ) {}

    /**
     * End Store dispense complete (SRD §4 / §8.1):
     * stock ↓ at stamped End Store, Main Module goods → Completed.
     * Approved Pool ↑ only for Batch & Stage lines (strategy-derived).
     * Outpatient (discrete / immediate) completes the ticket with no pool balance.
     *
     * @param  array{batch_lot?: string|null, serials?: list<string>|null}|null  $traceability
     */
    public function complete(
        InventoryFulfillmentLine $line,
        User $user,
        ?float $quantity = null,
        ?array $traceability = null
    ): InventoryFulfillmentLine
    {
        $line->loadMissing(['store', 'item', 'client', 'invoice']);

        if (! $line->isOpen()) {
            throw ValidationException::withMessages([
                'status' => 'This fulfillment line is already '.$line->statusLabel().'.',
            ]);
        }

        if (! $line->store || ! $line->store->isEndStore()) {
            throw ValidationException::withMessages([
                'store_id' => 'Dispense is only allowed from an End Store.',
            ]);
        }

        $config = InventoryModuleConfig::query()
            ->where('business_id', $line->business_id)
            ->first();

        if ($config?->enable_batch_lot_tracking && blank($traceability['batch_lot'] ?? null)) {
            throw ValidationException::withMessages([
                'batch_lot' => 'Batch / lot is required for this organisation.',
            ]);
        }

        $already = (float) $line->quantity_fulfilled;
        $remaining = max(0, (float) $line->quantity - $already);
        // Default to remaining open qty (not original qty) so re-opens / partials work.
        $dispenseQty = $quantity !== null ? (float) $quantity : $remaining;

        if ($dispenseQty <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Dispense quantity must be greater than zero.',
            ]);
        }

        if ($dispenseQty > $remaining + 0.0001) {
            throw ValidationException::withMessages([
                'quantity' => 'Cannot dispense more than the remaining quantity ('.$remaining.').',
            ]);
        }

        if ($config?->enable_serial_number_tracking) {
            $serials = array_values(array_filter(array_map('strval', $traceability['serials'] ?? [])));
            $expected = (int) round($dispenseQty);
            // Whole-unit serial items: one unique serial per unit dispensed.
            if ($expected >= 1 && count($serials) !== $expected) {
                throw ValidationException::withMessages([
                    'serials' => 'Enter exactly '.$expected.' serial number(s) (one per unit dispensed).',
                ]);
            }
            if (count($serials) !== count(array_unique($serials))) {
                throw ValidationException::withMessages([
                    'serials' => 'Serial numbers must be unique for this dispense.',
                ]);
            }
        }

        $businessId = (int) $line->business_id;
        $storeId = (int) $line->store_id;
        $itemId = (int) $line->item_id;

        $onHand = (float) (InventoryStockLevel::query()
            ->where('business_id', $businessId)
            ->where('store_id', $storeId)
            ->where('item_id', $itemId)
            ->value('quantity_suom') ?? 0);

        if ($onHand + 0.0001 < $dispenseQty) {
            throw ValidationException::withMessages([
                'quantity' => 'Insufficient stock at '.$line->store->name.'. On hand: '
                    .number_format($onHand, 0).', needed: '.number_format($dispenseQty, 0)
                    .'. Transfer stock to this End Store first.',
            ]);
        }

        return DB::transaction(function () use ($line, $user, $dispenseQty, $businessId, $storeId, $itemId, $already, $onHand, $traceability) {
            $this->analytics->recordConsumption(
                $businessId,
                $storeId,
                $itemId,
                now()->toDateString(),
                $dispenseQty,
                InventoryDailyConsumption::SOURCE_SALE,
                (int) $user->id,
                'End Store dispense: '.($line->item_name ?? 'item').' ('.$line->invoice?->invoice_number.')',
                now(),
                null
            );

            $this->audit->record(
                $businessId,
                'DISPENSE',
                (int) $user->id,
                $storeId,
                $itemId,
                $onHand,
                max(0, $onHand - $dispenseQty),
                $line->client_id,
                ['fulfillment_line_id' => $line->id]
            );

            $newFulfilled = round($already + $dispenseQty, 4);
            $isFull = $newFulfilled + 0.0001 >= (float) $line->quantity;

            $line->quantity_fulfilled = $newFulfilled;
            $line->completed_by = $user->id;

            if ($isFull) {
                $line->status = InventoryFulfillmentLine::STATUS_COMPLETED;
                $line->completed_at = now();
            } else {
                $line->status = InventoryFulfillmentLine::STATUS_PARTIAL;
            }

            $meta = $line->metadata ?? [];
            $meta['dispensed_at'] = now()->toIso8601String();
            $meta['dispensed_by'] = $user->id;
            $meta['last_dispense_qty'] = $dispenseQty;
            $line->metadata = $meta;
            if (! empty($traceability['batch_lot'])) {
                $line->dispense_batch_lot = (string) $traceability['batch_lot'];
            }
            if (! empty($traceability['serials'])) {
                $line->dispense_serials = array_values(array_filter(array_map('strval', $traceability['serials'])));
            }
            $line->save();

            if ($line->client_id && $line->supportsApprovedPool()) {
                PatientApprovedPoolLine::create([
                    'business_id' => $businessId,
                    'client_id' => $line->client_id,
                    'item_id' => $itemId,
                    'source_fulfillment_line_id' => $line->id,
                    'invoice_id' => $line->invoice_id,
                    'quantity_original' => $dispenseQty,
                    'quantity_remaining' => $dispenseQty,
                ]);
            }

            $this->syncMainModuleCompleted($line, $user);
            $this->mainModule->enqueueFulfillmentCompleted($line->fresh(), $user);

            return $line->fresh(['store', 'item', 'client', 'invoice']);
        });
    }

    /**
     * Flip matching service-delivery / sale ticket to Completed without a second stock hit.
     * InventorySaleConsumptionService skips goods owned by the fulfillment queue.
     */
    protected function syncMainModuleCompleted(InventoryFulfillmentLine $line, User $user): void
    {
        if (! $line->invoice_id || ! $line->item_id) {
            return;
        }

        $queues = ServiceDeliveryQueue::query()
            ->where('invoice_id', $line->invoice_id)
            ->where('item_id', $line->item_id)
            ->when($line->client_id, fn ($q) => $q->where('client_id', $line->client_id))
            ->whereIn('status', ['pending', 'in_progress', 'partially_done'])
            ->get();

        foreach ($queues as $queue) {
            if (! $line->service_delivery_queue_id) {
                $line->forceFill(['service_delivery_queue_id' => $queue->id])->saveQuietly();
            }

            if ($line->status === InventoryFulfillmentLine::STATUS_COMPLETED) {
                $queue->markAsCompleted((int) $user->id);
            }
            // SRD §2.2: goods stay Pending until fully Completed — never partially_done.
        }
    }
}
