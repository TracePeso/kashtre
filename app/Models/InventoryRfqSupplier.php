<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryRfqSupplier extends Model
{
    protected $table = 'inventory_rfq_suppliers';

    protected $fillable = [
        'inventory_order_id',
        'supplier_id',
        'invited_at',
        'rfq_sent_at',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
        'rfq_sent_at' => 'datetime',
    ];

    public function inventoryOrder(): BelongsTo
    {
        return $this->belongsTo(InventoryOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
