<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalCareAssignment extends Model
{
    protected $connection = 'clinical';

    protected $table = 'clinical_care_assignments';

    const MODEL_INDIVIDUAL = 'INDIVIDUAL';
    const MODEL_ROLE = 'ROLE';
    const MODEL_TEAM = 'TEAM';
    const MODEL_HYBRID = 'HYBRID';

    protected $fillable = [
        'business_id',
        'branch_id',
        'client_id',
        'visit_id',
        'assignment_model',
        'primary_doctor_user_id',
        'primary_nurse_user_id',
        'assigned_team_id',
        'assigned_role_code',
        'is_active',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'branch_id' => 'integer',
        'primary_doctor_user_id' => 'integer',
        'primary_nurse_user_id' => 'integer',
        'assigned_team_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function assignedTeam()
    {
        return $this->belongsTo(ClinicalCareTeam::class, 'assigned_team_id');
    }
}
