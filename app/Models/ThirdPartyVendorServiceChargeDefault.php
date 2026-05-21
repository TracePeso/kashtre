<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThirdPartyVendorServiceChargeDefault extends Model
{
    protected $fillable = [
        'lower_bound',
        'upper_bound',
        'amount',
        'type',
        'sort_order',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'lower_bound' => 'decimal:2',
        'upper_bound' => 'decimal:2',
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (ThirdPartyVendorServiceChargeDefault $row): void {
            if ($row->upper_bound !== null && $row->lower_bound !== null) {
                if ($row->upper_bound <= $row->lower_bound) {
                    throw new \InvalidArgumentException('Upper bound must be greater than lower bound.');
                }
            }

            if ($row->type === 'percentage' && $row->amount > 100) {
                throw new \InvalidArgumentException('Percentage amount cannot exceed 100%.');
            }
        });
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getFormattedAmountAttribute(): string
    {
        if ($this->type === 'percentage') {
            return $this->amount.'%';
        }

        return 'UGX '.number_format((float) $this->amount, 2);
    }

    public function amountForSubtotal(float $subtotal): float
    {
        if ($this->type === 'fixed') {
            return (float) $this->amount;
        }

        if ($this->type === 'percentage') {
            return ($subtotal * (float) $this->amount) / 100;
        }

        return 0.0;
    }

    /**
     * @return array<int, self>
     */
    public static function activeOrdered(): array
    {
        return static::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('lower_bound')
            ->get()
            ->all();
    }

    public static function tierForSubtotal(float $subtotal): ?self
    {
        $subtotal = max(0, round($subtotal, 2));

        return static::query()
            ->where('is_active', true)
            ->where('lower_bound', '<=', $subtotal)
            ->where(function ($q) use ($subtotal): void {
                $q->whereNull('upper_bound')
                    ->orWhere('upper_bound', '>=', $subtotal);
            })
            ->orderByDesc('lower_bound')
            ->first();
    }
}
