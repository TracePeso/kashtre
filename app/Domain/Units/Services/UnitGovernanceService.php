<?php

namespace App\Domain\Units\Services;

use App\Domain\Units\Enums\UnitStatus;
use App\Domain\Units\Models\CoreUnit;
use App\Domain\Units\Models\ModuleUnitPolicy;
use App\Domain\Units\Models\UnitAudit;
use App\Domain\Units\Models\UnitVersion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

/**
 * Governance helpers: verification, lifecycle, policies, audit trail.
 */
final class UnitGovernanceService
{
    public function __construct(
        private readonly UnitCatalogService $catalog,
    ) {}

    public function record(
        string $tenantKey,
        string $action,
        string $objectType,
        string $objectPublicId,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
    ): UnitAudit {
        return UnitAudit::query()->create([
            'event_id' => (string) Str::ulid(),
            'tenant_key' => $tenantKey,
            'actor_user_id' => Auth::id(),
            'action' => $action,
            'object_type' => $objectType,
            'object_public_id' => $objectPublicId,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'correlation_id' => (string) Str::ulid(),
            'ip_address' => Request::ip(),
        ]);
    }

    public function markUcumVerified(CoreUnit $unit, ?string $reason = null): CoreUnit
    {
        if ($unit->is_system) {
            throw new \InvalidArgumentException('SYSTEM units are seed-managed; verification is already seeded.');
        }

        $before = ['standard_verification_status' => $unit->standard_verification_status];
        $unit->update(['standard_verification_status' => 'VERIFIED']);
        $this->record(
            $unit->tenant_key,
            'UCUM_VERIFIED',
            'CoreUnit',
            $unit->public_id,
            $before,
            ['standard_verification_status' => 'VERIFIED'],
            $reason
        );

        return $unit->fresh();
    }

    public function deprecateUnit(CoreUnit $unit, ?string $reason = null): CoreUnit
    {
        if ($unit->is_system) {
            throw new \InvalidArgumentException('SYSTEM units cannot be deprecated from this console.');
        }

        $before = ['status' => $unit->status];
        $unit->update(['status' => UnitStatus::DEPRECATED->value]);
        UnitVersion::query()
            ->where('unit_id', $unit->id)
            ->where('status', UnitStatus::ACTIVE->value)
            ->update(['status' => UnitStatus::DEPRECATED->value, 'effective_to' => now()]);

        $this->record(
            $unit->tenant_key,
            'UNIT_DEPRECATED',
            'CoreUnit',
            $unit->public_id,
            $before,
            ['status' => UnitStatus::DEPRECATED->value],
            $reason
        );

        return $unit->fresh();
    }

    public function activateUnit(CoreUnit $unit, ?string $reason = null): CoreUnit
    {
        if ($unit->is_system) {
            throw new \InvalidArgumentException('SYSTEM units are already active.');
        }

        $before = ['status' => $unit->status];
        $this->catalog->activateTenantUnit($unit);
        $this->record(
            $unit->tenant_key,
            'UNIT_ACTIVATED',
            'CoreUnit',
            $unit->public_id,
            $before,
            ['status' => UnitStatus::ACTIVE->value],
            $reason
        );

        return $unit->fresh();
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function upsertModulePolicy(
        string $tenantKey,
        string $moduleCode,
        string $domainObjectType,
        string $domainObjectPublicId,
        CoreUnit $unit,
        string $usageRole,
        ?int $displayPrecision = null,
        array $settings = [],
    ): ModuleUnitPolicy {
        $existing = ModuleUnitPolicy::query()
            ->where('tenant_key', $tenantKey)
            ->where('module_code', strtoupper($moduleCode))
            ->where('domain_object_type', strtoupper($domainObjectType))
            ->where('domain_object_public_id', $domainObjectPublicId)
            ->where('unit_id', $unit->id)
            ->where('usage_role', strtoupper($usageRole))
            ->first();

        if ($existing) {
            $existing->update([
                'display_precision' => $displayPrecision,
                'status' => UnitStatus::ACTIVE->value,
                'settings' => $settings ?: $existing->settings,
                'effective_to' => null,
            ]);
            $policy = $existing->fresh();
        } else {
            $policy = ModuleUnitPolicy::query()->create([
                'public_id' => (string) Str::ulid(),
                'tenant_key' => $tenantKey,
                'module_code' => strtoupper($moduleCode),
                'domain_object_type' => strtoupper($domainObjectType),
                'domain_object_public_id' => $domainObjectPublicId,
                'unit_id' => $unit->id,
                'usage_role' => strtoupper($usageRole),
                'display_precision' => $displayPrecision,
                'status' => UnitStatus::ACTIVE->value,
                'effective_from' => now(),
                'settings' => $settings,
            ]);
        }

        $this->record(
            $tenantKey,
            'MODULE_POLICY_UPSERT',
            'ModuleUnitPolicy',
            $policy->public_id,
            null,
            [
                'module' => $policy->module_code,
                'role' => $policy->usage_role,
                'unit' => $unit->public_id,
            ]
        );

        return $policy->load('unit');
    }
}
