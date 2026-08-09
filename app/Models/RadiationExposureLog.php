<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RadiationExposureLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'imaging_study_id',
        'client_id',
        'dose_area_product_gy',
        'exposure_time_ms',
        'kvp_metrics',
    ];

    protected $casts = [
        'dose_area_product_gy' => 'decimal:4',
        'exposure_time_ms' => 'integer',
    ];

    public function imagingStudy()
    {
        return $this->belongsTo(ImagingStudy::class);
    }

    public function scopeForClient($query, string $clientId)
    {
        return $query->where('client_id', $clientId);
    }
}
