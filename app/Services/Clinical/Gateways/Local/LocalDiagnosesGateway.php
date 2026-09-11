<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\DiagnosesGateway;
use App\Models\ClinicalCondition;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\ClinicalDiagnosis;

/**
 * CLINICAL_DRIVER=local: the problem list against clinical_conditions — the
 * behaviour the panel had before the gateway existed, moved out of the
 * component.
 */
class LocalDiagnosesGateway implements DiagnosesGateway
{
    public function forPatient(ClinicalActor $actor, string $patientId): array
    {
        return ClinicalCondition::query()
            ->where('business_id', $actor->businessId)
            ->where('client_id', $patientId)
            ->orderByDesc('recorded_at')
            ->get()
            ->map(fn (ClinicalCondition $c) => ClinicalDiagnosis::fromModel($c))
            ->all();
    }

    public function record(
        ClinicalActor $actor,
        string $patientId,
        string $description,
        ?string $icd11Code = null,
        ?string $visitId = null,
    ): void {
        ClinicalCondition::create([
            'business_id' => $actor->businessId,
            'branch_id' => $actor->branchId,
            'client_id' => $patientId,
            'visit_id' => $visitId,
            'icd11_code' => $icd11Code ?: null,
            'description' => $description,
            'recorded_by_user_id' => $actor->userId,
            'recorded_at' => now(),
        ]);
    }

    public function allowsUncodedDiagnosis(): bool
    {
        return true;
    }
}
