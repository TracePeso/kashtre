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
                'inventory_stock_levels.id',
                'inventory_stock_levels.business_id',
                'inventory_stock_levels.store_id',
                'inventory_stock_levels.item_id',
                'inventory_stock_levels.quantity_suom',
                'inventory_stock_levels.physical_quantity_suom',
                'inventory_stock_levels.opening_quantity_suom',
                'inventory_stock_levels.damaged_quantity_suom',
                'inventory_stock_levels.expired_quantity_suom',
                'inventory_stock_levels.daily_usage_suom',
                'inventory_stock_levels.safety_stock_days',
                'inventory_stock_levels.buffer_stock_days',
                'inventory_stock_levels.ma_15_days',
                'inventory_stock_levels.ma_30_days',
                'inventory_stock_levels.ma_90_days',
                'inventory_stock_levels.ma_180_days',
                'inventory_stock_levels.ma_360_days',
                'inventory_stock_levels.last_purchase_price',
                'inventory_stock_levels.weighted_avg_cost',
                'items.name as item_name',
                'items.code as item_code',
                'stores.name as store_name',
            ])
            ->with(['item:id,uom_id,purchase_price,default_price,suom_per_ouom', 'item.itemUnit:id,name']);
    }

    protected function moduleConfigFor(int $businessId): ?InventoryModuleConfig
    {
        return InventoryModuleConfig::query()
            ->forBusiness($businessId)
            ->active()
            ->first();
    }
}
