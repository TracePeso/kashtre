<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryStockLevel;
use App\Models\Store;
use App\Services\Inventory\InventoryAiAdvisor;
use App\Support\InventoryBusinessContext;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AiAdviceBriefing extends Component
{
    public string $useCase = 'consumption';

    public ?int $storeId = null;

    public ?int $itemId = null;

    public string $question = '';

    /** @var array<string, mixed>|null */
    public ?array $advice = null;

    /** @var array<int, string> */
    public array $storeOptions = [];

    /** @var array<int, string> */
    public array $itemOptions = [];

    public function mount(): void
    {
        $requested = request()->query('use_case');
        if (is_string($requested) && isset(InventoryAiAdvisor::USE_CASES[$requested])) {
            $this->useCase = $requested;
        }

        $this->storeOptions = Store::optionsForSelect($this->businessId());
        $requestedStore = request()->integer('store_id') ?: null;
        $this->storeId = $requestedStore && isset($this->storeOptions[$requestedStore])
            ? $requestedStore
            : $this->defaultStoreId();

        $this->refreshItemOptions();
        $requestedItem = request()->integer('item_id') ?: null;
        if ($requestedItem && isset($this->itemOptions[$requestedItem])) {
            $this->itemId = $requestedItem;
        }
    }

    public function updatedStoreId(): void
    {
        $this->itemId = null;
        $this->advice = null;
        $this->refreshItemOptions();
    }

    public function updatedItemId(): void
    {
        $this->advice = null;
    }

    public function setUseCase(string $useCase): void
    {
        if (! isset(InventoryAiAdvisor::USE_CASES[$useCase]) || $useCase === 'ask') {
            return;
        }

        $this->useCase = $useCase;
        $this->advice = null;
    }

    public function check(): void
    {
        $this->run($this->useCase);
    }

    public function ask(): void
    {
        $this->run('ask');
    }

    public function render(): View
    {
        $advisor = app(InventoryAiAdvisor::class);
        $meta = InventoryAiAdvisor::USE_CASES[$this->useCase];
        $briefing = $advisor->briefing(
            $this->businessId(),
            $this->storeId ?: null,
            $this->itemId ?: null,
            $this->useCase,
        );

        return view('livewire.inventory.ai-advice-briefing', [
            'configured' => $advisor->isConfigured(),
            'actionLabel' => $meta['label'],
            'needsHistory' => (bool) $meta['needs_history'],
            'briefing' => $briefing,
            'tasks' => collect(InventoryAiAdvisor::USE_CASES)
                ->except('ask')
                ->all(),
        ]);
    }

    private function run(string $useCase): void
    {
        $this->advice = app(InventoryAiAdvisor::class)->advise(
            $useCase,
            $this->businessId(),
            $this->storeId ?: null,
            $this->itemId ?: null,
            trim($this->question) !== '' ? trim($this->question) : null,
        );

        $this->dispatch('inventory-ai-logged');
    }

    private function refreshItemOptions(): void
    {
        if (! $this->storeId) {
            $this->itemOptions = [];

            return;
        }

        $this->itemOptions = InventoryStockLevel::query()
            ->where('inventory_stock_levels.business_id', $this->businessId())
            ->where('inventory_stock_levels.store_id', $this->storeId)
            ->join('items', 'items.id', '=', 'inventory_stock_levels.item_id')
            ->orderBy('items.name')
            ->pluck('items.name', 'items.id')
            ->all();
    }

    private function defaultStoreId(): ?int
    {
        $first = array_key_first($this->storeOptions);

        return $first ? (int) $first : null;
    }

    private function businessId(): int
    {
        return (int) InventoryBusinessContext::effectiveBusinessId();
    }
}
