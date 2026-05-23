<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ApprovalWorkflow extends Model
{
    use SoftDeletes;

    protected $table = 'hr_approval_workflows';

    protected $fillable = [
        'uuid', 'organization_id', 'organizational_unit_id', 'approval_category', 'discipline_title', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
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

    public function organizationalUnit()
    {
        return $this->belongsTo(HrOrganizationalUnit::class, 'organizational_unit_id');
    }

    public function approvers()
    {
        return $this->hasMany(ApprovalWorkflowApprover::class, 'approval_workflow_id')->orderBy('approver_level')->orderBy('sort_order');
    }

    public function approvalRequests()
    {
        return $this->hasMany(HrApprovalRequest::class, 'approval_workflow_id');
    }

    public function primaryApprovers()
    {
        return $this->approvers()->where('approver_level', 'primary');
    }

    public function secondaryApprovers()
    {
        return $this->approvers()->where('approver_level', 'secondary');
    }

    public function tertiaryApprovers()
    {
        return $this->approvers()->where('approver_level', 'tertiary');
    }

    public function syncApprovers(string $level, array $staffData): void
    {
        $this->approvers()->where('approver_level', $level)->delete();

        foreach ($staffData as $index => $staff) {
            $this->approvers()->create([
                'approver_level' => $level,
                'approver_staff_uuid' => $staff['uuid'],
                'approver_name' => $staff['name'],
                'sort_order' => $index,
            ]);
        }
    }
}
