<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class HrDutyRosterEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'organization_id',
        'duty_roster_id',
        'roster_date',
        'staff_assignment_id',
        'staff_uuid',
        'staff_name',
        'staff_cadre',
        'shift_type_id',
        'notes',
    ];

    protected $casts = [
        'roster_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->uuid = $model->uuid ?? (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function dutyRoster()
    {
        return $this->belongsTo(HrDutyRoster::class, 'duty_roster_id');
    }

    public function staffAssignment()
    {
        return $this->belongsTo(StaffAssignment::class, 'staff_assignment_id');
    }

    public function shiftType()
    {
        return $this->belongsTo(ShiftType::class, 'shift_type_id');
    }

    public function sourceOpenShifts()
    {
        return $this->hasMany(HrOpenShift::class, 'source_duty_roster_entry_id');
    }
}
