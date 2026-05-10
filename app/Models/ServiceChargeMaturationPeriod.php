<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceChargeMaturationPeriod extends Model
{
    protected $fillable = [
        'business_id',
        'payment_method',
        'maturation_days',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'maturation_days' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getPaymentMethodNameAttribute(): string
    {
        $methodNames = [
            'insurance' => 'Insurance',
            'credit_arrangement' => 'Credit Arrangement',
            'mobile_money' => 'Mobile Money',
            'v_card' => 'V Card (Virtual Card)',
            'p_card' => 'P Card (Physical Card)',
            'bank_transfer' => 'Bank Transfer',
            'cash' => 'Cash',
        ];

        return $methodNames[$this->payment_method] ?? ucfirst(str_replace('_', ' ', $this->payment_method));
    }

    public function getFormattedMaturationPeriodAttribute(): string
    {
        return $this->maturation_days.' day'.($this->maturation_days > 1 ? 's' : '');
    }

    public static function defaultMaturationDays(string $paymentMethod): int
    {
        return MaturationSystemDefault::resolveServiceChargeDays($paymentMethod);
    }

    public static function resolveMaturationDays(int $businessId, string $paymentMethod): int
    {
        $period = static::query()
            ->where('business_id', $businessId)
            ->where('payment_method', $paymentMethod)
            ->first();

        if ($period !== null && $period->is_active) {
            return (int) $period->maturation_days;
        }

        return static::defaultMaturationDays($paymentMethod);
    }

    /**
     * @return list<string>
     */
    public static function activePaymentMethodsForBusiness(int $businessId): array
    {
        $methods = MaturationSystemDefault::allowedPaymentMethods();
        if ($methods === []) {
            return [];
        }

        $configured = static::query()
            ->where('business_id', $businessId)
            ->get()
            ->keyBy('payment_method');

        $active = [];
        foreach ($methods as $method) {
            $row = $configured->get($method);
            if ($row === null || $row->is_active) {
                $active[] = $method;
            }
        }

        foreach ($configured as $method => $row) {
            if (! in_array($method, $methods, true) && $row->is_active) {
                $active[] = $method;
            }
        }

        return array_values(array_unique($active));
    }
}
