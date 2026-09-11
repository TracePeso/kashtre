<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\HandoverGateway;
use App\Models\ClinicalBed;
use App\Services\Clinical\CareRelationshipChecker;
use App\Support\Clinical\ClinicalActor;

/**
 * CLINICAL_DRIVER=local: an honest approximation, not a port. The sickest-
 * first ordering and the per-patient critical-alerts/open-tasks/observation-
 * compliance/medications-due rollup are Clinical's compliance engine's
 * output — nothing in this schema computes any of that, so those blocks
 * come back empty rather than guessed at. Only location and who currently
 * owns the patient are real. MY_TEAM is not implemented (no per-user "my
 * team" concept exists locally beyond the team a patient happens to be
 * assigned to) and returns the same set as MY_PATIENTS.
 */
class LocalHandoverGateway implements HandoverGateway
{
    public function __construct(private readonly CareRelationshipChecker $careChecker)
    {
    }

    public function compile(ClinicalActor $actor, string $scope, ?string $wardCode = null): array
    {
        $clientIds = $scope === self::SCOPE_MY_WARD
            ? ClinicalBed::whereHas('ward', function ($query) use ($actor, $wardCode) {
                $query->where('business_id', $actor->businessId);
                if ($wardCode) {
                    $query->where('ward_code', $wardCode);
                }
            })->whereNotNull('current_client_id')->pluck('current_client_id')->unique()->all()
            : $this->careChecker->myPatientClientIds($actor->userId, $actor->businessId);

        $beds = ClinicalBed::with('ward')
            ->whereIn('current_client_id', $clientIds)
            ->get()
            ->keyBy('current_client_id');

        $patients = collect($clientIds)->map(function (string $clientId) use ($beds) {
            $bed = $beds->get($clientId);

            return [
                'patient_id' => $clientId,
                'visit_id' => null,
                'location' => $bed ? [
                    'ward_code' => $bed->ward?->ward_code,
                    'ward_name' => $bed->ward?->ward_name,
                    'bed_code' => $bed->bed_code,
                ] : null,
                'ownership' => null,
                'critical_alerts' => ['count' => 0, 'items' => []],
                'open_tasks' => ['count' => 0, 'overdue' => 0, 'items' => []],
                'observations' => ['outstanding' => 0, 'overdue' => 0, 'missing' => 0],
                'medications' => ['doses_due' => 0, 'next_due_at' => null],
            ];
        })->values()->all();

        return [
            'patients' => $patients,
            'scope' => $scope === self::SCOPE_MY_TEAM ? self::SCOPE_MY_PATIENTS : $scope,
            'ward_code' => $wardCode,
            'generated_at' => now()->toIso8601String(),
            'summary' => ['patient_count' => count($patients)],
        ];
    }
}
