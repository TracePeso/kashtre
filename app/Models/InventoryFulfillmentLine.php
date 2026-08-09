<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class InventoryFulfillmentLine extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PICKING = 'picking';

    public const STATUS_STAGED = 'staged';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_PARTIAL = 'partial';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITY_STAT = 'stat';

    protected $fillable = [
        'uuid',
        'business_id',
        'store_id',
        'client_space_id',
        'invoice_id',
        'client_id',
        'visit_id',
        'item_id',
        'service_delivery_queue_id',
        'item_name',
        'quantity',
        'quantity_fulfilled',
        'fulfillment_strategy',
        'priority',
        'status',
        'basket_key',
        'queued_at',
        'acknowledged_at',
        'staged_at',
        'handoff_token_id',
        'completed_at',
        'acknowledged_by',
        'completed_by',
        'notes',
        'metadata',
        'dispense_serials',
        'dispense_batch_lot',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'quantity_fulfilled' => 'decimal:2',
        'queued_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'staged_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
        'dispense_serials' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $line) {
            if (empty($line->uuid)) {
                $line->uuid = (string) Str::uuid();
            }
            if (empty($line->queued_at)) {
                $line->queued_at = now();
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
    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PICKING => 'Picking',
            self::STATUS_STAGED => 'Staged',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_PARTIAL => 'Partial',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function priorityOptions(): array
    {
        return [
            self::PRIORITY_NORMAL => 'Normal',
            self::PRIORITY_URGENT => 'Urgent',
            self::PRIORITY_STAT => 'STAT',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? $this->status;
    }

    public function priorityLabel(): string
    {
        return self::priorityOptions()[$this->priority] ?? $this->priority;
    }

    public function strategyLabel(): string
    {
        return ClientSpaceStoreAssignment::strategyOptions()[$this->fulfillment_strategy]
            ?? $this->fulfillment_strategy;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_PICKING,
            self::STATUS_STAGED,
            self::STATUS_PARTIAL,
        ], true);
    }

    public function isStat(): bool
    {
        return $this->priority === self::PRIORITY_STAT;
    }

    public function isInpatient(): bool
    {
        return $this->fulfillment_strategy === ClientSpaceStoreAssignment::STRATEGY_BATCH_AND_STAGE;
    }

    public function isOutpatient(): bool
    {
        return $this->fulfillment_strategy === ClientSpaceStoreAssignment::STRATEGY_DISCRETE_IMMEDIATE;
    }

    public function isStageable(): bool
    {
        return $this->isInpatient() && in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_PICKING,
            self::STATUS_PARTIAL,
        ], true);
    }

    public function isStaged(): bool
    {
        return $this->status === self::STATUS_STAGED;
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function serviceDeliveryQueue(): BelongsTo
    {
        return $this->belongsTo(ServiceDeliveryQueue::class);
    }

    public function handoffToken(): BelongsTo
    {
        return $this->belongsTo(InventoryHandoffToken::class, 'handoff_token_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
