<?php

namespace App\Services\Inventory;

use App\Models\GoodsReturnNote;
use App\Models\GoodsReturnNoteLine;
use App\Models\InventoryStockLevel;
use App\Models\InventoryStockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryGoodsReturnService
{
    public function generateReference(int $businessId): string
    {
        $prefix = 'GRT-'.now()->format('Ymd');
        $count = GoodsReturnNote::query()
            ->where('business_id', $businessId)
            ->where('reference', 'like', $prefix.'%')
            ->count();

        return $prefix.'-'.str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
    }

    public function createDraft(
        int $businessId,
        int $storeId,
        ?int $supplierId,
        string $returnDate,
        ?string $reasonCode,
        User $user,
        ?string $notes = null
    ): GoodsReturnNote {
        return GoodsReturnNote::create([
            'business_id' => $businessId,
            'store_id' => $storeId,
            'supplier_id' => $supplierId,
            'reference' => $this->generateReference($businessId),
            'status' => GoodsReturnNote::STATUS_DRAFT,
            'return_date' => $returnDate,
            'reason_code' => $reasonCode,
            'notes' => $notes,
            'created_by_user_id' => $user->id,
        ]);
    }

    /**
     * @param  array<int, array{item_id: int, quantity_suom: float, batch_number?: string, unit_price?: float}>  $lines
     */
    public function syncLines(GoodsReturnNote $note, array $lines): void
    {
        if (! $note->isDraft()) {
            throw ValidationException::withMessages(['status' => 'Only draft returns can be edited.']);
        }

        $note->lines()->delete();

        foreach ($lines as $line) {
            $qty = (float) ($line['quantity_suom'] ?? 0);

            if ($qty <= 0) {
                continue;
            }

            GoodsReturnNoteLine::create([
                'goods_return_note_id' => $note->id,
                'item_id' => (int) $line['item_id'],
                'quantity_suom' => $qty,
                'batch_number' => $line['batch_number'] ?? null,
                'unit_price' => isset($line['unit_price']) ? (float) $line['unit_price'] : null,
            ]);
        }
    }

    public function submit(GoodsReturnNote $note, User $user): GoodsReturnNote
    {
        if (! $note->isDraft()) {
            throw ValidationException::withMessages(['status' => 'Only draft returns can be submitted.']);
        }

        if ($note->lines()->count() === 0) {
            throw ValidationException::withMessages(['lines' => 'Add at least one return line.']);
        }

        return DB::transaction(function () use ($note, $user) {
            $note->load('lines');

            foreach ($note->lines as $line) {
                $line->loadMissing('item');
                $qty = (float) $line->quantity_suom;

                $stock = InventoryStockLevel::query()
                    ->where('business_id', $note->business_id)
                    ->where('store_id', $note->store_id)
                    ->where('item_id', $line->item_id)
                    ->first();

                $before = (float) ($stock->quantity_suom ?? 0);

                if ($qty > $before) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Return quantity exceeds system stock for '.($line->item->name ?? 'item').'.',
                    ]);
                }

                $after = max(0, round($before - $qty, 4));
                $stock = InventoryStockLevel::updateOrCreate(
                    [
                        'business_id' => $note->business_id,
                        'store_id' => $note->store_id,
                        'item_id' => $line->item_id,
                    ],
                    ['quantity_suom' => $after]
                );

                InventoryStockMovement::create([
                    'business_id' => $note->business_id,
                    'item_id' => $line->item_id,
                    'store_id' => $note->store_id,
                    'movement_type' => InventoryStockMovement::TYPE_GOODS_RETURN,
                    'quantity_delta' => -$qty,
                    'balance_after' => $after,
                    'unit_price' => $line->unit_price,
                    'goods_return_note_id' => $note->id,
                    'reference_label' => 'Goods return '.$note->reference,
                    'recorded_by_user_id' => $user->id,
                    'occurred_at' => now(),
                ]);
            }

            $note->update([
                'status' => GoodsReturnNote::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ]);

            return $note->fresh(['lines.item', 'store', 'supplier']);
        });
    }
}
