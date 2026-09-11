<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalBed extends Model
{
    protected $connection = 'clinical';

    protected $table = 'clinical_beds';

    const STATE_AVAILABLE = 'AVAILABLE';
    const STATE_OCCUPIED = 'OCCUPIED';
    const STATE_RESERVED = 'RESERVED';

    protected $fillable = [
        'ward_id',
        'bed_code',
        'operational_state',
        'current_client_id',
        'current_visit_id',
        'is_overflow',
    ];

    protected $casts = [
        'ward_id' => 'integer',
        'is_overflow' => 'boolean',
    ];

    protected $attributes = [
        'operational_state' => self::STATE_AVAILABLE,
    ];

    public function ward()
    {
        return $this->belongsTo(ClinicalWard::class, 'ward_id');
    }
}
