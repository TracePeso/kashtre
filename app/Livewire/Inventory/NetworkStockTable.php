<?php

namespace App\Livewire\Inventory;

use App\Models\Store;
use App\Services\Inventory\InventoryNetworkRollupService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class NetworkStockTable extends Component
{
    public ?int $storeId = null;

    public function render(InventoryNetworkRollupService $rollup): View
    {
        $businessId = (int) Auth::user()->business_id;
        $stores = Store::optionsForSelect($businessId);

        $rows = $this->storeId
            ? $rollup->rollupForStore($businessId, $this->storeId)
            : [];

        return view('livewire.inventory.network-stock-table', compact('stores', 'rows'));
    }
}
