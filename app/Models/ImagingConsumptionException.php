<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * RIS Amendment v2.6, Chunk 6: a consumption attempt that couldn't
 * actually deplete stock — created by RadiologyRecipeEngine, resolved via
 * ConsumptionAttributionService::resolveException(). Operational data (an
 * actionable follow-up list), not module config.
 */
class ImagingConsumptionException extends Model
{
    use HasFactory;

    protected $fillable = [
        'imaging_study_id',
        'imaging_protocol_workflow_step_id',
        'exception_reason',
        'resolved',
        'resolved_by_user_id',
        'resolved_at',
    ];

    protected $casts = [
        'resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function study()
    {
        return $this->belongsTo(ImagingStudy::class, 'imaging_study_id');
    }

    public function protocolWorkflowStep()
    {
        return $this->belongsTo(ProtocolWorkflowStep::class, 'imaging_protocol_workflow_step_id');
    }

    public function scopeUnresolved($query)
    {
        return $query->where('resolved', false);
    }

    /**
     * resolved_by_user_id is a plain column, not a real FK (same
     * cross-domain decoupling rule as every other Main Module User
     * reference in this module) — a lookup, not an Eloquent relation.
     */
    public function resolveResolvedByUser(): ?User
    {
        return $this->resolved_by_user_id ? User::find($this->resolved_by_user_id) : null;
    }
}
