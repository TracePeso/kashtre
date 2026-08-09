<?php

namespace App\Livewire\Clinical;

use App\Models\ClinicalBreakGlassLog;
use App\Models\ClinicalProcessStepExecution;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Lean audit polish, mirroring ImagingAuditService/ListImagingAuditLog —
 * surfaces the two most compliance-sensitive event streams already being
 * recorded (break-glass grants, major-transition step executions) rather
 * than building a new unified event-log table: every insert-only,
 * attributed row this module already writes (CdeObservation,
 * ClinicalConsumptionEvent, ClinicalMedicationOrder, ...) already IS an
 * audit trail by construction — this view just makes the two most
 * security-relevant ones reviewable in one place.
 */
class AuditTrail extends Component
{
    public string $clientId;

    public function mount(string $clientId): void
    {
        abort_unless(in_array('View Clinical Audit Trail', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
    }

    public function render()
    {
        $businessId = Auth::user()->business_id;

        return view('livewire.clinical.audit-trail', [
            'breakGlassLogs' => ClinicalBreakGlassLog::where('business_id', $businessId)
                ->where('client_id', $this->clientId)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(),
            'stepExecutions' => ClinicalProcessStepExecution::whereHas('execution', function ($query) use ($businessId) {
                $query->where('business_id', $businessId)->where('client_id', $this->clientId);
            })
                ->with('step')
                ->orderByDesc('completed_at')
                ->limit(20)
                ->get(),
        ]);
    }
}
