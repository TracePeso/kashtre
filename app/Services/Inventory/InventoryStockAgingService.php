<?php

namespace App\Services\Inventory;

use App\Models\GoodsReceivedNote;
use App\Models\GoodsReceivedNoteLine;
use App\Models\InventoryStockLevel;
use App\Models\Store;
use Carbon\Carbon;

class InventoryStockAgingService
{
    public function lastDeliveryDate(int $businessId, int $storeId, int $itemId): ?Carbon
    {
        $date = GoodsReceivedNoteLine::query()
            ->join('goods_received_notes as grn', 'grn.id', '=', 'goods_received_note_lines.goods_received_note_id')
            ->where('grn.business_id', $businessId)
            ->where('grn.store_id', $storeId)
            ->where('grn.status', GoodsReceivedNote::STATUS_APPROVED)
            ->where('goods_received_note_lines.item_id', $itemId)
            ->max('grn.date_of_delivery');

        return $date ? Carbon::parse($date) : null;
    }

    public function agingDays(int $businessId, int $storeId, int $itemId): ?int
    {
        $lastDelivery = $this->lastDeliveryDate($businessId, $storeId, $itemId);

        if (! $lastDelivery) {
            return null;
        }

        return max(0, (int) $lastDelivery->diffInDays(Carbon::today()));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function agingRows(int $businessId, ?int $storeId = null): array
    {
        $query = InventoryStockLevel::query()
            ->where('business_id', $businessId)
            ->where('quantity_suom', '>', 0)
            ->with(['item.itemUnit', 'store']);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $rows = [];

        foreach ($query->get() as $stock) {
            $lastDelivery = $this->lastDeliveryDate($businessId, (int) $stock->store_id, (int) $stock->item_id);
            $days = $lastDelivery ? max(0, (int) $lastDelivery->diffInDays(Carbon::today())) : null;

            $rows[] = [
                'stock' => $stock,
                'item' => $stock->item,
                'store' => $stock->store,
                'system_qty' => (float) $stock->quantity_suom,
                'last_delivery_date' => $lastDelivery,
                'aging_days' => $days,
            ];
        }

        usort($rows, fn (array $a, array $b) => ($b['aging_days'] ?? -1) <=> ($a['aging_days'] ?? -1));

        return $rows;
    }
}
