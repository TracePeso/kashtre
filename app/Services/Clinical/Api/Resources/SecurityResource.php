<?php

namespace App\Services\Clinical\Api\Resources;

/**
 * Access gates, device enrollment, the Medical Director's surveillance feed
 * and the audit trail — API Integration Guide §8 and §10.1.
 *
 * The four gates run in this order, each with its own error code, so a 403 is
 * always attributable:
 *
 *   service key → ZTNA → device + biometric → care relationship → chart lock
 *
 * The device gate ships **disabled** (§14). Until it is enabled after the first
 * enrollment cohort, none of the biometric controls below are actually in force
 * — the endpoints exist and answer, but nothing is gated on them.
 */
class SecurityResource extends ClinicalResource
{
    // ---------------------------------------------------------------- context & break-glass

    /**
     * On/off premises, roles, tenant and scope, as Clinical sees this caller.
     * The authoritative answer to "may I show the prescribe button?".
     *
     * @return array<string, mixed>
     */
    public function context(array $options = []): array
    {
        return $this->client->get('clinical/security/context', [], $options);
    }

    /**
     * Emergency override of the care-relationship gate. Grants a four-hour
     * audited window and writes to the immutable audit trail.
     *
     * Only offer this when a refusal said `requires_break_glass: true` —
     * otherwise the 403 came from a different gate and break-glass will not
     * lift it.
     */
    public function breakGlass(array $payload, array $options = []): array
    {
        return $this->client->post('clinical/security/break-glass', $this->filled($payload), $options);
    }

    /**
     * Break-glass events already granted — the review queue.
     *
     * @return array<int, array<string, mixed>>
     */
    public function breakGlassLog(array $query = [], array $options = []): array
    {
        return $this->rows(
            $this->client->get('clinical/security/break-glass', $this->filled($query), $options),
            'events'
        );
    }

    // ---------------------------------------------------------------- devices

    /**
     * Medical Director issues an enrollment token for a new device.
     */
    public function generateMdToken(array $payload = [], array $options = []): array
    {
        return $this->client->post('clinical/md/generate-token', $this->filled($payload), $options);
    }

    /**
     * Device redeems that token and registers its public key.
     */
    public function completeDeviceEnrollment(array $payload, array $options = []): array
    {
        return $this->client->post('clinical/device/complete-enrollment', $this->filled($payload), $options);
    }

    /**
     * Requests a biometric challenge for an off-premises request.
     *
     * Single-use and expires in five minutes, so it cannot be pre-fetched or
     * cached. The device signs it (RSA-SHA256, base64) and retries the
     * original request with X-KashTre-Device-UUID, -Challenge-Payload and
     * -Biometric-Signature.
     */
    public function deviceChallenge(string $deviceUuid, array $options = []): array
    {
        return $this->client->post('clinical/device/challenge', ['device_uuid' => $deviceUuid], $options);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function devices(array $query = [], array $options = []): array
    {
        return $this->rows($this->client->get('clinical/md/devices', $this->filled($query), $options), 'devices');
    }

    /**
     * Suspends or revokes a device. A withdrawn device is not a retry-later
     * condition for its holder — the guide's instruction is to stop and
     * telephone the Medical Director.
     */
    public function updateDevice(int|string $deviceId, array $payload, array $options = []): array
    {
        return $this->client->patch("clinical/md/devices/{$deviceId}", $this->filled($payload), $options);
    }

    // ---------------------------------------------------------------- offsite surveillance

    /**
     * Every successful off-premises chart access. This is the Medical
     * Director's feed, not a general audit log.
     *
     * @return array<int, array<string, mixed>>
     */
    public function offsiteAuditFeed(array $query = [], array $options = []): array
    {
        return $this->rows(
            $this->client->get('clinical/md/offsite-audit-feed', $this->filled($query), $options),
            'access_logs'
        );
    }

    /** Asks the clinician to explain a specific off-premises access. */
    public function demandJustification(int|string $accessLogId, array $payload = [], array $options = []): array
    {
        return $this->client->post(
            "clinical/md/offsite-audit-feed/{$accessLogId}/demand-justification",
            $this->filled($payload),
            $options,
        );
    }

    /** The clinician's answer to that demand. */
    public function justifyAccess(int|string $accessLogId, array $payload, array $options = []): array
    {
        return $this->client->post(
            "clinical/md/offsite-audit-feed/{$accessLogId}/justify",
            $this->filled($payload),
            $options,
        );
    }

    /** Terminates a live off-premises session. */
    public function closeSession(int|string $accessLogId, array $payload = [], array $options = []): array
    {
        return $this->client->post(
            "clinical/md/offsite-audit-feed/{$accessLogId}/close-session",
            $this->filled($payload),
            $options,
        );
    }

    // ---------------------------------------------------------------- audit trail

    /**
     * @return array<int, array<string, mixed>>
     */
    public function auditTrail(array $query = [], array $options = []): array
    {
        return $this->rows($this->client->get('clinical/audit-trail', $this->filled($query), $options), 'entries');
    }

    /**
     * Verifies the hash chain. A failure here means the immutable trail has
     * been tampered with, which is a governance incident rather than a bug —
     * escalate, do not retry.
     *
     * Verification is driven by Clinical's scheduler; without `schedule:run`
     * on their side the trail is never verified at all.
     */
    public function verifyAuditTrail(array $options = []): array
    {
        return $this->client->get('clinical/audit-trail/verify', [], $options);
    }

    // ---------------------------------------------------------------- care assignments

    /**
     * @return array<int, array<string, mixed>>
     */
    public function careAssignments(array $query = [], array $options = []): array
    {
        return $this->rows(
            $this->client->get('clinical/care-assignments', $this->filled($query), $options),
            'assignments'
        );
    }

    /**
     * @param  array{patient_id: string, visit_id?: ?string, assignment_model: string, primary_doctor_id?: ?int, primary_nurse_id?: ?int, assigned_team_id?: ?int, assigned_role_code?: ?string}  $payload
     */
    public function assignCare(array $payload, array $options = []): array
    {
        return $this->client->post('clinical/care-assignments', $this->filled($payload), $options);
    }

    /**
     * Advisory check. Clinical re-runs this gate on every patient-scoped call
     * regardless, so a `true` here is a rendering hint, not a grant.
     */
    public function checkCareRelationship(array $payload, array $options = []): array
    {
        return $this->client->post('clinical/care-assignments/check', $this->filled($payload), $options);
    }

    public function removeCareAssignment(int|string $careAssignmentId, array $options = []): array
    {
        return $this->client->delete("clinical/care-assignments/{$careAssignmentId}", [], $options);
    }
}
