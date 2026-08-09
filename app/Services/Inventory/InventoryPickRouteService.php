<?php

namespace App\Services\Inventory;

use App\Models\ClientSpaceStoreAssignment;
use App\Models\InventoryFulfillmentLine;
use App\Models\InventoryStockLevel;
use App\Models\Store;
use Illuminate\Support\Collection;

class InventoryPickRouteService
{
    /**
     * Build a sequential pick route for an inpatient basket (SRD §4.4).
     *
     * @return array{
     *     store: Store,
     *     basket_key: string,
     *     lines: list<array{item_id:int,item_name:string,quantity:float,location_layer_3:?string,location_layer_2:?string,location_layer_1:?string}>
     * }
     */
    public function forBasket(InventoryFulfillmentLine $seed): array
    {
        $seed->loadMissing('store');

        $open = InventoryFulfillmentLine::query()
            ->with('item:id,name,code')
            ->where('business_id', $seed->business_id)
            ->where('store_id', $seed->store_id)
            ->where('fulfillment_strategy', ClientSpaceStoreAssignment::STRATEGY_BATCH_AND_STAGE)
            ->where('basket_key', (string) $seed->basket_key)
            ->whereIn('status', [
                InventoryFulfillmentLine::STATUS_PENDING,
                InventoryFulfillmentLine::STATUS_PICKING,
                InventoryFulfillmentLine::STATUS_PARTIAL,
                InventoryFulfillmentLine::STATUS_STAGED,
            ])
            ->get();

        $bySku = [];
        foreach ($open as $line) {
            $remaining = max(0, (float) $line->quantity - (float) $line->quantity_fulfilled);
            if ($remaining <= 0) {
                continue;
            }
            $key = (int) $line->item_id;
            if (! isset($bySku[$key])) {
                $bySku[$key] = [
                    'item_id' => $key,
                    'item_name' => $line->item_name ?: ($line->item?->name ?? 'Item'),
                    'quantity' => 0.0,
                ];
            }
            $bySku[$key]['quantity'] = round($bySku[$key]['quantity'] + $remaining, 4);
        }

        $locations = InventoryStockLevel::query()
            ->where('business_id', $seed->business_id)
            ->where('store_id', $seed->store_id)
            ->whereIn('item_id', array_keys($bySku))
            ->get(['item_id', 'location_layer_3', 'location_layer_2', 'location_layer_1'])
            ->keyBy('item_id');

        $rows = collect($bySku)->map(function (array $row) use ($locations) {
            $loc = $locations->get($row['item_id']);

            return [
                'item_id' => $row['item_id'],
                'item_name' => $row['item_name'],
                'quantity' => $row['quantity'],
                'location_layer_3' => $loc?->location_layer_3,
                'location_layer_2' => $loc?->location_layer_2,
                'location_layer_1' => $loc?->location_layer_1,
            ];
        })->sortBy([
            ['location_layer_3', 'asc'],
            ['location_layer_2', 'asc'],
            ['location_layer_1', 'asc'],
            ['item_name', 'asc'],
        ])->values()->all();

        return [
            'store' => $seed->store,
            'basket_key' => (string) $seed->basket_key,
            'lines' => $rows,
            'labels' => $seed->store?->locationLayerLabels() ?? Store::defaultLocationLabels($seed->store?->distribution_type),
        ];
    }
}
