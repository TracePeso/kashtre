<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrApprovalStep extends Model
{
    protected $table = 'hr_approval_steps';

    protected $fillable = [
        'approval_request_id',
        'approver_level',
        'approver_staff_uuid',
        'approver_name',
        'status',
        'is_current',
        'sort_order',
        'acted_at',
        'comments',
    ];

    protected $casts = [
        'is_current' => 'boolean',
        'acted_at' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(HrApprovalRequest::class, 'approval_request_id');
    }

    public function events()
    {
        return $this->hasMany(HrApprovalEvent::class, 'approval_step_id');
    }
}
