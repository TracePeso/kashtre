<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
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

    public function show(Store $store)
    {
        $this->assertOwnedCrashCart($store);

        $store->load(['parent:id,name', 'branch:id,name']);

        return view('inventory.crash-carts.show', [
            'cart' => $store,
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

        return back()->with('success', 'Seal broken. Record usage against the cart manifest.');
    }

    public function restockAndReseal(Request $request, Store $store, InventoryCrashCartService $crashCarts): RedirectResponse
    {
        $this->assertOwnedCrashCart($store);

        $data = $request->validate([
            'seal_number' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $result = $crashCarts->restockAndReseal(
                $store,
                Auth::user(),
                isset($data['seal_number']) ? trim((string) $data['seal_number']) : null
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $pulled = count($result['restocked']);
        $message = $pulled === 0
            ? 'Cart resealed (seal '.$result['seal_number'].'). Nothing to pull from the End Store.'
            : 'Restocked '.$pulled.' line'.($pulled === 1 ? '' : 's').' from the End Store and resealed (seal '.$result['seal_number'].').';

        return redirect()
            ->route('inventory.crash-carts.show', $result['store'])
            ->with('success', $message);
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

        return back()->with('success', $count > 1
            ? 'Usage recorded ('.$count.' lines).'
            : 'Usage recorded.');
    }

    protected function assertOwnedCrashCart(Store $store): void
    {
        abort_unless((int) $store->business_id === (int) InventoryBusinessContext::effectiveBusinessId(), 404);
        abort_unless($store->isCrashCart(), 404);
    }
}
