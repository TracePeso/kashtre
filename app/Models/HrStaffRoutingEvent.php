<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HrStaffRoutingEvent extends Model
{
    protected $fillable = [
        'uuid',
        'staff_assignment_id',
        'organization_id',
        'from_unit_id',
        'to_unit_id',
        'routed_by_user_id',
        'routed_by_staff_uuid',
        'from_status',
        'to_status',
        'routed_at',
    ];

    protected $casts = [
        'routed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? (string) Str::uuid();
        });
    }

    public function staffAssignment()
    {
        return $this->belongsTo(StaffAssignment::class);
    }

    public function fromUnit()
    {
        return $this->belongsTo(HrOrganizationalUnit::class, 'from_unit_id');
    }

    public function toUnit()
    {
        return $this->belongsTo(HrOrganizationalUnit::class, 'to_unit_id');
    }
}
