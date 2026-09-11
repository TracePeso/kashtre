<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryConsumptionEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'store_id',
        'item_id',
        'quantity_suom',
        'occurred_at',
        'source',
        'sale_id',
    ];

    protected $casts = [
        'quantity_suom' => 'decimal:4',
        'occurred_at' => 'datetime',
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

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
