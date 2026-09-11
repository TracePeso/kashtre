<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CdeRegistry extends Model
{
    protected $connection = 'clinical';

    protected $table = 'cde_registry';

    const TYPE_NUMERIC = 'NUMERIC';
    const TYPE_BOOLEAN = 'BOOLEAN';
    const TYPE_TEXT = 'TEXT';
    const TYPE_CODE = 'CODE';
    const TYPE_MULTI_COMPONENT = 'MULTI_COMPONENT';

    protected $fillable = [
        'business_id',
        'cde_code',
        'cde_name',
        'data_type',
        'base_uom_id',
        'normal_range_min',
        'normal_range_max',
        'critical_high',
        'critical_low',
        'physiological_min',
        'physiological_max',
        'is_graphable',
        'is_active',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'base_uom_id' => 'integer',
        'normal_range_min' => 'decimal:4',
        'normal_range_max' => 'decimal:4',
        'critical_high' => 'decimal:4',
        'critical_low' => 'decimal:4',
        'physiological_min' => 'decimal:4',
        'physiological_max' => 'decimal:4',
        'is_graphable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function baseUnit()
    {
        return $this->belongsTo(ClinicalUomMaster::class, 'base_uom_id');
    }

    /**
     * Business-specific row if one exists, else the system-wide default
     * (business_id null). The one lookup every caller should use instead
     * of querying the table directly.
     */
    public static function resolve(int $businessId, string $cdeCode): ?self
    {
        return static::query()
            ->where('cde_code', $cdeCode)
            ->where('is_active', true)
            ->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)->orWhereNull('business_id');
            })
            ->orderByRaw('business_id IS NULL') // business-specific row wins over the default
            ->first();
    }
}
