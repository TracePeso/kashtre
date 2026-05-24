<?php

namespace App\Services;

use App\Models\HrPolicyVersion;
use App\Models\HrRegionalPolicy;
use App\Models\Organization;
use Carbon\CarbonImmutable;

class HrDefaultPolicyService
{
    /**
     * @return array<string, mixed>
     */
    public function policyDefinition(): array
    {
        return [
            'policy_code' => 'UG-STD-HR',
            'name' => 'Uganda Standard HR Policy',
            'country_code' => 'UGA',
            'jurisdiction' => 'Uganda',
            'description' => 'Default seeded HR regional policy for roster validation, approvals, and holiday compensatory-credit handling.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function versionDefinition(): array
    {
        return [
            'version_label' => 'Default v1',
            'effective_from' => CarbonImmutable::now()->startOfYear()->toDateString(),
            'effective_to' => null,
            'weekly_standard_minutes' => 40 * 60,
            'weekly_absolute_ceiling_minutes' => 56 * 60,
            'daily_net_cap_minutes' => 10 * 60,
            'minimum_rest_gap_minutes' => 12 * 60,
            'consecutive_work_days_limit' => 5,
            'rest_after_consecutive_days_minutes' => 24 * 60,
            'anchor_window_minutes' => 0,
            'overtime_trigger_minutes' => null,
            'metadata' => [
                'holiday_compensatory_credit_settings' => HrPolicyVersion::defaultHolidayCompensatoryCreditSettings(),
            ],
            'notes' => 'Seeded default HR policy version.',
        ];
    }

    public function seedMissingDefaults(Organization $organization): bool
    {
        $definition = $this->policyDefinition();
        $existingPolicy = HrRegionalPolicy::query()
            ->where('organization_id', $organization->id)
            ->where('policy_code', $definition['policy_code'])
            ->first();

        $createdPolicy = false;

        if (! $existingPolicy) {
            $createdPolicy = true;
            $existingPolicy = HrRegionalPolicy::create([
                'organization_id' => $organization->id,
                'policy_code' => $definition['policy_code'],
                'name' => $definition['name'],
                'country_code' => $definition['country_code'],
                'jurisdiction' => $definition['jurisdiction'],
                'description' => $definition['description'],
                'is_active' => ! HrRegionalPolicy::query()
                    ->where('organization_id', $organization->id)
                    ->exists(),
            ]);
        }

        $hasVersion = HrPolicyVersion::query()
            ->where('organization_id', $organization->id)
            ->where('regional_policy_id', $existingPolicy->id)
            ->exists();

        if ($hasVersion) {
            return $createdPolicy;
        }

        $version = $this->versionDefinition();

        HrPolicyVersion::create([
            'organization_id' => $organization->id,
            'regional_policy_id' => $existingPolicy->id,
            'version_label' => $version['version_label'],
            'effective_from' => $version['effective_from'],
            'effective_to' => $version['effective_to'],
            'is_active' => ! HrPolicyVersion::query()
                ->where('organization_id', $organization->id)
                ->where('is_active', true)
                ->exists(),
            'weekly_standard_minutes' => $version['weekly_standard_minutes'],
            'weekly_absolute_ceiling_minutes' => $version['weekly_absolute_ceiling_minutes'],
            'daily_net_cap_minutes' => $version['daily_net_cap_minutes'],
            'minimum_rest_gap_minutes' => $version['minimum_rest_gap_minutes'],
            'consecutive_work_days_limit' => $version['consecutive_work_days_limit'],
            'rest_after_consecutive_days_minutes' => $version['rest_after_consecutive_days_minutes'],
            'anchor_window_minutes' => $version['anchor_window_minutes'],
            'overtime_trigger_minutes' => $version['overtime_trigger_minutes'],
            'metadata' => $version['metadata'],
            'notes' => $version['notes'],
        ]);

        return true;
    }
}
