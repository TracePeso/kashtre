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
        'daily_usage_suom',
        'last_purchase_price',
        'weighted_avg_cost',
    ];

    protected $casts = [
        'quantity_suom' => 'decimal:4',
        'daily_usage_suom' => 'decimal:4',
        'last_purchase_price' => 'decimal:2',
        'weighted_avg_cost' => 'decimal:2',
    ];

    public function valuationTotal(): float
    {
        $qty = (float) $this->quantity_suom;
        $avgCost = (float) ($this->weighted_avg_cost ?? $this->last_purchase_price ?? 0);

        return round($qty * $avgCost, 2);
    }

    public function stockDays(): ?float
    {
        $usage = (float) ($this->daily_usage_suom ?? 0);

        if ($usage <= 0) {
            return null;
        }

        return round((float) $this->quantity_suom / $usage, 1);
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
