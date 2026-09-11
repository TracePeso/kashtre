<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
use App\Support\InventoryBusinessContext;

class InventoryUnitEngineController extends Controller
{
    use RequiresInventoryModule;

    public function __construct()
    {
        $this->middleware($this->inventoryMiddleware(...));
    }

    public function index()
    {
        return view('inventory.units.index', [
            'businessId' => InventoryBusinessContext::effectiveBusinessId(),
            'engineEnabled' => (bool) config('units.enabled'),
        ]);
    }
}
