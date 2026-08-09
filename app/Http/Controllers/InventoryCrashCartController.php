<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
use App\Models\Store;
use App\Support\InventoryBusinessContext;

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
}
