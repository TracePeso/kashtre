<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
use App\Models\Store;
use App\Support\InventoryBusinessContext;

class InventoryFulfillmentController extends Controller
{
    use RequiresInventoryModule;

    public function __construct()
    {
        $this->middleware($this->inventoryMiddleware(...));
    }

    public function index()
    {
        $businessId = InventoryBusinessContext::effectiveBusinessId();

        $hasEndStores = Store::query()
            ->where('business_id', $businessId)
            ->where('distribution_type', Store::DISTRIBUTION_END)
            ->exists();

        return view('inventory.fulfillment.index', [
            'hasEndStores' => $hasEndStores,
        ]);
    }
}
