<?php

namespace App\Services\Inventory;

use App\Models\GoodsReceivedNote;
use App\Models\GoodsReceivedNoteLine;
use App\Models\InventoryStockLevel;
use App\Models\Store;
use App\Support\StoreItemPairQuery;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class InventoryStockAgingService
{
    /** @var array<string, array{last_delivery: ?Carbon, aging_days: ?int}>|null */
    private ?array $pageAgingCache = null;

    public function resetPageAgingCache(): void
    {
        $this->pageAgingCache = null;
    }

    /**
     * @param  iterable<int, InventoryStockLevel>  $stocks
     */
    public function warmPageAging(int $businessId, iterable $stocks): void
    {
        $stocks = collect($stocks)->filter();

        if ($stocks->isEmpty()) {
            $this->pageAgingCache = [];

            return;
        }

        $pairs = $stocks
            ->map(fn (InventoryStockLevel $stock): array => [
                'store_id' => (int) $stock->store_id,
                'item_id' => (int) $stock->item_id,
            ])
            ->unique(fn (array $pair): string => "{$pair['store_id']}-{$pair['item_id']}")
            ->values();

        $rows = StoreItemPairQuery::whereInPairs(
            GoodsReceivedNoteLine::query()
                ->from('goods_received_note_lines as lines')
                ->join('goods_received_notes as grn', 'grn.id', '=', 'lines.goods_received_note_id')
                ->where('grn.business_id', $businessId)
                ->where('grn.status', GoodsReceivedNote::STATUS_APPROVED),
            $pairs,
            'grn.store_id',
            'lines.item_id'
        )
            ->selectRaw('grn.store_id, lines.item_id, MAX(grn.date_of_delivery) as last_delivery')
            ->groupBy('grn.store_id', 'lines.item_id')
            ->get();

        $this->pageAgingCache = [];

        foreach ($stocks as $stock) {
            $key = "{$stock->store_id}-{$stock->item_id}";
            $this->pageAgingCache[$key] = [
                'last_delivery' => null,
                'aging_days' => null,
            ];
        }

        foreach ($rows as $row) {
            $key = "{$row->store_id}-{$row->item_id}";
            $lastDelivery = Carbon::parse($row->last_delivery);

            $this->pageAgingCache[$key] = [
                'last_delivery' => $lastDelivery,
                'aging_days' => max(0, (int) $lastDelivery->diffInDays(Carbon::today())),
            ];
        }
    }

    public function pageLastDeliveryDate(int $businessId, int $storeId, int $itemId): ?Carbon
    {
        $key = "{$storeId}-{$itemId}";

        if ($this->pageAgingCache === null || ! array_key_exists($key, $this->pageAgingCache)) {
            $this->warmPageAging($businessId, [
                new InventoryStockLevel(['store_id' => $storeId, 'item_id' => $itemId]),
            ]);
        }

        return $this->pageAgingCache[$key]['last_delivery'] ?? null;
    }

    public function pageAgingDays(int $businessId, int $storeId, int $itemId): ?int
    {
        $key = "{$storeId}-{$itemId}";

        if ($this->pageAgingCache === null || ! array_key_exists($key, $this->pageAgingCache)) {
            $this->warmPageAging($businessId, [
                new InventoryStockLevel(['store_id' => $storeId, 'item_id' => $itemId]),
            ]);
        }

        return $this->pageAgingCache[$key]['aging_days'] ?? null;
    }

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
