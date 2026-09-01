<?php

namespace App\Services\Inventory;

use App\Models\CrashCartEvent;
use App\Models\CrashCartItem;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryStockLevel;
use App\Models\InventoryStockMovement;
use App\Models\InventoryUsageEvent;
use App\Models\Item;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

        return DB::transaction(function () use ($store, $user) {
            $previousSeal = $store->crash_cart_seal_number;

            $store->update([
                'crash_cart_status' => Store::CRASH_CART_OPEN,
                'crash_cart_deployed_at' => now(),
            ]);

            CrashCartEvent::create([
                'business_id' => (int) $store->business_id,
                'store_id' => (int) $store->id,
                'parent_store_id' => $store->parent_id ? (int) $store->parent_id : null,
                'event_type' => CrashCartEvent::TYPE_BREAK_SEAL,
                'seal_number' => $previousSeal,
                'previous_seal_number' => $previousSeal,
                'recorded_by_user_id' => (int) $user->id,
                'lines' => null,
                'meta' => null,
                'occurred_at' => now(),
            ]);

            return $store->fresh(['crashCartItems.item']);
        });
    }

    /**
     * Recover used crash cart stock from the parent End Store, refill to par, and reseal.
     * Blocks if the parent cannot cover every shortfall.
     *
     * @return array{
     *     store: Store,
     *     restocked: list<array{item_id: int, item_name: string, quantity: float}>,
     *     seal_number: string
     * }
     */
    public function restockAndReseal(Store $store, User $user, ?string $sealNumber = null): array
    {
        $this->assertCrashCart($store);

        if (! $store->isCrashCartOpen()) {
            throw ValidationException::withMessages([
                'crash_cart_status' => 'Only an open (seal broken) crash cart can be restocked and resealed.',
            ]);
        }

        $store->loadMissing(['parent', 'crashCartItems.item']);
        $parent = $store->parent;

        if (! $parent || ! $parent->isEndStore()) {
            throw ValidationException::withMessages([
                'parent_id' => 'This crash cart must sit under an End Store to restock from.',
            ]);
        }

        $plan = $this->restockPlan($store, $parent);

        if ($plan['shortages'] !== []) {
            throw ValidationException::withMessages([
                'restock' => 'Parent End Store “'.$parent->name.'” does not have enough stock: '
                    .implode('; ', $plan['shortages']),
            ]);
        }

        $seal = trim((string) ($sealNumber ?? ''));
        if ($seal === '') {
            $seal = 'SEAL-'.strtoupper(Str::random(8));
        }

        return DB::transaction(function () use ($store, $parent, $user, $plan, $seal) {
            $previousSeal = $store->crash_cart_seal_number;

            foreach ($plan['lines'] as $line) {
                $this->moveStockBetweenStores(
                    businessId: (int) $store->business_id,
                    fromStoreId: (int) $parent->id,
                    toStoreId: (int) $store->id,
                    itemId: (int) $line['item_id'],
                    quantity: (float) $line['quantity'],
                    userId: (int) $user->id,
                    label: 'Crash cart restock → '.$store->name,
                );
            }

            $store->update([
                'crash_cart_status' => Store::CRASH_CART_READY,
                'crash_cart_sealed_at' => now(),
                'crash_cart_seal_number' => $seal,
                'crash_cart_deployed_at' => null,
            ]);

            $historyLines = array_map(static fn (array $line) => [
                'item_id' => (int) $line['item_id'],
                'item_name' => (string) $line['item_name'],
                'quantity' => (float) $line['quantity'],
            ], $plan['lines']);

            CrashCartEvent::create([
                'business_id' => (int) $store->business_id,
                'store_id' => (int) $store->id,
                'parent_store_id' => (int) $parent->id,
                'event_type' => CrashCartEvent::TYPE_RESTOCK_RESEAL,
                'seal_number' => $seal,
                'previous_seal_number' => $previousSeal,
                'recorded_by_user_id' => (int) $user->id,
                'lines' => $historyLines,
                'meta' => [
                    'parent_store_name' => $parent->name,
                    'lines_count' => count($historyLines),
                ],
                'occurred_at' => now(),
            ]);

            return [
                'store' => $store->fresh(['crashCartItems.item', 'parent:id,name']),
                'restocked' => $plan['lines'],
                'seal_number' => $seal,
            ];
        });
    }

    /**
     * Preview what Restock & reseal will pull from the parent End Store.
     *
     * @return array{
     *     lines: list<array{item_id: int, item_name: string, quantity: float, parent_on_hand: float}>,
     *     shortages: list<string>,
     *     parent: ?Store
     * }
     */
    public function restockPlan(Store $store, ?Store $parent = null): array
    {
        $this->assertCrashCart($store);
        $store->loadMissing(['parent', 'crashCartItems.item']);
        $parent ??= $store->parent;

        $lines = [];
        $shortages = [];

        if (! $parent || ! $parent->isEndStore()) {
            return [
                'lines' => [],
                'shortages' => ['Crash cart has no parent End Store to restock from.'],
                'parent' => $parent,
            ];
        }

        foreach ($this->balances($store) as $row) {
            $need = max(0, round((float) $row['par'] - (float) $row['on_hand'], 4));
            if ($need <= 0) {
                continue;
            }

            $parentOnHand = round((float) (InventoryStockLevel::query()
                ->where('business_id', $store->business_id)
                ->where('store_id', $parent->id)
                ->where('item_id', $row['item_id'])
                ->value('quantity_suom') ?? 0), 4);

            $lines[] = [
                'item_id' => (int) $row['item_id'],
                'item_name' => (string) $row['item_name'],
                'quantity' => $need,
                'parent_on_hand' => $parentOnHand,
            ];

            if ($parentOnHand + 0.0001 < $need) {
                $shortages[] = $row['item_name'].' needs '
                    .rtrim(rtrim(number_format($need, 2), '0'), '.')
                    .' but parent has '
                    .rtrim(rtrim(number_format($parentOnHand, 2), '0'), '.');
            }
        }

        return [
            'lines' => $lines,
            'shortages' => $shortages,
            'parent' => $parent,
        ];
    }

    protected function moveStockBetweenStores(
        int $businessId,
        int $fromStoreId,
        int $toStoreId,
        int $itemId,
        float $quantity,
        int $userId,
        string $label,
    ): void {
        $quantity = round($quantity, 4);
        if ($quantity <= 0) {
            return;
        }

        $from = InventoryStockLevel::query()->firstOrCreate(
            [
                'business_id' => $businessId,
                'store_id' => $fromStoreId,
                'item_id' => $itemId,
            ],
            ['quantity_suom' => 0, 'physical_quantity_suom' => 0]
        );

        $fromBefore = round((float) $from->quantity_suom, 4);
        if ($fromBefore + 0.0001 < $quantity) {
            throw ValidationException::withMessages([
                'restock' => 'Insufficient stock at the parent End Store while restocking.',
            ]);
        }

        $fromAfter = $from->applyOnHandBalance(max(0, round($fromBefore - $quantity, 4)));
        $from->save();

        InventoryStockMovement::create([
            'business_id' => $businessId,
            'item_id' => $itemId,
            'store_id' => $fromStoreId,
            'movement_type' => InventoryStockMovement::TYPE_TRANSFER_OUT,
            'quantity_delta' => -$quantity,
            'balance_after' => $fromAfter,
            'reference_label' => $label,
            'recorded_by_user_id' => $userId,
            'occurred_at' => now(),
        ]);

        app(InventoryForensicAuditService::class)->recordStockDelta(
            $from->fresh(),
            'CRASH_CART_RESTOCK_OUT',
            $fromBefore,
            $fromAfter,
            $userId,
            null,
            ['label' => $label, 'to_store_id' => $toStoreId]
        );

        $to = InventoryStockLevel::query()->firstOrCreate(
            [
                'business_id' => $businessId,
                'store_id' => $toStoreId,
                'item_id' => $itemId,
            ],
            ['quantity_suom' => 0, 'physical_quantity_suom' => 0]
        );

        $toBefore = round((float) $to->quantity_suom, 4);
        $toAfter = $to->applyOnHandBalance(round($toBefore + $quantity, 4));
        $to->save();

        InventoryStockMovement::create([
            'business_id' => $businessId,
            'item_id' => $itemId,
            'store_id' => $toStoreId,
            'movement_type' => InventoryStockMovement::TYPE_TRANSFER_IN,
            'quantity_delta' => $quantity,
            'balance_after' => $toAfter,
            'reference_label' => $label,
            'recorded_by_user_id' => $userId,
            'occurred_at' => now(),
        ]);

        app(InventoryForensicAuditService::class)->recordStockDelta(
            $to->fresh(),
            'CRASH_CART_RESTOCK_IN',
            $toBefore,
            $toAfter,
            $userId,
            null,
            ['label' => $label, 'from_store_id' => $fromStoreId]
        );
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
