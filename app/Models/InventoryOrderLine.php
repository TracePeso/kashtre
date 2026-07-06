<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryOrderLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_order_id',
        'item_id',
        'supplier_id',
        'daily_average_suom',
        'lead_time_days',
        'system_quantity_suom',
        'current_stock_suom',
        'stock_days_at_order',
        'days_left_at_order',
        'order_days',
        'base_suggested_quantity_suom',
        'peak_consumption_increase_percent',
        'peak_impact_percent',
        'suggested_quantity_suom',
        'order_quantity_suom',
        'received_quantity_suom',
        'order_quantity_ouom',
        'unit_price',
        'line_total',
    ];

    protected $casts = [
        'daily_average_suom' => 'decimal:4',
        'system_quantity_suom' => 'decimal:4',
        'current_stock_suom' => 'decimal:4',
        'stock_days_at_order' => 'decimal:1',
        'days_left_at_order' => 'decimal:1',
        'order_days' => 'decimal:4',
        'base_suggested_quantity_suom' => 'decimal:4',
        'peak_consumption_increase_percent' => 'decimal:4',
        'peak_impact_percent' => 'decimal:4',
        'suggested_quantity_suom' => 'decimal:4',
        'order_quantity_suom' => 'decimal:4',
        'received_quantity_suom' => 'decimal:4',
        'order_quantity_ouom' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(InventoryOrder::class, 'inventory_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
