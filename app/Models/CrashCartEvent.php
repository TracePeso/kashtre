<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrashCartEvent extends Model
{
    public const TYPE_BREAK_SEAL = 'break_seal';

    public const TYPE_RESTOCK_RESEAL = 'restock_reseal';

    protected $fillable = [
        'business_id',
        'store_id',
        'parent_store_id',
        'event_type',
        'seal_number',
        'previous_seal_number',
        'recorded_by_user_id',
        'lines',
        'meta',
        'occurred_at',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'store_id' => 'integer',
        'parent_store_id' => 'integer',
        'recorded_by_user_id' => 'integer',
        'lines' => 'array',
        'meta' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function parentStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'parent_store_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function label(): string
    {
        return match ($this->event_type) {
            self::TYPE_BREAK_SEAL => 'Seal broken',
            self::TYPE_RESTOCK_RESEAL => 'Restock & reseal',
            default => $this->event_type,
        };
    }
}
