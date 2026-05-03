<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThirdPartyVendorServiceCharge extends Model
{
    protected $fillable = [
        'business_id',
        'insurance_company_id',
        'lower_bound',
        'upper_bound',
        'amount',
        'type',
        'is_active',
        'sort_order',
        'description',
        'created_by',
    ];

    protected $casts = [
        'lower_bound' => 'decimal:2',
        'upper_bound' => 'decimal:2',
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (ThirdPartyVendorServiceCharge $row): void {
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

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function insuranceCompany(): BelongsTo
    {
        return $this->belongsTo(InsuranceCompany::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getFormattedAmountAttribute(): string
    {
        if ($this->type === 'percentage') {
            return $this->amount.'%';
        }

        return 'UGX '.number_format((float) $this->amount, 2);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForBusiness($query, int $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    /**
     * Pick the active tier for an invoice subtotal (highest lower_bound that still applies).
     * Open-ended tiers use upper_bound = null.
     */
    /**
     * Vendor-specific tiers override the clinic-wide (all vendors) schedule when present.
     */
    public static function tierForSubtotal(int $businessId, float $subtotal, ?int $insuranceCompanyId = null): ?self
    {
        $matchingTier = function ($query) use ($subtotal) {
            return $query->where('is_active', true)
                ->where('lower_bound', '<=', $subtotal)
                ->where(function ($q) use ($subtotal): void {
                    $q->whereNull('upper_bound')
                        ->orWhere('upper_bound', '>=', $subtotal);
                })
                ->orderByDesc('lower_bound');
        };

        if ($insuranceCompanyId !== null) {
            $vendorTier = $matchingTier(
                static::query()
                    ->where('business_id', $businessId)
                    ->where('insurance_company_id', $insuranceCompanyId)
            )->first();

            if ($vendorTier !== null) {
                return $vendorTier;
            }
        }

        return $matchingTier(
            static::query()
                ->where('business_id', $businessId)
                ->whereNull('insurance_company_id')
        )->first();
    }
}
