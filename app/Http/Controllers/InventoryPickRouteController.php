<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
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
}
