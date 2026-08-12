<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
use App\Models\InventoryUsageEvent;
use App\Models\Store;
use App\Services\Inventory\InventoryCrashCartService;
use App\Support\InventoryBusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class InventoryCrashCartController extends Controller
{
    use RequiresInventoryModule;

    public function __construct()
    {
        $this->middleware($this->inventoryMiddleware(...));
    }

    public function index()
    {
        $businessId = InventoryBusinessContext::effectiveBusinessId();

        $carts = Store::query()
            ->with(['parent:id,name', 'branch:id,name'])
            ->where('business_id', $businessId)
            ->where('is_crash_cart', true)
            ->orderBy('name')
            ->get()
            ->each(function (Store $cart): void {
                if ($cart->crash_cart_status !== Store::CRASH_CART_RECONCILING) {
                    return;
                }

                $usageQuery = InventoryUsageEvent::query()
                    ->where('business_id', $cart->business_id)
                    ->where('store_id', $cart->id)
                    ->where('context', InventoryUsageEvent::CONTEXT_CRASH_CART);

                if ($cart->crash_cart_deployed_at) {
                    $usageQuery->where('occurred_at', '>=', $cart->crash_cart_deployed_at);
                }

                $cart->setAttribute('reconciliation_usage_count', (int) $usageQuery->count());
                $cart->setAttribute('reconciliation_usage_qty', (float) $usageQuery->sum('quantity'));
            });

        return view('inventory.crash-carts.index', [
            'carts' => $carts,
        ]);
    }

    public function deploy(Store $store, InventoryCrashCartService $crashCarts): RedirectResponse
    {
        $this->assertOwnedCrashCart($store);

        try {
            $crashCarts->deploy($store, Auth::user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', 'Crash cart deployed. Record usage is locked until reconciliation.');
    }

    public function reconcile(Store $store, InventoryCrashCartService $crashCarts): RedirectResponse
    {
        $this->assertOwnedCrashCart($store);

        try {
            $crashCarts->startReconcile($store, Auth::user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', 'Reconciliation started. Record emergency usage, then complete reconciliation to return the cart to service.');
    }

    public function ready(Request $request, Store $store, InventoryCrashCartService $crashCarts): RedirectResponse
    {
        $this->assertOwnedCrashCart($store);

        $data = $request->validate([
            'seal_number' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $result = $crashCarts->markReady(
                $store,
                Auth::user(),
                (string) $data['seal_number'],
                $data['notes'] ?? null
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $message = $result['order']
            ? 'Crash cart returned to service. Replenishment draft #'.($result['order']->order_number ?? $result['order']->id).' created.'
            : 'Crash cart returned to service. No replenishment order was required.';

        return back()->with('status', $message);
    }

    protected function assertOwnedCrashCart(Store $store): void
    {
        abort_unless((int) $store->business_id === (int) InventoryBusinessContext::effectiveBusinessId(), 404);
        abort_unless($store->isCrashCart(), 404);
    }
}
