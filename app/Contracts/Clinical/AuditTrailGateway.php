<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\AuditTrailEntry;
use App\Support\Clinical\ClinicalActor;

/**
 * The compliance-sensitive event stream for one patient — break-glass
 * grants, CDSS overrides, major-transition step completions. Read-only by
 * design: every row here is written elsewhere (capture, prescribe, grant,
 * complete-step), this only surfaces what already happened.
 */
interface AuditTrailGateway
{
    /**
     * @return array<int, AuditTrailEntry>
     */
    public function forPatient(ClinicalActor $actor, string $patientId, int $limit = 20): array;
}
