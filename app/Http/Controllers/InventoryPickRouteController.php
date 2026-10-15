<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
use App\Models\ClientSpace;
use App\Models\InventoryFulfillmentLine;
use App\Models\Store;
use App\Services\Inventory\InventoryFulfillmentStageService;
use App\Services\Inventory\InventoryPickRouteService;
use App\Support\InventoryBusinessContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class InventoryPickRouteController extends Controller
{
    use RequiresInventoryModule;

    public function __construct()
    {
        $this->middleware($this->inventoryMiddleware(...));
    }

    public function show(Request $request, InventoryFulfillmentLine $fulfillmentLine, InventoryPickRouteService $routes)
    {
        abort_unless((int) $fulfillmentLine->business_id === (int) InventoryBusinessContext::effectiveBusinessId(), 404);

        $route = $routes->forBasket($fulfillmentLine);

        return view('inventory.fulfillment.pick-route', [
            'route' => $route,
            'line' => $fulfillmentLine,
        ]);
    }

    public function ward(Request $request, Store $store, InventoryPickRouteService $routes, ?ClientSpace $clientSpace = null)
    {
        $businessId = (int) InventoryBusinessContext::effectiveBusinessId();
        abort_unless((int) $store->business_id === $businessId, 404);
        abort_unless($store->isEndStore(), 404);

        if ($clientSpace) {
            abort_unless((int) $clientSpace->business_id === $businessId, 404);
        }

        $visitId = $request->query('visit_id');
        $visitId = is_string($visitId) && $visitId !== '' ? $visitId : null;

        $route = $routes->forWardRun(
            $store,
            $clientSpace?->id,
            $visitId
        );

        return view('inventory.fulfillment.pick-route', [
            'route' => $route,
            'line' => null,
            'store' => $store,
            'clientSpace' => $clientSpace,
        ]);
    }

    public function stageWard(
        Request $request,
        Store $store,
        InventoryFulfillmentStageService $staging,
        ?ClientSpace $clientSpace = null,
    ) {
        $businessId = (int) InventoryBusinessContext::effectiveBusinessId();
        abort_unless((int) $store->business_id === $businessId, 404);
        abort_unless($store->isEndStore(), 404);

        if ($clientSpace) {
            abort_unless((int) $clientSpace->business_id === $businessId, 404);
        }

        $validated = $request->validate([
            'tote_barcode' => 'required|string|max:100',
        ]);

        $visitId = $request->input('visit_id');
        $visitId = is_string($visitId) && $visitId !== '' ? $visitId : null;

        try {
            $staged = $staging->stageReservoir(
                $store,
                Auth::user(),
                $validated['tote_barcode'],
                $clientSpace?->id,
                $visitId,
            );
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        $lineCount = collect($staged)->sum(fn (array $result) => $result['lines']->count());
        $basketCount = count($staged);

        return redirect()
            ->route($clientSpace ? 'inventory.fulfillment.ward-pick-route' : 'inventory.fulfillment.ward-pick', array_filter([
                'store' => $store,
                'client_space' => $clientSpace,
                'visit_id' => $visitId,
            ]))
            ->with('success', 'Staged '.$lineCount.' item'.($lineCount === 1 ? '' : 's').' for '.$basketCount.' patient envelope'.($basketCount === 1 ? '' : 's').' into tote '.$validated['tote_barcode'].'. Stock stays on this End Store until the ward releases the tote.');
    }
}
