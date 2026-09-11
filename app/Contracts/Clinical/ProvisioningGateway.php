<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * §10.9 "Before any of this works: provision the facility" — a brand-new
 * tenant arrives at Clinical with a completely empty dictionary registry;
 * every picker in the settings screens renders empty until this has run
 * once. Gated on a governance role or permission on Clinical's own side
 * (confirmed live 2026-08-19 — its default, "Manage Settings", does not
 * exist in Main's permission list at all, so Clinical's
 * CLINICAL_PROVISIONING_ACCESS_PERMISSIONS was widened to also accept
 * Main's real "Manage Clinical Module").
 *
 * No local equivalent — the local driver's dictionaries are seeded through
 * migrations/seeders at deploy time, not a runtime API a facility admin
 * calls. LocalProvisioningGateway reports "already provisioned" rather
 * than pretending to run a package that does not exist under this driver.
 */
interface ProvisioningGateway
{
    /**
     * @return array{tenant_id: string, status: string, is_provisioned: bool, dictionary_count: int, populated_dictionaries: int, total_rows: int, empty_dictionaries: array<int, string>, counts: array<string, int>}
     */
    public function status(ClinicalActor $actor, string $tenantId): array;

    /**
     * The guide clocks a real run at 47s and tells callers to allow 180s —
     * this is synchronous on Clinical's side, not fire-and-forget.
     *
     * @return array<string, mixed>
     */
    public function provision(ClinicalActor $actor, string $tenantId, bool $resync = false): array;
}
