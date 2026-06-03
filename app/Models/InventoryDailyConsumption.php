<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryDailyConsumption extends Model
{
    use HasFactory;

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_SALE = 'sale';
    public const SOURCE_ISSUE = 'issue';

    protected $fillable = [
        'business_id',
        'store_id',
        'item_id',
        'consumption_date',
        'quantity_suom',
        'source',
        'notes',
        'recorded_by_user_id',
    ];

    protected $casts = [
        'consumption_date' => 'date',
        'quantity_suom' => 'decimal:4',
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

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
