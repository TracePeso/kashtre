<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryOrder extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';

    public const BUDGET_MODE_DAYS = 'days';
    public const BUDGET_MODE_AMOUNT = 'amount';

    protected $fillable = [
        'business_id',
        'store_id',
        'order_number',
        'status',
        'importance_filter',
        'budget_mode',
        'budget_value',
        'moving_average_days',
        'notes',
        'created_by_user_id',
        'submitted_at',
    ];

    protected $casts = [
        'budget_value' => 'decimal:2',
        'moving_average_days' => 'integer',
        'submitted_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryOrderLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function orderTotal(): float
    {
        return round((float) $this->lines()->sum('line_total'), 2);
    }
}
