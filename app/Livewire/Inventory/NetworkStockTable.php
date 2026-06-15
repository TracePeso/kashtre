<?php

namespace App\Livewire\Inventory;

use App\Services\Inventory\InventoryNetworkRollupService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class NetworkStockTable extends Component
{
    public ?int $storeId = null;

    public bool $embedded = false;

    public function mount(?int $storeId = null, bool $embedded = false): void
    {
        $this->storeId = $storeId;
        $this->embedded = $embedded;
    }

    public function render(InventoryNetworkRollupService $rollup): View
    {
        $businessId = (int) Auth::user()->business_id;

        $rows = $this->storeId
            ? $rollup->rollupForStore($businessId, $this->storeId)
            : [];

        return view('livewire.inventory.network-stock-table', [
            'rows' => $rows,
            'embedded' => $this->embedded,
        ]);
    }
}
