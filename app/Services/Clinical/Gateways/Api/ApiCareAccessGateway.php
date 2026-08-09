<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\CareAccessGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Services\Clinical\Api\ClinicalRequestContext;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Log;

/**
 * CLINICAL_DRIVER=api: care relationships and break-glass — §10.1.
 *
 * Everything here is advisory. Clinical re-runs the care-relationship gate on
 * every patient-scoped request regardless of what this returned, so a `true`
 * from hasActiveRelationship() is a rendering hint, not a grant. The security
 * property lives on their side, which is the correct place for it — a check
 * the client performs is a check an attacker skips.
 *
 * Read failures therefore fail *closed* on display: if we cannot tell whether
 * the clinician is responsible for this patient, we hide the action rather
 * than offering one that will be refused.
 */
class ApiCareAccessGateway implements CareAccessGateway
{
    public function __construct(
        private readonly ClinicalApiClient $client,
        private readonly ClinicalRequestContext $context,
    ) {
    }

    public function hasActiveRelationship(ClinicalActor $actor, string $patientId): bool
    {
        try {
            $data = $this->client->post('clinical/care-assignments/check', [
                'user_id' => $actor->userId,
                'patient_id' => $patientId,
                'role_codes' => $this->context->rolesFor(),
            ], ['business_id' => $actor->businessId]);

            return (bool) ($data['is_responsible'] ?? false);
        } catch (ClinicalApiException $e) {
            Log::warning('Care relationship check failed; assuming no relationship.', $e->context());

            return false;
        }
    }

    public function myPatientIds(ClinicalActor $actor): array
    {
        try {
            $data = $this->client->get('clinical/care-assignments', [
                'user_id' => $actor->userId,
            ], ['business_id' => $actor->businessId]);
        } catch (ClinicalApiException $e) {
            Log::warning('Could not load care assignments.', $e->context());

            return [];
        }

        $rows = array_is_list($data) ? $data : ($data['items'] ?? []);

        return array_values(array_unique(array_filter(array_map(
            fn ($row) => is_array($row) ? ($row['patient_id'] ?? $row['global_client_id'] ?? null) : null,
            $rows,
        ))));
    }

    public function claim(ClinicalActor $actor, string $role, string $patientId, ?string $visitId = null): void
    {
        $this->client->post('clinical/care-assignments', array_filter([
            'patient_id' => $patientId,
            'visit_id' => $visitId,
            'assignment_model' => 'INDIVIDUAL',
            'primary_doctor_id' => $role === 'doctor' ? $actor->userId : null,
            'primary_nurse_id' => $role === 'nurse' ? $actor->userId : null,
        ], fn ($value) => $value !== null), [
            'business_id' => $actor->businessId,
            // Claiming the same patient twice is the same logical act, so a
            // double tap must not create two assignments.
            'idempotency_key' => "care-claim-{$actor->userId}-{$patientId}-{$role}",
        ]);
    }

    public function grantBreakGlass(
        ClinicalActor $actor,
        string $patientId,
        ?string $visitId,
        string $reasonCode,
        ?string $justificationNote = null,
    ): void {
        // Grants a four-hour audited window on Clinical's side (the local
        // guard's default is fifteen minutes — a real behavioural difference
        // between the drivers, and Clinical's window is the authoritative one).
        $this->client->post('clinical/security/break-glass', array_filter([
            'patient_id' => $patientId,
            'visit_id' => $visitId,
            'reason_code' => $reasonCode,
            'justification_note' => $justificationNote,
        ], fn ($value) => $value !== null), ['business_id' => $actor->businessId]);
    }

    public function canMutateFromCurrentLocation(): bool
    {
        try {
            $data = $this->client->get('clinical/security/context');
        } catch (ClinicalApiException $e) {
            Log::warning('Could not read clinical security context.', $e->context());

            // Fail closed on the *display* decision. A clinician who is
            // genuinely on-premises sees a disabled button and reloads; the
            // alternative is offering a live prescribe action that Clinical
            // will refuse with a 403 the user cannot interpret.
            return false;
        }

        return (bool) ($data['is_on_premises'] ?? $data['on_premises'] ?? false);
    }
}
