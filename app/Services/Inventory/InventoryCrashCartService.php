<?php

namespace App\Services\Inventory;

use App\Models\CrashCartItem;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryStockLevel;
use App\Models\InventoryUsageEvent;
use App\Models\Item;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryCrashCartService
{
    /**
     * Replace the cart manifest and restock to par while the cart is sealed.
     *
     * @param  list<array{item_id: int|string, par_quantity: float|int|string}>  $lines
     */
    public function syncManifest(Store $store, array $lines, User $user): void
    {
        $this->assertCrashCart($store);

        if ($store->isCrashCartOpen()) {
            throw ValidationException::withMessages([
                'crash_cart_items' => 'Cannot change the manifest while the seal is broken. Restock and reseal the cart first.',
            ]);
        }

        $normalized = $this->normalizeManifestLines($store, $lines);

        DB::transaction(function () use ($store, $normalized): void {
            $store->crashCartItems()->delete();

            foreach ($normalized as $line) {
                CrashCartItem::create([
                    'store_id' => $store->id,
                    'item_id' => $line['item_id'],
                    'par_quantity' => $line['par_quantity'],
                ]);
            }

            $this->applyManifestStock($store);
        });
    }

    public function breakSeal(Store $store, User $user): Store
    {
        $this->assertCrashCart($store);

        if (! $store->isCrashCartSealed()) {
            throw ValidationException::withMessages([
                'crash_cart_status' => 'Only a sealed crash cart can have its seal broken.',
            ]);
        }

        if ($store->crashCartItems()->count() === 0) {
            throw ValidationException::withMessages([
                'crash_cart_items' => 'Define the cart manifest before breaking the seal.',
            ]);
        }

        $store->update([
            'crash_cart_status' => Store::CRASH_CART_OPEN,
            'crash_cart_deployed_at' => now(),
        ]);

        return $store->fresh(['crashCartItems.item']);
    }

    /**
     * @return Collection<int, array{
     *     item_id: int,
     *     item_name: string,
     *     par: float,
     *     used: float,
     *     remaining: float,
     *     on_hand: float
     * }>
     */
    public function balances(Store $store): Collection
    {
        $this->assertCrashCart($store);

        $store->loadMissing(['crashCartItems.item']);

        $usedByItem = $this->usageQuantitiesSinceSealBroken($store);

        return $store->crashCartItems->map(function (CrashCartItem $line) use ($store, $usedByItem) {
            $itemId = (int) $line->item_id;
            $par = round((float) $line->par_quantity, 4);
            $used = round((float) ($usedByItem[$itemId] ?? 0), 4);
            $onHand = round((float) (InventoryStockLevel::query()
                ->where('business_id', $store->business_id)
                ->where('store_id', $store->id)
                ->where('item_id', $itemId)
                ->value('quantity_suom') ?? 0), 4);

            return [
                'item_id' => $itemId,
                'item_name' => (string) ($line->item?->name ?? 'Item #'.$itemId),
                'par' => $par,
                'used' => $used,
                'remaining' => max(0, round($par - $used, 4)),
                'on_hand' => $onHand,
            ];
        })->values();
    }

    public function remainingManifestQuantity(Store $store, int $itemId): float
    {
        $line = $store->crashCartItems()->where('item_id', $itemId)->first();
        if (! $line) {
            return 0;
        }

        $used = $this->usageQuantitiesSinceSealBroken($store)[$itemId] ?? 0;

        return max(0, round((float) $line->par_quantity - (float) $used, 4));
    }

    public function assertManifestItem(Store $store, int $itemId): void
    {
        if (! $store->crashCartItems()->where('item_id', $itemId)->exists()) {
            throw ValidationException::withMessages([
                'item_id' => 'That item is not on this crash cart manifest.',
            ]);
        }
    }

    public function assertAllowsStockIn(Store $store): void
    {
        if (! $store->isCrashCart()) {
            return;
        }

        if ($store->isCrashCartOpen()) {
            throw ValidationException::withMessages([
                'to_store_id' => 'Crash cart “'.$store->name.'” seal is broken. Stock cannot be transferred in until the cart is resealed.',
            ]);
        }
    }

    protected function applyManifestStock(Store $store): void
    {
        $store->loadMissing('crashCartItems');

        foreach ($store->crashCartItems as $line) {
            InventoryStockLevel::query()->updateOrCreate(
                [
                    'business_id' => $store->business_id,
                    'store_id' => $store->id,
                    'item_id' => $line->item_id,
                ],
                [
                    'quantity_suom' => $line->par_quantity,
                ]
            );
        }
    }

    /**
     * @return array<int, float>
     */
    protected function usageQuantitiesSinceSealBroken(Store $store): array
    {
        $query = InventoryUsageEvent::query()
            ->where('business_id', $store->business_id)
            ->where('store_id', $store->id)
            ->where('context', InventoryUsageEvent::CONTEXT_CRASH_CART);

        if ($store->crash_cart_deployed_at) {
            $query->where('occurred_at', '>=', $store->crash_cart_deployed_at);
        }

        $rows = $query
            ->selectRaw('item_id, SUM(quantity) as total_qty')
            ->groupBy('item_id')
            ->get();

        $used = [];
        foreach ($rows as $row) {
            $used[(int) $row->item_id] = round((float) $row->total_qty, 4);
        }

        return $used;
    }

    /**
     * @param  list<array{item_id: int|string, par_quantity: float|int|string}>  $lines
     * @return list<array{item_id: int, par_quantity: float}>
     */
    protected function normalizeManifestLines(Store $store, array $lines): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages([
                'crash_cart_items' => 'Add at least one item to the crash cart manifest.',
            ]);
        }

        $businessId = (int) $store->business_id;
        $normalized = [];
        $seen = [];

        foreach ($lines as $index => $line) {
            $itemId = (int) ($line['item_id'] ?? 0);
            $qty = round((float) ($line['par_quantity'] ?? 0), 4);

            if ($itemId <= 0) {
                throw ValidationException::withMessages([
                    'crash_cart_items' => 'Select an item on row '.($index + 1).'.',
                ]);
            }

            if ($qty <= 0) {
                throw ValidationException::withMessages([
                    'crash_cart_items' => 'Quantity must be greater than zero on row '.($index + 1).'.',
                ]);
            }

            if (isset($seen[$itemId])) {
                throw ValidationException::withMessages([
                    'crash_cart_items' => 'Each item can only appear once on the manifest.',
                ]);
            }

            $seen[$itemId] = true;

            $item = Item::query()
                ->where('business_id', $businessId)
                ->whereKey($itemId)
                ->where('type', 'good')
                ->first();

            if (! $item) {
                throw ValidationException::withMessages([
                    'crash_cart_items' => 'Item on row '.($index + 1).' is not a valid good for this organisation.',
                ]);
            }

            $normalized[] = [
                'item_id' => $itemId,
                'par_quantity' => $qty,
            ];
        }

        return $normalized;
    }

    protected function assertCrashCart(Store $store): void
    {
        if (! $this->businessCrashCartEnabled((int) $store->business_id)) {
            throw ValidationException::withMessages([
                'is_crash_cart' => 'Crash cart management is disabled for this organisation.',
            ]);
        }

        if (! $store->isCrashCart()) {
            throw ValidationException::withMessages([
                'is_crash_cart' => 'This store is not a crash-cart satellite.',
            ]);
        }
    }

    protected function businessCrashCartEnabled(int $businessId): bool
    {
        $config = InventoryModuleConfig::query()
            ->where('business_id', $businessId)
            ->first();

        return $config?->crashCartEnabled() ?? false;
    }
}
