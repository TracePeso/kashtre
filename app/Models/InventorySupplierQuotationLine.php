<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySupplierQuotationLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_supplier_quotation_id',
        'inventory_order_line_id',
        'item_id',
        'quoted_quantity_suom',
        'unit_price',
        'line_total',
    ];

    protected $casts = [
        'quoted_quantity_suom' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(InventorySupplierQuotation::class, 'inventory_supplier_quotation_id');
    }

    public function inventoryOrderLine(): BelongsTo
    {
        return $this->belongsTo(InventoryOrderLine::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
