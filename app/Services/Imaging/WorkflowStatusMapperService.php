<?php

namespace App\Services\Imaging;

use App\Models\ImagingStudy;

/**
 * RIS Amendment v2.6, Chunk 3. Two independent, deliberately dumb setters —
 * WorkflowEngineService::completeStep() is what decides *which* statuses a
 * completed step maps to; this service only applies them.
 */
class WorkflowStatusMapperService
{
    /**
     * Reuses ImagingStudy::transitionTo() (widened to public for this)
     * rather than duplicating its status_history logging — every existing
     * worklist query, Blade @if($study->isStatus(...)) branch, and report
     * keeps working unmodified as long as $risStatus is one of this app's
     * existing status strings, which it always is for the backfilled
     * default workflow.
     */
    public function updateRisStatus(ImagingStudy $study, string $risStatus, ?int $userId = null, array $extra = []): void
    {
        $study->transitionTo($risStatus, $userId, $extra);
    }

    /**
     * Writes only the new main_module_status column — not pushed to any
     * real Main Module record yet (see the roadmap's grounding note: no
     * confirmed integration target exists today).
     */
    public function updateMainStatus(ImagingStudy $study, string $mainStatus): void
    {
        $study->update(['main_module_status' => $mainStatus]);
    }
}
