<?php

namespace App\Http\Controllers;

use App\Models\InventoryStockCount;
use App\Models\Store;
use App\Services\Inventory\InventoryStockCountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventoryStockCountController extends Controller
{
    public function __construct(
        private readonly InventoryStockCountService $service
    ) {
        $this->middleware(function ($request, $next) {
            return $this->inventoryMiddleware($request, $next);
        });
    }

    public function index()
    {
        return view('inventory.stock-counts.index');
    }

    public function create()
    {
        $businessId = (int) Auth::user()->business_id;

        return view('inventory.stock-counts.create', [
            'stores' => Store::optionsForSelect($businessId),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'notes' => 'nullable|string|max:2000',
        ]);

        $businessId = (int) Auth::user()->business_id;
        $storeId = (int) $validated['store_id'];

        $store = Store::query()
            ->where('business_id', $businessId)
            ->where('id', $storeId)
            ->firstOrFail();

        $count = $this->service->createDraft(
            $businessId,
            $store->id,
            Auth::user(),
            $validated['notes'] ?? null
        );

        return redirect()
            ->route('inventory.stock-counts.show', $count)
            ->with('success', 'Stock count created. Enter physical quantities and finalize when ready.');
    }

    public function show(InventoryStockCount $stockCount)
    {
        $this->authorizeStockCount($stockCount);

        $stockCount->load(['lines.item.itemUnit', 'store', 'createdBy', 'finalizedBy']);

        return view('inventory.stock-counts.show', compact('stockCount'));
    }

    public function finalize(InventoryStockCount $stockCount)
    {
        $this->authorizeStockCount($stockCount);

        $this->service->finalize($stockCount, Auth::user());

        return redirect()
            ->route('inventory.stock-counts.show', $stockCount)
            ->with('success', 'Stock count finalized. Physical stock and shrinkage have been recorded.');
    }

    private function authorizeStockCount(InventoryStockCount $stockCount): void
    {
        if ((int) $stockCount->business_id !== (int) Auth::user()->business_id) {
            abort(403);
        }
    }

    private function inventoryMiddleware($request, $next)
    {
        $user = auth()->user();

        if ($user->business_id === 1) {
            abort(403, 'Inventory is only available to business users.');
        }

        $enabled = \App\Models\InventoryModuleConfig::query()
            ->where('business_id', $user->business_id)
            ->where('is_active', true)
            ->exists();

        if (! $enabled) {
            abort(403, 'The inventory module is not enabled for your organisation.');
        }

        return $next($request);
    }
}
