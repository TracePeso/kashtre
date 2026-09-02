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
        'unaccounted_quantity_suom',
        'shrinkage_quantity_suom',
        'shrinkage_value_ugx',
    ];

    protected $casts = [
        'system_quantity_suom' => 'decimal:4',
        'physical_quantity_suom' => 'decimal:4',
        'damaged_quantity_suom' => 'decimal:4',
        'expired_quantity_suom' => 'decimal:4',
        'unaccounted_quantity_suom' => 'decimal:4',
        'shrinkage_quantity_suom' => 'decimal:4',
        'shrinkage_value_ugx' => 'decimal:2',
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

    public function verifiedLossSuom(): float
    {
        return round(
            (float) $this->damaged_quantity_suom + (float) ($this->expired_quantity_suom ?? 0),
            4
        );
    }

    public function unaccountedLossSuom(): float
    {
        if ($this->unaccounted_quantity_suom !== null) {
            return (float) $this->unaccounted_quantity_suom;
        }

        return $this->computeUnaccountedLossSuom();
    }

    /** @deprecated Use unaccountedLossSuom() */
    public function unverifiedLossSuom(): float
    {
        return $this->unaccountedLossSuom();
    }

    public function totalShrinkageLossSuom(): float
    {
        if ($this->shrinkage_quantity_suom !== null) {
            return (float) $this->shrinkage_quantity_suom;
        }

        return max(0, round($this->computeUnaccountedLossSuom() + $this->verifiedLossSuom(), 4));
    }

    private function computeUnaccountedLossSuom(): float
    {
        return max(0, round(
            (float) $this->system_quantity_suom
            - (float) $this->physical_quantity_suom
            - $this->verifiedLossSuom(),
            4
        ));
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
