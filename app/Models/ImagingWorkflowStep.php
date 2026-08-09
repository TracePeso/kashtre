<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * RIS Amendment v2.6, Chunk 1: the reusable workflow-step registry — an
 * admin can Create/Edit/Disable/Rename a step with no code change. A step
 * becomes a work queue via its assigned user pool (users()); Chunk 2 wires
 * steps into versioned per-protocol workflows instead of the hardcoded
 * ImagingStudy status machine.
 */
class ImagingWorkflowStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'step_code',
        'step_name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * The step's assigned user pool — this is what makes a step a queue.
     * user_id is a plain column, not a real FK (same cross-domain
     * decoupling rule as every other Main Module User reference in this
     * module), so this relation is defined explicitly rather than via a
     * FK-based belongsToMany.
     */
    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'imaging_workflow_step_users',
            'imaging_workflow_step_id',
            'user_id'
        )->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * System-wide steps (business_id null) plus any business-specific ones.
     */
    public function scopeAvailableToBusiness($query, ?int $businessId)
    {
        return $query->where(function ($q) use ($businessId) {
            $q->whereNull('business_id');

            if ($businessId) {
                $q->orWhere('business_id', $businessId);
            }
        });
    }
}
