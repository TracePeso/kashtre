<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
use App\Models\Store;
use App\Services\Inventory\InventoryInternalReplenishmentService;
use App\Support\InventoryBusinessContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class InventoryInternalReplenishmentController extends Controller
{
    use RequiresInventoryModule;

    public function __construct()
    {
        $this->middleware($this->inventoryMiddleware(...));
    }

    public function create()
    {
        $businessId = InventoryBusinessContext::effectiveBusinessId();
        $stores = Store::query()
            ->where('business_id', $businessId)
            ->whereIn('distribution_type', [Store::DISTRIBUTION_END, Store::DISTRIBUTION_SATELLITE])
            ->orderBy('name')
            ->get(['id', 'name', 'distribution_type', 'parent_id', 'reorder_level_days', 'max_stock_days']);

        return view('inventory.replenishment.create', compact('stores'));
    }

    public function store(Request $request, InventoryInternalReplenishmentService $service)
    {
        $validated = $request->validate([
            'child_store_id' => ['required', 'integer'],
            'forecast_basis' => ['required', 'in:consumption,demand'],
            'coverage_days' => ['nullable', 'numeric', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $order = $service->draft($validated, Auth::user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('inventory.orders.show', $order)
            ->with('success', 'Internal replenishment draft created.');
    }
}
