<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalMedicationOrder extends Model
{
    protected $connection = 'clinical';

    protected $table = 'clinical_medication_orders';

    public $timestamps = false;

    const STATUS_ACTIVE = 'ACTIVE';
    const STATUS_DISCONTINUED = 'DISCONTINUED';
    const STATUS_COMPLETED = 'COMPLETED';

    protected $fillable = [
        'business_id',
        'branch_id',
        'client_id',
        'visit_id',
        'ordering_user_id',
        'drug_code',
        'drug_display_name',
        'dose_amount',
        'dose_uom_id',
        'route_code',
        'frequency_code',
        'start_at',
        'end_at',
        'status',
        'is_external',
        'external_referral_path',
        'cdss_override_reason',
        'created_at',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'branch_id' => 'integer',
        'ordering_user_id' => 'integer',
        'dose_amount' => 'decimal:4',
        'dose_uom_id' => 'integer',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'is_external' => 'boolean',
        'created_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'is_external' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (ClinicalMedicationOrder $order) {
            $order->created_at ??= now();
        });
    }

    public function doses()
    {
        return $this->hasMany(ClinicalMarDose::class, 'medication_order_id');
    }
}
