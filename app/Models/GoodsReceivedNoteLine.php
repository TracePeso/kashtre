<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoodsReceivedNoteLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'goods_received_note_id',
        'item_id',
        'inventory_order_line_id',
        'category',
        'item_name',
        'quantity',
        'batch_number',
        'expiry_date',
        'duom',
        'purchase_price',
        'suom',
        'sale_units_per_purchase_unit',
        'sale_units_purchased',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'purchase_price' => 'decimal:2',
        'sale_units_per_purchase_unit' => 'decimal:4',
        'sale_units_purchased' => 'decimal:4',
        'expiry_date' => 'date',
    ];

    public function goodsReceivedNote()
    {
        return $this->belongsTo(GoodsReceivedNote::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function inventoryOrderLine()
    {
        return $this->belongsTo(InventoryOrderLine::class);
    }

    public static function calculateSaleUnitsPurchased(float $quantity, float $saleUnitsPerPurchaseUnit): float
    {
        return round($quantity * $saleUnitsPerPurchaseUnit, 4);
    }
}
