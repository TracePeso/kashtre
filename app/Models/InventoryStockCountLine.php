<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStockCountLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_stock_count_id',
        'item_id',
        'system_quantity_suom',
        'physical_quantity_suom',
        'damaged_quantity_suom',
        'expired_quantity_suom',
    ];

    protected $casts = [
        'system_quantity_suom' => 'decimal:4',
        'physical_quantity_suom' => 'decimal:4',
        'damaged_quantity_suom' => 'decimal:4',
        'expired_quantity_suom' => 'decimal:4',
    ];

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(InventoryStockCount::class, 'inventory_stock_count_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function varianceSuom(): float
    {
        return round(
            (float) $this->physical_quantity_suom - (float) $this->system_quantity_suom,
            4
        );
    }

    public function shrinkagePercent(): ?float
    {
        $system = (float) $this->system_quantity_suom;

        if ($system <= 0) {
            return null;
        }

        return round(($this->varianceSuom() / $system) * -100, 2);
    }
}
