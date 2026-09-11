<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * SRD v6.1 Phase 1 §9 (Credentials, Privileges and Restrictions) —
 * high-risk authority represented separately from ordinary title-derived
 * permissions (CLN-PRIV-001). API_GUIDE_V6.1 §Phase 1: live, but "not yet
 * wired into any specific order/MAR/discharge endpoint's own authorization
 * check" — this is the primitive, not an enforced gate yet.
 */
interface PrivilegeGateway
{
    /**
     * The exact 9 named categories from SRD §9 — not illustrative, this is
     * the complete list as written.
     */
    public const CATEGORY_CONTROLLED_MEDICINE_PRESCRIBING = 'CONTROLLED_MEDICINE_PRESCRIBING';

    public const CATEGORY_CHEMOTHERAPY = 'CHEMOTHERAPY_VERIFICATION_OR_ADMINISTRATION';

    public const CATEGORY_BLOOD_PRODUCT_AUTHORIZATION = 'BLOOD_PRODUCT_AUTHORIZATION';

    public const CATEGORY_INDEPENDENT_PROCEDURE = 'INDEPENDENT_PROCEDURE_PERFORMANCE';

    public const CATEGORY_SEDATION = 'SEDATION';

    public const CATEGORY_HIGH_ALERT_MEDICATION_OVERRIDE = 'HIGH_ALERT_MEDICATION_OVERRIDE';

    public const CATEGORY_DEATH_VALIDATION = 'DEATH_VALIDATION';

    public const CATEGORY_SPECIALIST_RESULT_AUTHORIZATION = 'SPECIALIST_RESULT_AUTHORIZATION';

    public const CATEGORY_RECORD_CORRECTION_APPROVAL = 'CLINICAL_RECORD_CORRECTION_APPROVAL';

    /** @return array<int, array<string, mixed>> */
    public function forUser(ClinicalActor $actor, int $userId): array;

    /**
     * CLN-PRIV-002/003: a credential, competency, training, licence,
     * specialty privilege or local authorization — effective-dated,
     * suspendable, expirable.
     *
     * @param  array{user_id: int, category: string, effective_start: string, effective_end?: ?string, credential_reference?: ?string, granted_by?: ?string}  $payload
     * @return array<string, mixed>
     */
    public function grant(ClinicalActor $actor, array $payload): array;

    /** @return array<string, mixed> */
    public function suspend(ClinicalActor $actor, string $privilegeId, string $reason): array;

    /** @return array<string, mixed> */
    public function reinstate(ClinicalActor $actor, string $privilegeId): array;
}
