<?php

namespace App\Livewire\Inventory\Concerns;

use App\Models\InventoryModuleConfig;
use App\Models\InventoryStockLevel;
use App\Services\Inventory\InventoryStockAnalyticsService;
use Illuminate\Database\Eloquent\Builder;

trait InteractsWithInventoryMetrics
{
    protected function metricsService(): InventoryStockAnalyticsService
    {
        return app(InventoryStockAnalyticsService::class);
    }

    protected function inventoryReportQuery(int $businessId): Builder
    {
        return InventoryStockLevel::query()
            ->where('inventory_stock_levels.business_id', $businessId)
            ->where(function (Builder $query) {
                $query->where('inventory_stock_levels.quantity_suom', '>', 0)
                    ->orWhere('inventory_stock_levels.ma_15_days', '>', 0)
                    ->orWhere('inventory_stock_levels.ma_30_days', '>', 0);
            })
            ->join('items', 'items.id', '=', 'inventory_stock_levels.item_id')
            ->join('stores', 'stores.id', '=', 'inventory_stock_levels.store_id')
            ->select([
                'inventory_stock_levels.*',
                'items.name as item_name',
                'items.code as item_code',
                'stores.name as store_name',
            ])
            ->with('item.itemUnit');
    }

    protected function moduleConfigFor(int $businessId): ?InventoryModuleConfig
    {
        return InventoryModuleConfig::query()
            ->forBusiness($businessId)
            ->active()
            ->first();
    }
}
