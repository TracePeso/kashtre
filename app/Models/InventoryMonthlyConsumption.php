<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMonthlyConsumption extends Model
{
    protected $fillable = [
        'business_id',
        'store_id',
        'item_id',
        'consumption_month',
        'total_quantity_suom',
        'days_with_usage',
    ];

    protected $casts = [
        'consumption_month' => 'date',
        'total_quantity_suom' => 'decimal:4',
        'days_with_usage' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function consumptionMonthKey(): string
    {
        return $this->consumption_month->format('Y-m');
    }
}
