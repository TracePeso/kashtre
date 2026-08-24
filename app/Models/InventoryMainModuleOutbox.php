<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InventoryMainModuleOutbox extends Model
{
    protected $table = 'inventory_main_module_outbox';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const TYPE_FULFILLMENT_COMPLETED = 'fulfillment.completed';

    public const TYPE_USAGE_BILLING = 'usage.billing_packet';

    public const TYPE_CRASH_CART_REPLENISHMENT = 'crash_cart.replenishment_required';

    protected $fillable = [
        'uuid',
        'event_id',
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'business_id',
        'payload',
        'status',
        'attempts',
        'last_error',
        'available_at',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'encrypted:array',
        'available_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $row) {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
            if (empty($row->event_id)) {
                $row->event_id = $row->uuid;
            }
            if ($row->available_at === null) {
                $row->available_at = now();
            }
        });
    }
}
