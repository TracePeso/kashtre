<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InventoryForensicAuditLog extends Model
{
    protected $fillable = [
        'uuid',
        'business_id',
        'actor_user_id',
        'context',
        'store_id',
        'client_id',
        'item_id',
        'old_qty',
        'new_qty',
        'meta',
        'prev_hash',
        'row_hash',
        'committed_at',
    ];

    protected $casts = [
        'old_qty' => 'decimal:4',
        'new_qty' => 'decimal:4',
        'meta' => 'array',
        'committed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $row) {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
            if (empty($row->committed_at)) {
                $row->committed_at = now();
            }
        });

        static::updating(function () {
            return false;
        });

        static::deleting(function () {
            return false;
        });
    }
}
