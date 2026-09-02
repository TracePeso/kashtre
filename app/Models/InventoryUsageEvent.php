<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class InventoryUsageEvent extends Model
{
    public const CONTEXT_PATIENT = 'patient';

    public const CONTEXT_ADMINISTRATIVE = 'administrative';

    public const CONTEXT_CRASH_CART = 'crash_cart';

    public const CONTEXT_WASTAGE_OPERATIONAL = 'wastage_operational';

    public const CONTEXT_WASTAGE_EXPIRED = 'wastage_expired';

    public const CLASSIFICATION_PATIENT = 'PATIENT';

    public const CLASSIFICATION_ADMINISTRATIVE = 'ADMINISTRATIVE';

    public const CLASSIFICATION_CRASH_CART = 'CRASH_CART';

    public const CLASSIFICATION_WASTAGE_OPERATIONAL = 'WASTAGE_OPERATIONAL';

    public const CLASSIFICATION_WASTAGE_EXPIRED = 'WASTAGE_EXPIRED';

    public const RESOLUTION_APPROVED_POOL = 'approved_pool';

    public const RESOLUTION_PHYSICAL_STOCK = 'physical_stock';

    protected $fillable = [
        'uuid',
        'business_id',
        'context',
        'classification',
        'client_id',
        'item_id',
        'store_id',
        'quantity',
        'resolution',
        'billed_main_module',
        'invoice_id',
        'main_billing_status',
        'main_billing_error',
        'main_billed_at',
        'recorded_by',
        'occurred_at',
        'notes',
        'pool_allocations',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'billed_main_module' => 'boolean',
        'occurred_at' => 'datetime',
        'main_billed_at' => 'datetime',
        'pool_allocations' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $event) {
            if (empty($event->uuid)) {
                $event->uuid = (string) Str::uuid();
            }
            if (empty($event->occurred_at)) {
                $event->occurred_at = now();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return array<string, string>
     */
    public static function contextOptions(): array
    {
        return [
            self::CONTEXT_PATIENT => 'Patient',
            self::CONTEXT_ADMINISTRATIVE => 'Administrative',
            self::CONTEXT_CRASH_CART => 'Crash cart',
            self::CONTEXT_WASTAGE_OPERATIONAL => 'Wastage (operational)',
            self::CONTEXT_WASTAGE_EXPIRED => 'Wastage (expired)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function resolutionOptions(): array
    {
        return [
            self::RESOLUTION_APPROVED_POOL => 'Approved Pool',
            self::RESOLUTION_PHYSICAL_STOCK => 'Physical stock',
        ];
    }

    public function contextLabel(): string
    {
        return self::contextOptions()[$this->context] ?? $this->context;
    }

    public function resolutionLabel(): string
    {
        return self::resolutionOptions()[$this->resolution] ?? $this->resolution;
    }

    public function requiresStore(): bool
    {
        return in_array($this->context, [
            self::CONTEXT_ADMINISTRATIVE,
            self::CONTEXT_CRASH_CART,
            self::CONTEXT_WASTAGE_OPERATIONAL,
            self::CONTEXT_WASTAGE_EXPIRED,
        ], true);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
