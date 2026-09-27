<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAiAdviceLog extends Model
{
    protected $fillable = [
        'business_id',
        'store_id',
        'item_id',
        'recorded_by_user_id',
        'use_case',
        'capability',
        'title',
        'question',
        'request_id',
        'ok',
        'error_code',
        'error',
        'summary',
        'request_payload',
        'response_payload',
    ];

    protected $casts = [
        'ok' => 'boolean',
        'request_payload' => 'array',
        'response_payload' => 'array',
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

    public function scopeLabel(): string
    {
        $store = $this->store?->name;
        $item = $this->item?->name;

        if ($item && $store) {
            return $item.' · '.$store;
        }

        if ($item) {
            return $item;
        }

        if ($store) {
            return 'All items · '.$store;
        }

        return 'Organisation';
    }
}
