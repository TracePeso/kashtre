<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryAiAdviceLog;
use App\Support\InventoryBusinessContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\On;
use Livewire\Component;

class AiAdviceLogTable extends Component
{
    public ?int $storeId = null;

    public ?int $itemId = null;

    #[On('inventory-ai-logged')]
    public function refreshLogs(): void
    {
        // Render reloads the query.
    }

    public function render(): View
    {
        return view('livewire.inventory.ai-advice-log-table', [
            'logs' => $this->recentLogs(),
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, InventoryAiAdviceLog>
     */
    private function recentLogs()
    {
        if (! Schema::hasTable('inventory_ai_advice_logs')) {
            return collect();
        }

        return InventoryAiAdviceLog::query()
            ->with(['store:id,name', 'item:id,name', 'recordedBy:id,name'])
            ->where('business_id', (int) InventoryBusinessContext::effectiveBusinessId())
            ->when($this->storeId, fn ($q) => $q->where(function ($query) {
                $query->where('store_id', $this->storeId)->orWhereNull('store_id');
            }))
            ->when($this->itemId, fn ($q) => $q->where(function ($query) {
                $query->where('item_id', $this->itemId)->orWhereNull('item_id');
            }))
            ->latest('id')
            ->limit(25)
            ->get();
    }
}
