<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflowApprover extends Model
{
    protected $table = 'hr_approval_workflow_approvers';

    protected $fillable = [
        'approval_workflow_id', 'approver_level', 'approver_staff_uuid',
        'approver_name', 'sort_order',
    ];

    public function workflow()
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }
}
