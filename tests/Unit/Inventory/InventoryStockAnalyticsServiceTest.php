<?php

namespace Tests\Unit\Inventory;

use App\Models\InventoryModuleConfig;
use App\Models\InventoryOrder;
use App\Models\InventoryStockLevel;
use App\Services\Inventory\InventoryStockAnalyticsService;
use Carbon\Carbon;
use Tests\TestCase;

class InventoryStockAnalyticsServiceTest extends TestCase
{
    private InventoryStockAnalyticsService $analytics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analytics = app(InventoryStockAnalyticsService::class);
    }

    public function test_current_stock_uses_physical_count_plus_movements(): void
    {
        $stock = new InventoryStockLevel([
            'quantity_suom' => 80,
            'physical_quantity_suom' => 100,
            'physical_counted_at' => Carbon::parse('2026-01-01'),
        ]);

        $current = $this->analytics->currentStockLevelSuom($stock, -20.0);

        $this->assertSame(80.0, $current);
    }

    public function test_current_stock_falls_back_to_ledger_without_count(): void
    {
        $stock = new InventoryStockLevel([
            'quantity_suom' => 45,
            'physical_quantity_suom' => 0,
        ]);

        $this->assertSame(45.0, $this->analytics->currentStockLevelSuom($stock));
    }

    public function test_stock_days_uses_current_stock_not_system_ar(): void
    {
        $config = new InventoryModuleConfig([
            'fixed_daily_average_suom' => 0,
            'safety_stock_days' => 5,
            'buffer_stock_days' => 5,
        ]);

        $stock = new InventoryStockLevel([
            'quantity_suom' => 50,
            'physical_quantity_suom' => 50,
            'ma_15_days' => 10,
        ]);

        $this->assertSame(5.0, $this->analytics->stockDaysReport($stock, $config));
        $this->assertSame(-5.0, $this->analytics->daysLeftToOrder($stock, $config));
    }

    public function test_period_order_qty_matches_excel_af(): void
    {
        $config = new InventoryModuleConfig([
            'safety_stock_days' => 10,
            'buffer_stock_days' => 5,
        ]);

        $order = new InventoryOrder([
            'period_of_order_days' => 30,
        ]);

        $stock = new InventoryStockLevel([
            'quantity_suom' => 50,
            'ma_15_days' => 10,
            'ma_30_days' => 8,
        ]);

        // N = 50/10 = 5; coverage = 30+10+5-5 = 40; AF = 40 * 10 (MA15 because N < 15)
        $qty = $this->analytics->suggestedOrderQtyPeriod($stock, $config, 30, $order);

        $this->assertSame(400.0, $qty);
    }

    public function test_budget_order_days_allocation_matches_excel_aj_and_ak(): void
    {
        $budgetDays = 60.0;
        $avgDaysLeft = 10.0;
        $sumTestAmount = 3000.0;
        $daysLeft = 5.0;
        $dailyUsage = 20.0;

        $orderDays = $this->analytics->orderDaysBudgetAllocation(
            $budgetDays,
            $daysLeft,
            $avgDaysLeft,
            $sumTestAmount
        );

        // AI gap = 5 - 10 = -5; AJ = (15*60/3000) - (-5) = 0.3 + 5 = 5.3
        $this->assertSame(5.3, $orderDays);

        $qty = $this->analytics->suggestedOrderQtyBudgetDays(
            $budgetDays,
            $daysLeft,
            $avgDaysLeft,
            $sumTestAmount,
            $dailyUsage
        );

        // AK = 5.3 * 20 = 106
        $this->assertSame(106.0, $qty);
    }
}
