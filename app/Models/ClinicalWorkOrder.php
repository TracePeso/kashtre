<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalWorkOrder extends Model
{
    protected $connection = 'clinical';

    protected $table = 'clinical_work_orders';

    public $timestamps = false;

    const STATUS_PENDING = 'PENDING';
    const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    const STATUS_COMPLETED = 'COMPLETED';
    const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'business_id',
        'branch_id',
        'client_id',
        'visit_id',
        'order_type',
        'ordering_user_id',
        'assigned_to_user_id',
        'assigned_role_code',
        'status',
        'external_module',
        'external_reference',
        'created_at',
        'completed_at',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'branch_id' => 'integer',
        'ordering_user_id' => 'integer',
        'assigned_to_user_id' => 'integer',
        'created_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected static function booted(): void
    {
        static::creating(function (ClinicalWorkOrder $order) {
            $order->created_at ??= now();
        });
    }
}
