<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;

class InventoryRecordUsageController extends Controller
{
    use RequiresInventoryModule;

    public function __construct()
    {
        $this->middleware($this->inventoryMiddleware(...));
    }

    public function index()
    {
        return view('inventory.usage.index');
    }
}
