<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalMarDose extends Model
{
    protected $connection = 'clinical';

    protected $table = 'clinical_mar_doses';

    public $timestamps = false;

    const STATUS_DUE = 'DUE';
    const STATUS_ADMINISTERED = 'ADMINISTERED';
    const STATUS_MISSED = 'MISSED';
    const STATUS_HELD = 'HELD';

    protected $fillable = [
        'medication_order_id',
        'scheduled_at',
        'status',
        'administered_by_user_id',
        'administered_at',
        'reason_code',
        'notes',
    ];

    protected $casts = [
        'medication_order_id' => 'integer',
        'scheduled_at' => 'datetime',
        'administered_by_user_id' => 'integer',
        'administered_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_DUE,
    ];

    public function medicationOrder()
    {
        return $this->belongsTo(ClinicalMedicationOrder::class, 'medication_order_id');
    }
}
