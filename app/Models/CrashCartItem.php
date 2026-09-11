<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrashCartItem extends Model
{
    protected $fillable = [
        'store_id',
        'item_id',
        'par_quantity',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'item_id' => 'integer',
        'par_quantity' => 'decimal:4',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
