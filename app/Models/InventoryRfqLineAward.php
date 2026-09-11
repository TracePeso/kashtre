<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryRfqLineAward extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_order_id',
        'inventory_order_line_id',
        'supplier_id',
        'inventory_supplier_quotation_line_id',
        'awarded_quantity_suom',
        'unit_price',
        'line_total',
    ];

    protected $casts = [
        'awarded_quantity_suom' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function inventoryOrder(): BelongsTo
    {
        return $this->belongsTo(InventoryOrder::class);
    }

    public function inventoryOrderLine(): BelongsTo
    {
        return $this->belongsTo(InventoryOrderLine::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function quotationLine(): BelongsTo
    {
        return $this->belongsTo(InventorySupplierQuotationLine::class, 'inventory_supplier_quotation_line_id');
    }
}
