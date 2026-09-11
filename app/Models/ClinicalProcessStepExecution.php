<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalProcessStepExecution extends Model
{
    protected $connection = 'clinical';

    protected $table = 'clinical_process_step_executions';

    public $timestamps = false;

    const STATUS_COMPLETED = 'COMPLETED';
    const STATUS_SKIPPED = 'SKIPPED';

    protected $fillable = [
        'execution_id',
        'step_id',
        'status',
        'completed_by_user_id',
        'completed_at',
        'override_reason',
        'notes',
    ];

    protected $casts = [
        'execution_id' => 'integer',
        'step_id' => 'integer',
        'completed_by_user_id' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function step()
    {
        return $this->belongsTo(ClinicalProcessStep::class, 'step_id');
    }

    public function execution()
    {
        return $this->belongsTo(ClinicalProcessExecution::class, 'execution_id');
    }
}
