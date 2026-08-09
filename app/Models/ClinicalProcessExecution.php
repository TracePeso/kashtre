<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalProcessExecution extends Model
{
    protected $connection = 'clinical';

    protected $table = 'clinical_process_executions';

    public $timestamps = false;

    const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    const STATUS_COMPLETED = 'COMPLETED';
    const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'business_id',
        'branch_id',
        'client_id',
        'visit_id',
        'process_id',
        'status',
        'current_step_id',
        'initiation_note',
        'started_by_user_id',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'branch_id' => 'integer',
        'process_id' => 'integer',
        'current_step_id' => 'integer',
        'started_by_user_id' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_IN_PROGRESS,
    ];

    public function process()
    {
        return $this->belongsTo(ClinicalProcess::class, 'process_id');
    }

    public function currentStep()
    {
        return $this->belongsTo(ClinicalProcessStep::class, 'current_step_id');
    }

    public function stepExecutions()
    {
        return $this->hasMany(ClinicalProcessStepExecution::class, 'execution_id');
    }
}
