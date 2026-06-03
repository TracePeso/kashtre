<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStockMovement extends Model
{
    use HasFactory;

    public const TYPE_GRN_RECEIPT = 'grn_receipt';
    public const TYPE_STOCK_COUNT = 'stock_count';
    public const TYPE_CONSUMPTION = 'consumption';

    protected $fillable = [
        'business_id',
        'item_id',
        'store_id',
        'movement_type',
        'quantity_delta',
        'balance_after',
        'unit_price',
        'line_valuation',
        'balance_valuation',
        'goods_received_note_id',
        'goods_received_note_line_id',
        'reference_label',
        'recorded_by_user_id',
        'occurred_at',
    ];

    protected $casts = [
        'quantity_delta' => 'decimal:4',
        'balance_after' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'line_valuation' => 'decimal:2',
        'balance_valuation' => 'decimal:2',
        'occurred_at' => 'datetime',
    ];

    public function receiptPurchasePrice(): float
    {
        return (float) ($this->unit_price ?? 0);
    }

    public function receiptValuation(): float
    {
        if ($this->line_valuation !== null) {
            return (float) $this->line_valuation;
        }

        return round(abs((float) $this->quantity_delta) * $this->receiptPurchasePrice(), 2);
    }

    public function stockValueAfter(): float
    {
        if ($this->balance_valuation !== null) {
            return (float) $this->balance_valuation;
        }

        return round((float) $this->balance_after * $this->receiptPurchasePrice(), 2);
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

    public function goodsReceivedNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivedNote::class);
    }

    public function goodsReceivedNoteLine(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivedNoteLine::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function movementTypeLabel(): string
    {
        return match ($this->movement_type) {
            self::TYPE_GRN_RECEIPT => 'GRN receipt',
            self::TYPE_STOCK_COUNT => 'Stock count adjustment',
            self::TYPE_CONSUMPTION => 'Consumption',
            default => ucfirst(str_replace('_', ' ', $this->movement_type)),
        };
    }
}
