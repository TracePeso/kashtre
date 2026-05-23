<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrApprovalEvent extends Model
{
    protected $table = 'hr_approval_events';

    protected $fillable = [
        'approval_request_id',
        'approval_step_id',
        'actor_staff_uuid',
        'actor_name',
        'action',
        'from_status',
        'to_status',
        'comments',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function request()
    {
        return $this->belongsTo(HrApprovalRequest::class, 'approval_request_id');
    }

    public function step()
    {
        return $this->belongsTo(HrApprovalStep::class, 'approval_step_id');
    }
}
