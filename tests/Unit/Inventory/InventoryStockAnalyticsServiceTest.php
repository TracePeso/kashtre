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

    public function test_period_order_qty_uses_15_day_ma_when_stock_days_is_zero(): void
    {
        $config = new InventoryModuleConfig([
            'safety_stock_days' => 10,
            'buffer_stock_days' => 10,
        ]);

        $stock = new InventoryStockLevel([
            'quantity_suom' => 0,
            'ma_15_days' => 299.2667,
            'ma_360_days' => 163.7778,
        ]);

        // Excel AF: N = 0 < 15 → use V; coverage = 30+10+10-0 = 50; AF = 50 * 299.2667
        $qty = $this->analytics->suggestedOrderQtyPeriod($stock, $config, 30);

        $this->assertSame(14963.335, $qty);
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

    public function test_budget_ah_test_amount_is_fifteen_times_usage_times_price(): void
    {
        $stock = new InventoryStockLevel([
            'business_id' => 0,
            'store_id' => 0,
            'item_id' => 0,
            'ma_15_days' => 10,
            'weighted_avg_cost' => 50,
        ]);

        // AH = 15 × 10 × 50 = 7500
        $this->assertSame(7500.0, $this->analytics->budgetTestAmountUgx($stock, null));
    }

    public function test_budget_ai_positive_reduces_order_days(): void
    {
        // Item above average AM → AI positive → AJ reduced vs urgent peer
        $urgentAj = $this->analytics->orderDaysBudgetAllocation(60, 5, 10, 3000);
        $comfortableAj = $this->analytics->orderDaysBudgetAllocation(60, 15, 10, 3000);

        // Urgent AI = -5 → AJ = 5.3; comfortable AI = +5 → AJ = max(0, 0.3-5) = 0
        $this->assertSame(5.3, $urgentAj);
        $this->assertSame(0.0, $comfortableAj);
        $this->assertGreaterThan($comfortableAj, $urgentAj);
    }

    public function test_budget_aj_clamps_to_zero_when_gap_exceeds_share(): void
    {
        $aj = $this->analytics->orderDaysBudgetAllocation(
            budgetUgxOrBa7: 30,
            daysLeft: 100,
            averageDaysLeft: 10,
            sumTestAmount: 1500
        );

        // base share = 15*30/1500 = 0.3; AI = 90; AJ = max(0, 0.3-90) = 0
        $this->assertSame(0.0, $aj);
        $this->assertSame(0.0, $this->analytics->suggestedOrderQtyBudgetDays(30, 100, 10, 1500, 12));
    }

    public function test_budget_returns_zero_when_sum_test_amount_is_zero(): void
    {
        $this->assertSame(0.0, $this->analytics->orderDaysBudgetAllocation(60, 5, 10, 0));
        $this->assertSame(0.0, $this->analytics->suggestedOrderQtyBudgetDays(60, 5, 10, 0, 20));
    }

    public function test_budget_returns_zero_when_budget_days_or_usage_is_zero(): void
    {
        $this->assertSame(0.0, $this->analytics->orderDaysBudgetAllocation(0, 5, 10, 3000));
        $this->assertSame(0.0, $this->analytics->suggestedOrderQtyBudgetDays(60, 5, 10, 3000, 0));
        $this->assertSame(0.0, $this->analytics->suggestedOrderQtyBudgetDays(0, 5, 10, 3000, 20));
    }

    public function test_budget_al_amount_is_ak_times_unit_price(): void
    {
        $ak = $this->analytics->suggestedOrderQtyBudgetDays(60, 5, 10, 3000, 20);
        $unitPrice = 15.5;
        $al = round($ak * $unitPrice, 2);

        $this->assertSame(106.0, $ak);
        $this->assertSame(1643.0, $al);
    }

    public function test_budget_portfolio_gives_more_days_to_urgent_item(): void
    {
        $budgetDays = 60.0;
        $sumAh = 4000.0; // two equal AH lines of 2000
        $avgAm = 10.0;

        $urgentAj = $this->analytics->orderDaysBudgetAllocation($budgetDays, 2, $avgAm, $sumAh);
        $comfortableAj = $this->analytics->orderDaysBudgetAllocation($budgetDays, 18, $avgAm, $sumAh);

        // Urgent AI = 2-10 = -8 → AJ = (15*60/4000) - (-8) = 0.225 + 8 = 8.225
        // Comfortable AI = 18-10 = 8 → AJ = max(0, 0.225 - 8) = 0
        $this->assertSame(8.225, $urgentAj);
        $this->assertSame(0.0, $comfortableAj);
        $this->assertGreaterThan($comfortableAj, $urgentAj);
    }
}
