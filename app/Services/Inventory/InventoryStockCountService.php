<?php

namespace App\Services\Inventory;

use App\Models\InventoryStockCount;
use App\Models\InventoryStockCountLine;
use App\Models\InventoryStockLevel;
use App\Models\InventoryStockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryStockCountService
{
    public function __construct(
        private readonly InventoryStockAnalyticsService $analytics
    ) {}

    public function generateReference(int $businessId): string
    {
        $prefix = 'SC-'.now()->format('Ymd');
        $count = InventoryStockCount::query()
            ->where('business_id', $businessId)
            ->where('reference', 'like', $prefix.'%')
            ->count();

        return $prefix.'-'.str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
    }

    public function createDraft(int $businessId, int $storeId, User $user, ?string $notes = null): InventoryStockCount
    {
        return DB::transaction(function () use ($businessId, $storeId, $user, $notes) {
            $count = InventoryStockCount::create([
                'business_id' => $businessId,
                'store_id' => $storeId,
                'reference' => $this->generateReference($businessId),
                'status' => InventoryStockCount::STATUS_DRAFT,
                'counted_at' => now(),
                'notes' => $notes,
                'created_by_user_id' => $user->id,
            ]);

            $levels = InventoryStockLevel::query()
                ->where('business_id', $businessId)
                ->where('store_id', $storeId)
                ->where('quantity_suom', '>', 0)
                ->get();

            foreach ($levels as $level) {
                InventoryStockCountLine::create([
                    'inventory_stock_count_id' => $count->id,
                    'item_id' => $level->item_id,
                    'system_quantity_suom' => $level->quantity_suom,
                    'physical_quantity_suom' => $level->physical_quantity_suom ?? $level->quantity_suom,
                    'damaged_quantity_suom' => $level->damaged_quantity_suom ?? 0,
                    'expired_quantity_suom' => $level->expired_quantity_suom ?? 0,
                ]);
            }

            return $count->load(['lines.item', 'store']);
        });
    }

    public function updateLine(
        InventoryStockCountLine $line,
        float $physicalQty,
        float $damagedQty,
        float $expiredQty = 0
    ): InventoryStockCountLine {
        if (! $line->stockCount->isDraft()) {
            throw ValidationException::withMessages([
                'status' => 'Only draft stock counts can be edited.',
            ]);
        }

        $line->update([
            'physical_quantity_suom' => max(0, $physicalQty),
            'damaged_quantity_suom' => max(0, $damagedQty),
            'expired_quantity_suom' => max(0, $expiredQty),
        ]);

        return $line->fresh('item');
    }

    public function finalize(InventoryStockCount $count, User $user): InventoryStockCount
    {
        if (! $count->isDraft()) {
            throw ValidationException::withMessages([
                'status' => 'This stock count has already been finalized.',
            ]);
        }

        return DB::transaction(function () use ($count, $user) {
            $count->load('lines');

            foreach ($count->lines as $line) {
                $stock = InventoryStockLevel::firstOrCreate(
                    [
                        'business_id' => $count->business_id,
                        'store_id' => $count->store_id,
                        'item_id' => $line->item_id,
                    ],
                    ['quantity_suom' => 0]
                );

                $balanceBefore = (float) $stock->quantity_suom;
                $physical = (float) $line->physical_quantity_suom;
                $variance = round($physical - $balanceBefore, 4);

                $stock->update([
                    'physical_quantity_suom' => $physical,
                    'physical_counted_at' => $count->counted_at ?? now(),
                    'damaged_quantity_suom' => (float) $line->damaged_quantity_suom,
                    'expired_quantity_suom' => (float) $line->expired_quantity_suom,
                ]);

                if ($variance != 0.0) {
                    $stock->update(['quantity_suom' => $physical]);

                    InventoryStockMovement::create([
                        'business_id' => $count->business_id,
                        'item_id' => $line->item_id,
                        'store_id' => $count->store_id,
                        'movement_type' => InventoryStockMovement::TYPE_STOCK_COUNT,
                        'quantity_delta' => $variance,
                        'balance_after' => $physical,
                        'reference_label' => $count->reference,
                        'recorded_by_user_id' => $user->id,
                        'occurred_at' => $count->counted_at ?? now(),
                    ]);
                }

                $this->analytics->recalculateForStockLevel($stock->fresh());
            }

            $count->update([
                'status' => InventoryStockCount::STATUS_FINALIZED,
                'finalized_by_user_id' => $user->id,
                'finalized_at' => now(),
            ]);

            return $count->fresh(['lines.item', 'store', 'finalizedBy']);
        });
    }
}
