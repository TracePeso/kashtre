<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\DiagnosticReportStatusGateway;
use App\Support\Clinical\ClinicalActor;
use RuntimeException;

/**
 * CLINICAL_DRIVER=local: diagnostic report status corrections (SRD v6.1
 * Phase 8) are Clinical-owned — lab/imaging ingestion is out of scope for
 * the local tables entirely, same reasoning as LocalCriticalAlertsGateway.
 */
class LocalDiagnosticReportStatusGateway implements DiagnosticReportStatusGateway
{
    public function correct(ClinicalActor $actor, string $reportId, string $reason): array
    {
        $this->refuse();
    }

    public function markEnteredInError(ClinicalActor $actor, string $reportId, string $reason): array
    {
        $this->refuse();
    }

    public function cancel(ClinicalActor $actor, string $reportId, string $reason): array
    {
        $this->refuse();
    }

    private function refuse(): never
    {
        throw new RuntimeException('Diagnostic report status corrections (v6.1 Phase 8) are only available under CLINICAL_DRIVER=api.');
    }
}
