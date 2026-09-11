<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecoveryRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'imaging_study_id',
        'monitoring_started_at',
        'vital_signs_notes',
        'discharge_criteria_met',
        'discharge_cleared_at',
        'discharge_cleared_by_user_id',
        'discharge_notes',
    ];

    protected $casts = [
        'monitoring_started_at' => 'datetime',
        'discharge_criteria_met' => 'boolean',
        'discharge_cleared_at' => 'datetime',
    ];

    public function imagingStudy()
    {
        return $this->belongsTo(ImagingStudy::class);
    }

    public function resolveDischargedBy(): ?User
    {
        return $this->discharge_cleared_by_user_id ? User::find($this->discharge_cleared_by_user_id) : null;
    }

    public function isDischarged(): bool
    {
        return $this->discharge_cleared_at !== null;
    }

    /**
     * Pillar 16: the system locks the final documentation step until this
     * has been logged — so clearing for discharge requires criteria to
     * already be confirmed met, not just asserted in the same request.
     */
    public function clearForDischarge(int $userId, ?string $notes = null): void
    {
        if (! $this->discharge_criteria_met) {
            throw new \RuntimeException('Discharge criteria must be confirmed met before clearing for discharge.');
        }

        if ($this->isDischarged()) {
            throw new \RuntimeException('This recovery record has already been cleared for discharge.');
        }

        $this->update([
            'discharge_cleared_at' => now(),
            'discharge_cleared_by_user_id' => $userId,
            'discharge_notes' => $notes,
        ]);

        // Pillar 12.1: recovery discharge isn't an ImagingStudy status, so
        // this is the only hook point for protocols configured to deplete
        // consumption at RECOVERY_COMPLETE.
        $this->imagingStudy?->triggerConsumptionIfDue(ImagingProtocol::TRIGGER_RECOVERY_COMPLETE, $userId);
    }
}
