<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImagingReadinessCheckType extends Model
{
    use HasFactory;

    const CATEGORY_PREPARATION = 'PREPARATION';
    const CATEGORY_READINESS = 'READINESS';

    const CATEGORIES = [
        self::CATEGORY_PREPARATION,
        self::CATEGORY_READINESS,
    ];

    protected $fillable = [
        'business_id',
        'code',
        'label',
        'category',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * System-wide check types (business_id null) plus any business-specific ones.
     */
    public function scopeAvailableToBusiness($query, ?int $businessId)
    {
        return $query->where(function ($q) use ($businessId) {
            $q->whereNull('business_id');

            if ($businessId) {
                $q->orWhere('business_id', $businessId);
            }
        });
    }
}
