<?php

namespace App\Http\Controllers;

use App\Models\InventoryModuleConfig;
use App\Support\InventoryBusinessContext;
use Illuminate\Http\RedirectResponse;

class InventoryContextController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if ((int) auth()->user()?->business_id !== 1) {
                abort(403);
            }

            if (! in_array('View Inventory Module', auth()->user()->permissions ?? [])) {
                abort(403);
            }

            return $next($request);
        });
    }

    public function exit(): RedirectResponse
    {
        $businessId = (int) session(InventoryBusinessContext::SESSION_KEY, 0);
        $config = $businessId > 0
            ? InventoryModuleConfig::query()->where('business_id', $businessId)->first()
            : null;

        InventoryBusinessContext::clearContext();

        if ($config) {
            return redirect()->route('inventory-module-configs.show', $config)
                ->with('success', 'Stopped browsing organisation inventory.');
        }

        return redirect()->route('inventory-module-configs.index')
            ->with('success', 'Stopped browsing organisation inventory.');
    }
}
