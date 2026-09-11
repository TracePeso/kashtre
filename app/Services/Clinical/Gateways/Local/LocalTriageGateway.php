<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\TriageGateway;
use App\Support\Clinical\ClinicalActor;
use RuntimeException;

/**
 * CLINICAL_DRIVER=local: no scoring engine exists against this schema —
 * NEWS2/SATS calculation lives entirely in Clinical's calculated-indicator
 * engine (SRD §12.1), driven by formula dictionaries this driver does not
 * carry. Refusing loudly is more honest than returning a fabricated score
 * a clinician could act on.
 */
class LocalTriageGateway implements TriageGateway
{
    public function assess(ClinicalActor $actor, string $patientId, string $visitId, bool $announce = true): array
    {
        throw new RuntimeException('Triage acuity scoring is not available under the local clinical driver.');
    }
}
