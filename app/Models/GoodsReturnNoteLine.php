<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReturnNoteLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'goods_return_note_id',
        'item_id',
        'quantity_suom',
        'batch_number',
        'unit_price',
    ];

    protected $casts = [
        'quantity_suom' => 'decimal:4',
        'unit_price' => 'decimal:2',
    ];

    public function goodsReturnNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReturnNote::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
