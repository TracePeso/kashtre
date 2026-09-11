<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImagingCriticalFindingType extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'code',
        'label',
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

    /**
     * System-wide finding types (business_id null) plus any business-specific ones.
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
