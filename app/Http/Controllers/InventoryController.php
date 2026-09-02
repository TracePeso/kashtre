<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Support\InventoryBusinessContext;

class InventoryController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = auth()->user();

            if ((int) $user->business_id === 1 && ! InventoryBusinessContext::hasContext()) {
                abort(403, 'Select an organisation from Inventory Module Configuration and choose Browse inventory.');
            }

            if (! InventoryBusinessContext::moduleEnabled()) {
                abort(403, 'The inventory module is not enabled for this organisation.');
            }

            if (InventoryBusinessContext::isAdminBrowsing() && ! in_array($request->method(), ['GET', 'HEAD'], true)) {
                abort(403, 'Read-only while browsing another organisation\'s inventory.');
            }

            return $next($request);
        });
    }

    public function index()
    {
        return redirect()->route('inventory.receive');
    }

    public function receive()
    {
        return view('inventory.receive');
    }

    public function monitor()
    {
        return view('inventory.monitor');
    }

    public function network()
    {
        if (! InventoryBusinessContext::multiStoreNetworkEnabled()) {
            abort(403, 'Multi-store network view is disabled for this organisation.');
        }

        return redirect()->route('inventory.monitor', ['view' => 'network']);
    }

    public function stockHistory(Item $item)
    {
        if ((int) $item->business_id !== InventoryBusinessContext::effectiveBusinessId()) {
            abort(403);
        }

        if ($item->type !== 'good') {
            abort(404);
        }

        $item->load('itemUnit');

        return view('inventory.monitor.history', compact('item'));
    }
}
