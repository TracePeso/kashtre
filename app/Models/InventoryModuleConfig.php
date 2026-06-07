<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryModuleConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'is_active',
        'description',
        'fixed_daily_average_suom',
        'safety_stock_days',
        'buffer_stock_days',
        'notification_to_order_days',
        'period_of_order_days',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'fixed_daily_average_suom' => 'decimal:4',
        'safety_stock_days' => 'decimal:2',
        'buffer_stock_days' => 'decimal:2',
        'notification_to_order_days' => 'decimal:2',
        'period_of_order_days' => 'decimal:2',
    ];

    /**
     * Daily usage for stock calculations: item-level value, or fixed average when item usage is zero.
     */
    public function effectiveDailyUsageSuom(?float $itemDailyUsageSuom): float
    {
        $usage = (float) ($itemDailyUsageSuom ?? 0);

        if ($usage > 0) {
            return $usage;
        }

        return (float) ($this->fixed_daily_average_suom ?? 0);
    }

    public function safetyStockSuom(?float $itemDailyUsageSuom): float
    {
        return round(
            $this->effectiveDailyUsageSuom($itemDailyUsageSuom) * (float) ($this->safety_stock_days ?? 0),
            4
        );
    }

    public function bufferStockSuom(?float $itemDailyUsageSuom): float
    {
        return round(
            $this->effectiveDailyUsageSuom($itemDailyUsageSuom) * (float) ($this->buffer_stock_days ?? 0),
            4
        );
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForBusiness($query, int $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    public function approvers()
    {
        return $this->hasMany(InventoryModuleApprover::class)->orderBy('approval_order');
    }
}
