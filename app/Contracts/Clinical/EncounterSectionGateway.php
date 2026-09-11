<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * MDT co-signing of a shared encounter workspace — API Integration Guide
 * §10.1. Each provider attests to their own section of the same encounter,
 * concurrently: a diabetic or oncology clinic where nutrition, nursing and
 * the consultant each sign a distinct section rather than one person
 * charting for everyone.
 *
 * The signer needs an ordinary care relationship, same as any chart access
 * — a specialist not yet on the chart is refused and must be added
 * CO_MANAGING first (CareAccessGateway::assign()). Signing the same
 * section again updates the attestation rather than duplicating it.
 * Withdrawal is a state, not a delete, and only the signatory may withdraw
 * their own signature.
 *
 * No local equivalent — this is a Clinical-only concept with nothing in
 * the local schema to back it.
 */
interface EncounterSectionGateway
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function forPatient(ClinicalActor $actor, string $patientId, ?string $visitId = null, bool $includeWithdrawn = false): array;

    /**
     * @return array<string, mixed>
     */
    public function sign(
        ClinicalActor $actor,
        string $patientId,
        string $visitId,
        string $sectionCode,
        string $sectionName,
        ?string $attestationNote = null,
    ): array;

    public function withdraw(ClinicalActor $actor, string $patientId, int|string $signatureId, string $reason): void;
}
