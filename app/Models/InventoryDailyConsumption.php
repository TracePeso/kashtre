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
    public const SOURCE_IMAGING = 'imaging';

    /** Expired wastage — stock ↓ but excluded from moving-average / reorder demand. */
    public const SOURCE_WASTAGE_EXPIRED = 'wastage_expired';

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

    /**
     * Sources that feed demand / moving-average maths.
     *
     * @return list<string>
     */
    public static function demandSources(): array
    {
        return [
            self::SOURCE_MANUAL,
            self::SOURCE_SALE,
            self::SOURCE_ISSUE,
        ];
    }

    public function isExcludedFromDemand(): bool
    {
        return $this->source === self::SOURCE_WASTAGE_EXPIRED;
    }

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

    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_SALE => 'POS / Sale',
            self::SOURCE_ISSUE => 'Issue',
            self::SOURCE_MANUAL => 'Manual entry',
            self::SOURCE_WASTAGE_EXPIRED => 'Wastage (expired)',
            default => ucfirst(str_replace('_', ' ', $this->source)),
        };
    }
}
