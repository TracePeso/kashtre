<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
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
            ->get();

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

        return back()->with('status', 'Crash cart is reconciling. Record emergency usage, then seal to Ready.');
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
            ? 'Cart sealed Ready. Replenishment draft #'.($result['order']->order_number ?? $result['order']->id).' created.'
            : 'Cart sealed Ready. No replenishment ticket was needed.';

        return back()->with('status', $message);
    }

    protected function assertOwnedCrashCart(Store $store): void
    {
        abort_unless((int) $store->business_id === (int) InventoryBusinessContext::effectiveBusinessId(), 404);
        abort_unless($store->isCrashCart(), 404);
    }
}
