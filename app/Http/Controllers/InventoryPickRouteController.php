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

    public function ward(Store $store, ClientSpace $clientSpace, InventoryPickRouteService $routes)
    {
        $businessId = (int) InventoryBusinessContext::effectiveBusinessId();
        abort_unless((int) $store->business_id === $businessId, 404);
        abort_unless((int) $clientSpace->business_id === $businessId, 404);
        abort_unless($store->isEndStore(), 404);

        $route = $routes->forWardRun($store, (int) $clientSpace->id);

        return view('inventory.fulfillment.pick-route', [
            'route' => $route,
            'line' => null,
        ]);
    }
}
