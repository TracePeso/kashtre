<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * Diagnostic report status lifecycle — SRD v6.1 Phase 8, API_GUIDE_V6.1
 * §Phase 8. Clinical's own review layer on top of a report already ingested
 * by the live LIMS/RIS webhook pipeline (unaffected, unrelated to this) —
 * correct/entered-in-error/cancel, each terminal once applied
 * (REPORT_STATUS_TERMINAL on a second attempt). A correction never rewrites
 * the original: it is preserved (marked AMENDED) and a new report row is
 * created superseding it.
 */
interface DiagnosticReportStatusGateway
{
    /**
     * @return array<string, mixed> the new CORRECTED report; carries
     *                               supersedes_report_id pointing at the
     *                               original (now AMENDED) report
     *
     * @throws \App\Services\Clinical\Api\Exceptions\ClinicalRuleRefusedException on the API driver
     */
    public function correct(ClinicalActor $actor, string $reportId, string $reason): array;

    /** @return array<string, mixed> */
    public function markEnteredInError(ClinicalActor $actor, string $reportId, string $reason): array;

    /** @return array<string, mixed> */
    public function cancel(ClinicalActor $actor, string $reportId, string $reason): array;
}
