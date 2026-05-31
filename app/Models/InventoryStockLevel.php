<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryStockLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'item_id',
        'quantity_suom',
        'daily_usage_suom',
        'last_purchase_price',
    ];

    protected $casts = [
        'quantity_suom' => 'decimal:4',
        'daily_usage_suom' => 'decimal:4',
        'last_purchase_price' => 'decimal:2',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
