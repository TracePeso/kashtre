<?php

namespace App\Services\Inventory;

use App\Models\InventoryModuleConfig;
use App\Models\InventoryOrder;
use App\Models\InventoryOrderLine;
use App\Models\InventoryStockLevel;
use App\Models\InventoryUsageEvent;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryCrashCartService
{
    public function __construct(
        private readonly InventoryOrderService $orders,
    ) {}

    public function deploy(Store $store, User $user): Store
    {
        $this->assertCrashCart($store);

        if ($store->crash_cart_status !== Store::CRASH_CART_READY) {
            throw ValidationException::withMessages([
                'crash_cart_status' => 'Only a Ready crash cart can be deployed.',
            ]);
        }

        $store->update([
            'crash_cart_status' => Store::CRASH_CART_DEPLOYED,
            'crash_cart_deployed_at' => now(),
        ]);

        return $store->fresh();
    }

    public function startReconcile(Store $store, User $user): Store
    {
        $this->assertCrashCart($store);

        if ($store->crash_cart_status !== Store::CRASH_CART_DEPLOYED) {
            throw ValidationException::withMessages([
                'crash_cart_status' => 'Only a Deployed crash cart can enter reconciliation.',
            ]);
        }

        $store->update([
            'crash_cart_status' => Store::CRASH_CART_RECONCILING,
        ]);

        return $store->fresh();
    }

    /**
     * Seal the cart, mark Ready, and open an internal replenishment draft for used items.
     *
     * @return array{store: Store, order: ?InventoryOrder}
     */
    public function markReady(Store $store, User $user, string $sealNumber, ?string $notes = null): array
    {
        $this->assertCrashCart($store);

        if ($store->crash_cart_status !== Store::CRASH_CART_RECONCILING) {
            throw ValidationException::withMessages([
                'crash_cart_status' => 'Mark Ready after reconciliation (restock / seal). Cart must be Reconciling.',
            ]);
        }

        $sealNumber = trim($sealNumber);
        if ($sealNumber === '') {
            throw ValidationException::withMessages([
                'seal_number' => 'Enter the new seal number before marking the cart Ready.',
            ]);
        }

        if (! $store->parent_id) {
            throw ValidationException::withMessages([
                'parent_id' => 'Crash cart must sit under an End Store to create a replenishment ticket.',
            ]);
        }

        return DB::transaction(function () use ($store, $user, $sealNumber, $notes) {
            $order = $this->createReplenishmentDraft($store, $user, $sealNumber, $notes);

            $store->update([
                'crash_cart_status' => Store::CRASH_CART_READY,
                'crash_cart_seal_number' => $sealNumber,
                'crash_cart_sealed_at' => now(),
                'crash_cart_last_replenishment_order_id' => $order?->id,
            ]);

            return [
                'store' => $store->fresh(),
                'order' => $order?->fresh(['lines.item', 'sourceStore', 'store']),
            ];
        });
    }

    public function assertAllowsStockIn(Store $store): void
    {
        if (! $store->isCrashCart()) {
            return;
        }

        if ($store->crash_cart_status === Store::CRASH_CART_DEPLOYED) {
            throw ValidationException::withMessages([
                'to_store_id' => 'Crash cart “'.$store->name.'” is deployed. Stock cannot be added until it is reconciling or ready.',
            ]);
        }
    }

    protected function createReplenishmentDraft(
        Store $store,
        User $user,
        string $sealNumber,
        ?string $notes
    ): ?InventoryOrder {
        $used = $this->usageSinceDeploy($store);

        if ($used === []) {
            return null;
        }

        $noteBits = [
            'Crash cart replenishment for '.$store->name,
            'Seal: '.$sealNumber,
        ];
        if ($notes) {
            $noteBits[] = $notes;
        }

        $order = InventoryOrder::create([
            'business_id' => $store->business_id,
            'store_id' => $store->id,
            'order_type' => InventoryOrder::TYPE_INTERNAL,
            'source_store_id' => $store->parent_id,
            'order_number' => $this->orders->generateOrderNumber(
                (int) $store->business_id,
                InventoryOrder::TYPE_INTERNAL
            ),
            'status' => InventoryOrder::STATUS_DRAFT,
            'notes' => implode(' · ', $noteBits),
            'created_by_user_id' => $user->id,
        ]);

        foreach ($used as $itemId => $qty) {
            $onHand = (float) (InventoryStockLevel::query()
                ->where('business_id', $store->business_id)
                ->where('store_id', $store->id)
                ->where('item_id', $itemId)
                ->value('quantity_suom') ?? 0);

            InventoryOrderLine::create([
                'inventory_order_id' => $order->id,
                'item_id' => $itemId,
                'daily_average_suom' => 0,
                'lead_time_days' => 0,
                'system_quantity_suom' => $onHand,
                'current_stock_suom' => $onHand,
                'suggested_quantity_suom' => $qty,
                'base_suggested_quantity_suom' => $qty,
                'order_quantity_suom' => $qty,
            ]);
        }

        return $order;
    }

    /**
     * @return array<int, float> item_id => quantity
     */
    protected function usageSinceDeploy(Store $store): array
    {
        $query = InventoryUsageEvent::query()
            ->where('business_id', $store->business_id)
            ->where('store_id', $store->id)
            ->where('context', InventoryUsageEvent::CONTEXT_CRASH_CART)
            ->where('resolution', InventoryUsageEvent::RESOLUTION_PHYSICAL_STOCK);

        if ($store->crash_cart_deployed_at) {
            $query->where('occurred_at', '>=', $store->crash_cart_deployed_at);
        } elseif ($store->crash_cart_sealed_at) {
            $query->where('occurred_at', '>', $store->crash_cart_sealed_at);
        }

        $rows = $query
            ->selectRaw('item_id, SUM(quantity) as total_qty')
            ->groupBy('item_id')
            ->havingRaw('SUM(quantity) > 0')
            ->get();

        $used = [];
        foreach ($rows as $row) {
            $used[(int) $row->item_id] = round((float) $row->total_qty, 4);
        }

        return $used;
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
                    'is_crash_cart' => 'This store is not a crash-cart satellite (set Satellite role to Crash cart under Manage Stores).',
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
