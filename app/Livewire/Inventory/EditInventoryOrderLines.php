<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryOrder;
use App\Models\InventoryOrderLine;
use App\Services\Inventory\InventoryOrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EditInventoryOrderLines extends Component
{
    public InventoryOrder $order;

    /** @var array<int, string> */
    public array $quantities = [];

    public function mount(InventoryOrder $order): void
    {
        if ((int) $order->business_id !== (int) Auth::user()->business_id) {
            abort(403);
        }

        $this->order = $order->load(['lines.item.itemUnit', 'lines.item.orderUnit']);

        foreach ($order->lines as $line) {
            $this->quantities[$line->id] = (string) $line->order_quantity_suom;
        }
    }

    public function saveLine(int $lineId, InventoryOrderService $service): void
    {
        if (! $this->order->isDraft()) {
            return;
        }

        $line = InventoryOrderLine::query()
            ->where('inventory_order_id', $this->order->id)
            ->where('id', $lineId)
            ->firstOrFail();

        $qty = (float) ($this->quantities[$lineId] ?? 0);
        $ouom = null;

        $item = $line->item;
        if ($item && $item->suom_per_ouom && (float) $item->suom_per_ouom > 0) {
            $ouom = round($qty / (float) $item->suom_per_ouom, 4);
        }

        $service->updateLine($line, $qty, $ouom);

        $this->order->refresh()->load(['lines.item.itemUnit', 'lines.item.orderUnit']);

        session()->flash('success', 'Line updated.');
    }

    public function render(): View
    {
        return view('livewire.inventory.edit-inventory-order-lines');
    }
}
