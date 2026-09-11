<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * RIS Amendment v2.6, Chunk 2: a versioned, ordered composition of
 * ImagingWorkflowStep rows for one ImagingProtocol — what Chunk 3's
 * WorkflowEngineService::startWorkflow() resolves and walks a study
 * through, replacing the old hardcoded ImagingStudy status sequence.
 */
class ProtocolWorkflow extends Model
{
    use HasFactory;

    protected $table = 'imaging_protocol_workflows';

    protected $fillable = [
        'imaging_protocol_id',
        'workflow_name',
        'workflow_version',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function protocol()
    {
        return $this->belongsTo(ImagingProtocol::class, 'imaging_protocol_id');
    }

    public function steps()
    {
        return $this->hasMany(ProtocolWorkflowStep::class, 'imaging_protocol_workflow_id')
            ->orderBy('sequence_no');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
