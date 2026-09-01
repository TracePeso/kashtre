<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class InventoryDemandLedger extends Model
{
    protected $fillable = [
        'uuid',
        'business_id',
        'store_id',
        'item_id',
        'quantity',
        'source',
        'client_id',
        'invoice_id',
        'occurred_at',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $row) {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
            if (empty($row->occurred_at)) {
                $row->occurred_at = now();
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
