<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryPurchaseOrderLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_purchase_order_id',
        'inventory_order_line_id',
        'item_id',
        'quantity_suom',
        'received_quantity_suom',
        'unit_price',
        'line_total',
    ];

    protected $casts = [
        'quantity_suom' => 'decimal:4',
        'received_quantity_suom' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(InventoryPurchaseOrder::class, 'inventory_purchase_order_id');
    }

    public function inventoryOrderLine(): BelongsTo
    {
        return $this->belongsTo(InventoryOrderLine::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function remainingQuantitySuom(): float
    {
        return max(0, round((float) $this->quantity_suom - (float) $this->received_quantity_suom, 4));
    }
}
