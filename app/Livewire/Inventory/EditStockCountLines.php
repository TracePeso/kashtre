<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryStockCount;
use App\Models\InventoryStockCountLine;
use App\Services\Inventory\InventoryStockCountService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EditStockCountLines extends Component
{
    public InventoryStockCount $stockCount;

    /** @var array<int, array{physical: string, damaged: string}> */
    public array $lines = [];

    public function mount(InventoryStockCount $stockCount): void
    {
        if ((int) $stockCount->business_id !== (int) Auth::user()->business_id) {
            abort(403);
        }

        $this->stockCount = $stockCount->load('lines.item.itemUnit');

        foreach ($stockCount->lines as $line) {
            $this->lines[$line->id] = [
                'physical' => (string) $line->physical_quantity_suom,
                'damaged' => (string) $line->damaged_quantity_suom,
            ];
        }
    }

    public function saveLine(int $lineId, InventoryStockCountService $service): void
    {
        if (! $this->stockCount->isDraft()) {
            return;
        }

        $line = InventoryStockCountLine::query()
            ->where('inventory_stock_count_id', $this->stockCount->id)
            ->where('id', $lineId)
            ->firstOrFail();

        $data = $this->lines[$lineId] ?? ['physical' => '0', 'damaged' => '0'];

        $service->updateLine(
            $line,
            (float) $data['physical'],
            (float) $data['damaged']
        );

        session()->flash('success', 'Line updated.');
    }

    public function render(): View
    {
        return view('livewire.inventory.edit-stock-count-lines');
    }
}
