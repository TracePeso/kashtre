<?php

namespace App\Services\Inventory;

use App\Models\InventoryStockLevel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryExpiredEscrowService
{
    /**
     * Move quantity from active on-hand into expired escrow (expired_quantity_suom).
     * Escrow is not dispensable because on-hand (quantity_suom) is reduced.
     */
    public function moveToEscrow(
        int $businessId,
        int $storeId,
        int $itemId,
        float $quantity,
        User $user,
        ?string $notes = null
    ): InventoryStockLevel {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be greater than zero.']);
        }

        return DB::transaction(function () use ($businessId, $storeId, $itemId, $quantity, $user, $notes) {
            $level = InventoryStockLevel::query()
                ->where('business_id', $businessId)
                ->where('store_id', $storeId)
                ->where('item_id', $itemId)
                ->lockForUpdate()
                ->first();

            if (! $level || (float) $level->quantity_suom + 0.0001 < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Insufficient active stock to move to expired escrow.',
                ]);
            }

            $old = (float) $level->quantity_suom;
            $level->applyOnHandBalance($old - $quantity);
            $level->expired_quantity_suom = round((float) ($level->expired_quantity_suom ?? 0) + $quantity, 4);
            $level->stock_zone = 'active';
            $level->save();

            app(InventoryForensicAuditService::class)->record(
                $businessId,
                'WASTAGE_EXPIRED_ESCROW',
                $user->id,
                $storeId,
                $itemId,
                $old,
                (float) $level->quantity_suom,
                null,
                ['escrow_qty' => $quantity, 'notes' => $notes]
            );

            return $level->fresh();
        });
    }

    public function writeOffEscrow(
        int $businessId,
        int $storeId,
        int $itemId,
        float $quantity,
        User $user
    ): void {
        DB::transaction(function () use ($businessId, $storeId, $itemId, $quantity, $user) {
            $level = InventoryStockLevel::query()
                ->where('business_id', $businessId)
                ->where('store_id', $storeId)
                ->where('item_id', $itemId)
                ->lockForUpdate()
                ->first();

            $escrow = (float) ($level?->expired_quantity_suom ?? 0);
            if (! $level || $escrow + 0.0001 < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Insufficient expired escrow quantity to write off.',
                ]);
            }

            $level->expired_quantity_suom = round($escrow - $quantity, 4);
            $level->save();

            app(InventoryForensicAuditService::class)->record(
                $businessId,
                'EXPIRED_ESCROW_WRITEOFF',
                $user->id,
                $storeId,
                $itemId,
                $escrow,
                (float) $level->expired_quantity_suom
            );
        });
    }
}
