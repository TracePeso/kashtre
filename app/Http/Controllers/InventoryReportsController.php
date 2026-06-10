<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;

class InventoryReportsController extends Controller
{
    use RequiresInventoryModule;

    public function __construct()
    {
        $this->middleware($this->inventoryMiddleware(...));
    }

    public function index()
    {
        return view('inventory.reports.index');
    }

    public function aging()
    {
        return view('inventory.reports.aging');
    }

    public function reorder()
    {
        return view('inventory.reports.reorder');
    }

    public function valuation()
    {
        return view('inventory.reports.valuation');
    }

    public function shrinkage()
    {
        return view('inventory.reports.shrinkage');
    }

    public function demand()
    {
        return view('inventory.reports.demand');
    }
}
