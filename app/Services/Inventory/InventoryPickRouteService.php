<?php

namespace App\Services\Inventory;

use App\Models\InventoryFulfillmentLine;
use App\Models\InventoryStockLevel;
use App\Models\Store;
use App\Support\InventoryFulfillmentStrategy;
use Illuminate\Support\Collection;

class InventoryPickRouteService
{
    /**
     * Build a sequential pick route for an inpatient basket (SRD §4.4).
     *
     * @return array{
     *     store: Store,
     *     basket_key: string,
     *     scope: string,
     *     visit_id: ?string,
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
            ->where('fulfillment_strategy', InventoryFulfillmentStrategy::BATCH_AND_STAGE)
            ->where('basket_key', (string) $seed->basket_key)
            ->whereIn('status', [
                InventoryFulfillmentLine::STATUS_PENDING,
                InventoryFulfillmentLine::STATUS_PICKING,
                InventoryFulfillmentLine::STATUS_PARTIAL,
                InventoryFulfillmentLine::STATUS_STAGED,
            ])
            ->get();

        return $this->buildRoute($seed->store, $open, [
            'scope' => 'basket',
            'basket_key' => (string) $seed->basket_key,
            'visit_id' => $seed->visit_id,
        ]);
    }

    /**
     * Ward / End Store reservoir collection run — all open inpatient lines at the store (SRD §4.4).
     * Optionally narrow to a Client Space and/or visit_id.
     *
     * @return array{
     *     store: Store,
     *     basket_key: string,
     *     scope: string,
     *     visit_id: ?string,
     *     client_space: ?\App\Models\ClientSpace,
     *     lines: list<array{item_id:int,item_name:string,quantity:float,location_layer_3:?string,location_layer_2:?string,location_layer_1:?string}>
     * }
     */
    public function forWardRun(Store $store, ?int $clientSpaceId = null, ?string $visitId = null): array
    {
        $open = InventoryFulfillmentLine::query()
            ->with(['item:id,name,code', 'clientSpace:id,name'])
            ->where('business_id', $store->business_id)
            ->where('store_id', $store->id)
            ->where('fulfillment_strategy', InventoryFulfillmentStrategy::BATCH_AND_STAGE)
            ->when($clientSpaceId, fn ($q) => $q->where('client_space_id', $clientSpaceId))
            ->when($visitId !== null && $visitId !== '', fn ($q) => $q->where('visit_id', $visitId))
            ->whereIn('status', [
                InventoryFulfillmentLine::STATUS_PENDING,
                InventoryFulfillmentLine::STATUS_PICKING,
                InventoryFulfillmentLine::STATUS_PARTIAL,
                InventoryFulfillmentLine::STATUS_STAGED,
            ])
            ->get();

        $space = $clientSpaceId
            ? ($open->first()?->clientSpace ?? \App\Models\ClientSpace::query()->find($clientSpaceId))
            : null;

        return $this->buildRoute($store, $open, [
            'scope' => 'ward',
            'basket_key' => $clientSpaceId
                ? 'ward-'.$clientSpaceId
                : ($visitId ? 'visit-'.$visitId : 'store-'.$store->id.'-reservoir'),
            'visit_id' => $visitId,
            'client_space' => $space,
        ]);
    }

    /**
     * @param  Collection<int, InventoryFulfillmentLine>  $open
     * @param  array{scope:string,basket_key:string,visit_id:?string,client_space?:mixed}  $meta
     * @return array<string, mixed>
     */
    protected function buildRoute(Store $store, Collection $open, array $meta): array
    {
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
            ->where('business_id', $store->business_id)
            ->where('store_id', $store->id)
            ->whereIn('item_id', array_keys($bySku) ?: [0])
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
            'store' => $store,
            'basket_key' => $meta['basket_key'],
            'scope' => $meta['scope'],
            'visit_id' => $meta['visit_id'] ?? null,
            'client_space' => $meta['client_space'] ?? null,
            'lines' => $rows,
            'labels' => $store->locationLayerLabels() ?? Store::defaultLocationLabels($store->distribution_type),
        ];
    }
}
