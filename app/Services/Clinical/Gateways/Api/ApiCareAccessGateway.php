<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\CareAccessGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Services\Clinical\Api\ClinicalRequestContext;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Carbon;
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

            // Clinical answers with has_care_relationship; is_responsible was
            // the field this was written against and never arrives, which made
            // every check fall through to false and every chart look forbidden.
            if ((bool) ($data['has_care_relationship'] ?? $data['is_responsible'] ?? false)) {
                return true;
            }
        } catch (ClinicalApiException $e) {
            Log::warning('Care relationship check failed; assuming no relationship.', $e->context());
            // Fall through to the break-glass check rather than returning
            // here — a clinician mid-emergency-override should not lose
            // access because the *other* check happened to 503.
        }

        // has_care_relationship only answers "is this a formal assignment" —
        // Clinical genuinely tracks break-glass as a separate grant, confirmed
        // live 2026-08-15 (clinical/care-assignments/check returned
        // has_care_relationship: false, requires_break_glass: true for a user
        // who had *already broken glass twice* on this exact patient). Without
        // this, the interface's own promise — "a clinician who has just broken
        // glass must not be bounced straight back to the refusal screen" — was
        // true for the local driver and silently false for this one: grant,
        // redirect, get refused, forever.
        return $this->hasActiveBreakGlassGrant($actor, $patientId);
    }

    private function hasActiveBreakGlassGrant(ClinicalActor $actor, string $patientId): bool
    {
        try {
            $data = $this->client->get('clinical/security/break-glass', [
                'patient_id' => $patientId,
                'user_id' => $actor->userId,
            ], ['business_id' => $actor->businessId]);
        } catch (ClinicalApiException $e) {
            Log::warning('Break-glass grant check failed; assuming no override.', $e->context());

            return false;
        }

        $rows = array_is_list($data) ? $data : ($data['items'] ?? $data['data'] ?? []);

        foreach ($rows as $row) {
            if (is_array($row) && ! empty($row['granted_until']) && Carbon::parse($row['granted_until'])->isFuture()) {
                return true;
            }
        }

        return false;
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
    ): ?string {
        // Grants a four-hour audited window on Clinical's side (the local
        // guard's default is fifteen minutes — a real behavioural difference
        // between the drivers, and Clinical's window is the authoritative one).
        $data = $this->client->post('clinical/security/break-glass', array_filter([
            'patient_id' => $patientId,
            'visit_id' => $visitId,
            'reason_code' => $reasonCode,
            'justification_note' => $justificationNote,
        ], fn ($value) => $value !== null), ['business_id' => $actor->businessId]);

        return $data['break_glass_episode_id'] ?? null;
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

    public function teamFor(ClinicalActor $actor, string $patientId, ?string $visitId = null): array
    {
        try {
            return $this->client->get(
                "clinical/patients/{$patientId}/care-team",
                array_filter(['visit_id' => $visitId]),
                ['business_id' => $actor->businessId],
            );
        } catch (ClinicalApiException $e) {
            Log::warning('Could not load the care team.', $e->context());

            return [];
        }
    }

    public function assign(ClinicalActor $actor, array $attributes): void
    {
        // Keyed on *who is being assigned to what*, not on the acting user —
        // an admin reassigning three different doctors to the same patient
        // in one sitting must produce three assignments, not one deduped by
        // a key that only varied by nothing. A double-tap of the identical
        // assignment (same target, same participation) still dedupes.
        $target = $attributes['primary_doctor_id']
            ?? $attributes['primary_nurse_id']
            ?? $attributes['assigned_team_id']
            ?? $attributes['assigned_role_code']
            ?? 'none';

        $this->client->post(
            'clinical/care-assignments',
            array_filter($attributes, fn ($value) => $value !== null && $value !== ''),
            [
                'business_id' => $actor->businessId,
                'idempotency_key' => implode('-', [
                    'care-assign',
                    $attributes['patient_id'] ?? '',
                    $attributes['participation'] ?? 'PRIMARY',
                    $target,
                ]),
            ],
        );
    }

    public function endAssignment(ClinicalActor $actor, int|string $assignmentId): void
    {
        $this->client->delete(
            "clinical/care-assignments/{$assignmentId}",
            [],
            ['business_id' => $actor->businessId],
        );
    }

    public function reviewBreakGlass(
        ClinicalActor $actor,
        string $episodePublicId,
        string $outcome,
        string $finding,
    ): void {
        $this->client->post(
            "clinical/security/break-glass/{$episodePublicId}/review",
            ['outcome' => $outcome, 'finding' => $finding],
            [
                'business_id' => $actor->businessId,
                'idempotency_key' => "break-glass-review-{$episodePublicId}",
            ],
        );
    }
}
