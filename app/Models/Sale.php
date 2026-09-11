<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'business_id',
        'branch_id',
        'service_point_id',
        'invoice_id',
        'service_delivery_queue_id',
        'client_id',
        'item_id',
        'client_name',
        'invoice_number',
        'item_name',
        'quantity',
        'unit_price',
        'total_amount',
        'status',
        'processed_at',
        'status_changed_at',
        'processed_by',
        'metadata',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'status_changed_at' => 'datetime',
        'metadata' => 'array',
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Sale $sale) {
            $sale->uuid = $sale->uuid ?: (string) Str::uuid();
        });
    }

    /**
     * Record or update a sale from a service delivery queue item.
     */
    public static function recordFromQueue(ServiceDeliveryQueue $queue, string $status, ?int $userId = null): self
    {
        $queue->loadMissing(['client', 'invoice', 'item', 'servicePoint', 'business', 'branch']);

        $status = $status === 'completed' ? 'completed' : 'partially_done';

        $sale = static::firstOrNew([
            'service_delivery_queue_id' => $queue->id,
        ]);

        $quantity = (int) ($queue->quantity ?? 1);
        if ($quantity <= 0) {
            $quantity = 1;
        }

        $unitPrice = static::resolveUnitPrice($queue);
        $totalAmount = $unitPrice * $quantity;

        $sale->business_id = $queue->business_id;
        $sale->branch_id = $queue->branch_id;
        $sale->service_point_id = $queue->service_point_id;
        $sale->invoice_id = $queue->invoice_id;
        $sale->client_id = $queue->client_id;
        $sale->item_id = $queue->item_id;
        $sale->client_name = optional($queue->client)->full_name ?? optional($queue->client)->name ?? null;
        $sale->invoice_number = optional($queue->invoice)->invoice_number;
        $sale->item_name = $queue->item_name ?? optional($queue->item)->name ?? 'Unknown Item';
        $sale->quantity = $quantity;
        $sale->unit_price = $unitPrice;
        $sale->total_amount = $totalAmount;
        $sale->status = $status;
        $sale->processed_at = $sale->processed_at ?? now();
        $sale->status_changed_at = now();
        $sale->processed_by = $userId;
        $sale->metadata = [
            'queue_status' => $queue->status,
            'queued_at' => optional($queue->queued_at)->toIso8601String(),
            'completed_at' => optional($queue->completed_at)->toIso8601String(),
            'partially_done_at' => optional($queue->partially_done_at)->toIso8601String(),
            'notes' => $queue->notes,
        ];

        $sale->save();

        try {
            app(\App\Services\Inventory\InventorySaleConsumptionService::class)
                ->recordFromQueue($queue, $userId, $sale->id);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Inventory auto-consumption failed', [
                'queue_id' => $queue->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $sale;
    }

    protected static function resolveUnitPrice(ServiceDeliveryQueue $queue): float
    {
        if (!is_null($queue->price) && is_numeric($queue->price)) {
            return (float) $queue->price;
        }

        if ($queue->relationLoaded('item') || $queue->item) {
            $price = $queue->item->default_price ?? null;
            if (!is_null($price) && is_numeric($price)) {
                return (float) $price;
            }
        }

        return 0.0;
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function servicePoint(): BelongsTo
    {
        return $this->belongsTo(ServicePoint::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function serviceDeliveryQueue(): BelongsTo
    {
        return $this->belongsTo(ServiceDeliveryQueue::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}

