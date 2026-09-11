<?php

namespace Tests\Unit\Inventory;

use App\Services\Inventory\InventoryDaysOfStockService;
use PHPUnit\Framework\TestCase;

class InventoryDaysOfStockServiceTest extends TestCase
{
    public function test_forecast_window_matrix_matches_srd(): void
    {
        $svc = new InventoryDaysOfStockService();

        $this->assertSame(15, $svc->forecastWindowDays(10));
        $this->assertSame(30, $svc->forecastWindowDays(15));
        $this->assertSame(30, $svc->forecastWindowDays(30));
        $this->assertSame(90, $svc->forecastWindowDays(45));
        $this->assertSame(180, $svc->forecastWindowDays(120));
        $this->assertSame(365, $svc->forecastWindowDays(200));
    }
}
