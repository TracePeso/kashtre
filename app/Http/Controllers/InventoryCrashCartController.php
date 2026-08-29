<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
use App\Models\Client;
use App\Models\Store;
use App\Services\Inventory\InventoryCrashCartService;
use App\Services\Inventory\InventoryRecordUsageService;
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
            ->withCount('crashCartItems')
            ->where('business_id', $businessId)
            ->crashCarts()
            ->orderBy('name')
            ->get();

        return view('inventory.crash-carts.index', [
            'carts' => $carts,
        ]);
    }

    public function show(Store $store, InventoryCrashCartService $crashCarts)
    {
        $this->assertOwnedCrashCart($store);

        $store->load(['parent:id,name', 'branch:id,name', 'crashCartItems.item:id,name,strength,code']);

        $businessId = (int) $store->business_id;

        $balances = $crashCarts->balances($store);

        $clients = Client::query()
            ->where('business_id', $businessId)
            ->orderBy('name')
            ->get(['id', 'name', 'client_id']);

        $recentUsage = \App\Models\InventoryUsageEvent::query()
            ->with(['client:id,name,client_id', 'item:id,name', 'recordedBy:id,name'])
            ->where('business_id', $businessId)
            ->where('store_id', $store->id)
            ->where('context', \App\Models\InventoryUsageEvent::CONTEXT_CRASH_CART)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        return view('inventory.crash-carts.show', [
            'cart' => $store,
            'balances' => $balances,
            'clients' => $clients,
            'recentUsage' => $recentUsage,
        ]);
    }

    public function breakSeal(Store $store, InventoryCrashCartService $crashCarts): RedirectResponse
    {
        $this->assertOwnedCrashCart($store);

        try {
            $crashCarts->breakSeal($store, Auth::user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', 'Seal broken. Record usage against the cart manifest.');
    }

    public function recordUsage(Request $request, Store $store, InventoryRecordUsageService $usage): RedirectResponse
    {
        $this->assertOwnedCrashCart($store);

        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            app(InventoryCrashCartService::class)->assertManifestItem($store, (int) $data['item_id']);

            $events = $usage->record([
                'business_id' => (int) $store->business_id,
                'context' => \App\Models\InventoryUsageEvent::CONTEXT_CRASH_CART,
                'client_id' => (int) $data['client_id'],
                'item_id' => (int) $data['item_id'],
                'store_id' => (int) $store->id,
                'quantity' => $data['quantity'],
                'notes' => isset($data['notes']) ? trim((string) $data['notes']) : null,
                'occurred_at' => now(),
            ], Auth::user());

            $count = $events->count();
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('status', $count > 1
            ? 'Usage recorded ('.$count.' lines).'
            : 'Usage recorded.');
    }

    protected function assertOwnedCrashCart(Store $store): void
    {
        abort_unless((int) $store->business_id === (int) InventoryBusinessContext::effectiveBusinessId(), 404);
        abort_unless($store->isCrashCart(), 404);
    }
}
