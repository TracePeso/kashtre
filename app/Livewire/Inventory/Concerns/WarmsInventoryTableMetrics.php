<?php

namespace App\Livewire\Inventory\Concerns;

use App\Models\InventoryModuleConfig;
use App\Models\InventoryStockLevel;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

trait WarmsInventoryTableMetrics
{
    /**
     * @return Collection<int, InventoryStockLevel>
     */
    protected function stockLevelsFromPaginator(Paginator $paginator): Collection
    {
        return $paginator->getCollection();
    }

    protected function warmTablePageMetrics(iterable $stockLevels): void
    {
        $config = $this->resolveInventoryModuleConfig();
        $periodDays = (float) ($config?->period_of_order_days ?? 30);

        $this->metricsService()->warmPageMetrics($stockLevels, $config, $periodDays);

        if (method_exists($this, 'warmAgingMetricsForStocks')) {
            $this->warmAgingMetricsForStocks($stockLevels);
        }
    }

    protected function resolveInventoryModuleConfig(): ?InventoryModuleConfig
    {
        if (property_exists($this, 'moduleConfig') && $this->moduleConfig instanceof InventoryModuleConfig) {
            return $this->moduleConfig;
        }

        if (property_exists($this, 'inventoryModuleConfig') && $this->inventoryModuleConfig instanceof InventoryModuleConfig) {
            return $this->inventoryModuleConfig;
        }

        return $this->moduleConfigFor((int) \App\Support\InventoryBusinessContext::effectiveBusinessId());
    }

    protected function m(InventoryStockLevel $stock, string $field): mixed
    {
        $config = $this->resolveInventoryModuleConfig();

        return $this->metricsService()->pageMetric(
            $stock,
            $field,
            $config,
            $stock->item,
            (float) ($config?->period_of_order_days ?? 30),
        );
    }
}
