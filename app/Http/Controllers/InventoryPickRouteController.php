<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
use App\Models\ClientSpace;
use App\Models\InventoryFulfillmentLine;
use App\Models\Store;
use App\Services\Inventory\InventoryPickRouteService;
use App\Support\InventoryBusinessContext;
use Illuminate\Http\Request;

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
        ]);
    }
}
