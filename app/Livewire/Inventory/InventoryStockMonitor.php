<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryStockLevel;
use App\Models\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class InventoryStockMonitor extends Component
{
    public const VIEW_LOCAL = 'local';

    public const VIEW_NETWORK = 'network';

    public string $stockView = self::VIEW_LOCAL;

    public ?int $storeId = null;

    /** @var array<int, string> */
    public array $storeOptions = [];

    public function mount(): void
    {
        $requestedView = request()->query('view');

        if ($requestedView === self::VIEW_NETWORK) {
            $this->stockView = self::VIEW_NETWORK;
        }

        $this->storeOptions = Store::optionsForSelect((int) Auth::user()->business_id);
        $this->storeId = $this->resolveDefaultStoreId();
    }

    public function updatedStockView(): void
    {
        if ($this->storeId === null) {
            $this->storeId = $this->resolveDefaultStoreId();
        }
    }

    public function selectedStoreLabel(): ?string
    {
        if (! $this->storeId) {
            return null;
        }

        return $this->storeOptions[(string) $this->storeId] ?? null;
    }

    public function render(): View
    {
        $businessId = (int) Auth::user()->business_id;
        $store = $this->storeId ? Store::query()->with('parent')->find($this->storeId) : null;

        return view('livewire.inventory.inventory-stock-monitor', [
            'stores' => $this->storeOptions,
            'store' => $store,
            'networkScope' => $store?->networkScopeDescription(),
        ]);
    }

    private function resolveDefaultStoreId(): ?int
    {
        $businessId = (int) Auth::user()->business_id;
        $user = Auth::user();

        if ($user->default_store_id) {
            $exists = Store::query()
                ->forBusiness($businessId)
                ->whereKey($user->default_store_id)
                ->exists();

            if ($exists) {
                return (int) $user->default_store_id;
            }
        }

        if ($user->branch_id) {
            $branchStoreId = Store::query()
                ->forBusiness($businessId)
                ->where('branch_id', $user->branch_id)
                ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
                ->orderBy('name')
                ->value('id');

            if ($branchStoreId) {
                return (int) $branchStoreId;
            }
        }

        $stockStoreId = InventoryStockLevel::query()
            ->where('business_id', $businessId)
            ->where(function ($query) {
                $query->where('quantity_suom', '>', 0)
                    ->orWhere('ma_15_days', '>', 0)
                    ->orWhere('ma_30_days', '>', 0);
            })
            ->orderByDesc('quantity_suom')
            ->value('store_id');

        if ($stockStoreId) {
            return (int) $stockStoreId;
        }

        $first = array_key_first($this->storeOptions);

        return $first ? (int) $first : null;
    }
}
