<?php

namespace App\Http\Controllers\Concerns;

use App\Support\InventoryBusinessContext;
use Illuminate\Support\Facades\Auth;

trait RequiresInventoryModule
{
    protected function inventoryMiddleware($request, $next)
    {
        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

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
    }
}
