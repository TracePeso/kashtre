<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class InventoryHandoffToken extends Model
{
    public const DEFAULT_TTL_MINUTES = 15;

    protected $fillable = [
        'uuid',
        'business_id',
        'store_id',
        'client_space_id',
        'basket_key',
        'code_hash',
        'expires_at',
        'used_at',
        'created_by',
        'used_by',
        'fulfillment_line_ids',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'fulfillment_line_ids' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $token) {
            if (empty($token->uuid)) {
                $token->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isActive(): bool
    {
        return $this->used_at === null && $this->expires_at !== null && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->used_at === null
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    public function lineCount(): int
    {
        return count($this->fulfillment_line_ids ?? []);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function clientSpace(): BelongsTo
    {
        return $this->belongsTo(ClientSpace::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }
}
