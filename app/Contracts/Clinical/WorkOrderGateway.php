<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * Ad-hoc ward tasks — API Integration Guide §10.5 ("re-site the cannula",
 * "chase consent"). Deliberately narrow: a laboratory or imaging request
 * placed here would skip the Translator Engine, the CDSS shield and
 * dispatch to LIMS/RIS, so no specimen barcode would exist and no result
 * would return — those go through OrdersGateway-equivalents
 * (`orders/laboratory`, `orders/imaging`), never here.
 *
 * A raw array, not a DTO — display-only, list-of-records shape.
 */
interface WorkOrderGateway
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function forPatient(ClinicalActor $actor, string $patientId): array;

    /**
     * @return array<string, mixed>
     */
    public function create(
        ClinicalActor $actor,
        string $patientId,
        string $visitId,
        string $orderName,
        ?int $assignedToUserId = null,
        ?string $assignedRoleCode = null,
        ?string $notes = null,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function transition(ClinicalActor $actor, int|string $workOrderId, string $status, ?string $notes = null): array;
}
