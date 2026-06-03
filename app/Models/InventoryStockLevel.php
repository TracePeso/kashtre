<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryStockLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'store_id',
        'item_id',
        'quantity_suom',
        'physical_quantity_suom',
        'physical_counted_at',
        'damaged_quantity_suom',
        'opening_quantity_suom',
        'daily_usage_suom',
        'safety_stock_days',
        'buffer_stock_days',
        'ma_15_days',
        'ma_30_days',
        'ma_90_days',
        'ma_180_days',
        'ma_360_days',
        'last_purchase_price',
        'weighted_avg_cost',
    ];

    protected $casts = [
        'quantity_suom' => 'decimal:4',
        'physical_quantity_suom' => 'decimal:4',
        'physical_counted_at' => 'datetime',
        'damaged_quantity_suom' => 'decimal:4',
        'opening_quantity_suom' => 'decimal:4',
        'daily_usage_suom' => 'decimal:4',
        'safety_stock_days' => 'decimal:2',
        'buffer_stock_days' => 'decimal:2',
        'ma_15_days' => 'decimal:4',
        'ma_30_days' => 'decimal:4',
        'ma_90_days' => 'decimal:4',
        'ma_180_days' => 'decimal:4',
        'ma_360_days' => 'decimal:4',
        'last_purchase_price' => 'decimal:2',
        'weighted_avg_cost' => 'decimal:2',
    ];

    public function systemQuantitySuom(): float
    {
        return (float) $this->quantity_suom;
    }

    public function usableQuantitySuom(): float
    {
        $base = $this->physical_quantity_suom !== null
            ? (float) $this->physical_quantity_suom
            : (float) $this->quantity_suom;

        return max(0, round($base - (float) ($this->damaged_quantity_suom ?? 0), 4));
    }

    public function shrinkagePercent(): ?float
    {
        $system = (float) $this->quantity_suom;

        if ($this->physical_quantity_suom === null || $system <= 0) {
            return null;
        }

        return round((($system - (float) $this->physical_quantity_suom) / $system) * 100, 2);
    }

    public function shrinkageAmountSuom(): ?float
    {
        if ($this->physical_quantity_suom === null) {
            return null;
        }

        return round((float) $this->quantity_suom - (float) $this->physical_quantity_suom, 4);
    }

    public function valuationTotal(): float
    {
        $qty = $this->usableQuantitySuom();
        $avgCost = (float) ($this->weighted_avg_cost ?? $this->last_purchase_price ?? 0);

        return round($qty * $avgCost, 2);
    }

    public function stockDays(?float $dailyUsage = null): ?float
    {
        $usage = (float) ($dailyUsage ?? $this->daily_usage_suom ?? 0);

        if ($usage <= 0) {
            return null;
        }

        return round($this->usableQuantitySuom() / $usage, 1);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
