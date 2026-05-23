<?php

namespace App\Livewire;

use App\Models\HrPolicyVersion;
use App\Models\HrRegionalPolicy;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class HrPolicyManager extends Component
{
    public bool $creatingPolicy = false;
    public ?int $selectedPolicyId = null;
    public ?int $editingPolicyId = null;
    public string $policyCode = '';
    public string $policyName = '';
    public string $countryCode = '';
    public string $jurisdiction = '';
    public string $policyDescription = '';
    public bool $policyIsActive = true;

    public ?int $editingVersionId = null;
    public string $versionLabel = '';
    public ?string $effectiveFrom = null;
    public ?string $effectiveTo = null;
    public bool $versionIsActive = true;
    public ?float $weeklyStandardHours = 40.0;
    public ?float $weeklyAbsoluteCeilingHours = 56.0;
    public ?float $dailyNetCapHours = 10.0;
    public ?float $minimumRestGapHours = 12.0;
    public ?int $consecutiveWorkDaysLimit = 5;
    public ?float $restAfterConsecutiveDaysHours = 24.0;
    public ?float $anchorWindowHours = 0.0;
    public ?float $overtimeTriggerHours = null;
    public string $crossingHolidayCreditRule = HrPolicyVersion::HOLIDAY_COMPENSATORY_CREDIT_PER_SHIFT;
    public ?float $crossingHolidayCreditDays = 1.0;
    public string $withinHolidayCreditRule = HrPolicyVersion::HOLIDAY_COMPENSATORY_CREDIT_PER_SHIFT;
    public ?float $withinHolidayCreditDays = 1.0;
    public string $versionNotes = '';

    public ?string $message = null;

    public function mount(): void
    {
        $this->resetPolicyForm();
        $this->resetVersionForm();
    }

    public function render(): View
    {
        $user = Auth::user();
        $organization = Organization::current();

        abort_unless($user instanceof User && $organization && $user->canViewHrSetup(), 403);

        $policies = HrRegionalPolicy::query()
            ->where('organization_id', $organization->id)
            ->with([
                'versions' => fn ($query) => $query
                    ->orderByDesc('effective_from')
                    ->orderByDesc('id'),
            ])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        if (
            ! $this->creatingPolicy
            && $policies->isNotEmpty()
            && ! $policies->contains('id', $this->selectedPolicyId)
        ) {
            $this->applySelectedPolicy($policies->first());
        }

        if ($policies->isEmpty() && $this->selectedPolicyId !== null) {
            $this->selectedPolicyId = null;
            $this->editingPolicyId = null;
            $this->resetPolicyForm();
            $this->resetVersionForm();
        }

        $selectedPolicy = $policies->firstWhere('id', $this->selectedPolicyId);
        $currentVersion = $selectedPolicy ? $this->currentVersion($selectedPolicy) : null;

        return view('livewire.hr-policy-manager', [
            'policies' => $policies,
            'selectedPolicy' => $selectedPolicy,
            'selectedPolicyVersions' => $selectedPolicy?->versions ?? collect(),
            'currentVersion' => $currentVersion,
            'holidayCompensatoryCreditPolicyOptions' => HrPolicyVersion::holidayCompensatoryCreditPolicyOptions(),
            'holidayCompensatoryCreditScopeOptions' => HrPolicyVersion::holidayCompensatoryCreditScopeOptions(),
            'canAddPolicies' => $user->canAddHrSetup(),
            'canEditPolicies' => $user->canEditHrSetup(),
        ]);
    }

    public function selectPolicy(int $policyId): void
    {
        $policy = $this->policyQuery()->findOrFail($policyId);

        $this->resetValidation();
        $this->message = null;
        $this->applySelectedPolicy($policy);
    }

    public function startCreatingPolicy(): void
    {
        $this->assertCanAddPolicies();

        $this->resetValidation();
        $this->message = null;
        $this->creatingPolicy = true;
        $this->selectedPolicyId = null;
        $this->editingPolicyId = null;
        $this->resetPolicyForm();
        $this->resetVersionForm();
    }

    public function savePolicy(): void
    {
        if ($this->editingPolicyId) {
            $this->assertCanEditPolicies();
        } else {
            $this->assertCanAddPolicies();
        }

        $organization = Organization::current();

        abort_unless($organization, 403);

        $validated = $this->validate([
            'policyCode' => [
                'required',
                'string',
                'max:80',
                Rule::unique('hr_regional_policies', 'policy_code')
                    ->where(fn ($query) => $query->where('organization_id', $organization->id))
                    ->ignore($this->editingPolicyId),
            ],
            'policyName' => ['required', 'string', 'max:255'],
            'countryCode' => ['nullable', 'string', 'max:3'],
            'jurisdiction' => ['nullable', 'string', 'max:255'],
            'policyDescription' => ['nullable', 'string', 'max:1000'],
            'policyIsActive' => ['boolean'],
        ]);

        $policy = DB::transaction(function () use ($organization, $validated) {
            $policy = $this->editingPolicyId
                ? $this->policyQuery()->findOrFail($this->editingPolicyId)
                : new HrRegionalPolicy([
                    'organization_id' => $organization->id,
                ]);

            $policy->fill([
                'policy_code' => strtoupper(trim($validated['policyCode'])),
                'name' => trim($validated['policyName']),
                'country_code' => filled($validated['countryCode']) ? strtoupper(trim($validated['countryCode'])) : null,
                'jurisdiction' => filled($validated['jurisdiction']) ? trim($validated['jurisdiction']) : null,
                'description' => filled($validated['policyDescription']) ? trim($validated['policyDescription']) : null,
                'is_active' => (bool) $validated['policyIsActive'],
            ]);
            $policy->save();

            if ($policy->is_active) {
                HrRegionalPolicy::query()
                    ->where('organization_id', $organization->id)
                    ->whereKeyNot($policy->id)
                    ->update(['is_active' => false]);
            }

            return $policy->fresh('versions');
        });

        $this->resetValidation();
        $this->applySelectedPolicy($policy);
        $this->message = 'Policy saved.';
    }

    public function activatePolicy(int $policyId): void
    {
        $this->assertCanEditPolicies();

        $organization = Organization::current();

        abort_unless($organization, 403);

        $policy = $this->policyQuery()->findOrFail($policyId);

        DB::transaction(function () use ($organization, $policy): void {
            HrRegionalPolicy::query()
                ->where('organization_id', $organization->id)
                ->update(['is_active' => false]);

            $policy->update(['is_active' => true]);
        });

        $this->applySelectedPolicy($policy->fresh('versions'));
        $this->message = 'Policy activated.';
    }

    public function deactivatePolicy(int $policyId): void
    {
        $this->assertCanEditPolicies();

        $policy = $this->policyQuery()->findOrFail($policyId);
        $policy->update(['is_active' => false]);

        $this->applySelectedPolicy($policy->fresh('versions'));
        $this->message = 'Policy deactivated.';
    }

    public function startCreatingVersion(): void
    {
        $this->assertCanAddPolicies();

        if (! $this->selectedPolicyId) {
            $this->addError('version', 'Select or save a policy before adding versions.');
            return;
        }

        $this->resetValidation();
        $this->message = null;
        $this->editingVersionId = null;
        $this->resetVersionForm();
    }

    public function editVersion(int $versionId): void
    {
        $this->assertCanEditPolicies();

        $version = $this->versionQuery()->findOrFail($versionId);

        $this->resetValidation();
        $this->message = null;
        $this->editingVersionId = $version->id;
        $this->fillVersionForm($version);
    }

    public function saveVersion(): void
    {
        if ($this->editingVersionId) {
            $this->assertCanEditPolicies();
        } else {
            $this->assertCanAddPolicies();
        }

        $policy = $this->selectedPolicy();

        if (! $policy) {
            $this->addError('version', 'Select or save a policy before adding versions.');
            return;
        }

        $validated = $this->validate([
            'versionLabel' => ['required', 'string', 'max:80'],
            'effectiveFrom' => ['required', 'date'],
            'effectiveTo' => ['nullable', 'date', 'after_or_equal:effectiveFrom'],
            'versionIsActive' => ['boolean'],
            'weeklyStandardHours' => ['required', 'numeric', 'min:0.1'],
            'weeklyAbsoluteCeilingHours' => ['required', 'numeric', 'min:0.1'],
            'dailyNetCapHours' => ['required', 'numeric', 'min:0.1'],
            'minimumRestGapHours' => ['required', 'numeric', 'min:0'],
            'consecutiveWorkDaysLimit' => ['required', 'integer', 'min:1', 'max:31'],
            'restAfterConsecutiveDaysHours' => ['required', 'numeric', 'min:0'],
            'anchorWindowHours' => ['required', 'numeric', 'min:0'],
            'overtimeTriggerHours' => ['nullable', 'numeric', 'min:0'],
            'crossingHolidayCreditRule' => ['required', Rule::in(array_keys(HrPolicyVersion::holidayCompensatoryCreditPolicyOptions()))],
            'crossingHolidayCreditDays' => ['required', 'numeric', 'min:0', 'multiple_of:0.5'],
            'withinHolidayCreditRule' => ['required', Rule::in(array_keys(HrPolicyVersion::holidayCompensatoryCreditPolicyOptions()))],
            'withinHolidayCreditDays' => ['required', 'numeric', 'min:0', 'multiple_of:0.5'],
            'versionNotes' => ['nullable', 'string', 'max:2000'],
        ]);

        $weeklyStandardMinutes = $this->minutesFromHours($validated['weeklyStandardHours']);
        $weeklyAbsoluteCeilingMinutes = $this->minutesFromHours($validated['weeklyAbsoluteCeilingHours']);
        $dailyNetCapMinutes = $this->minutesFromHours($validated['dailyNetCapHours']);
        $minimumRestGapMinutes = $this->minutesFromHours($validated['minimumRestGapHours']);
        $restAfterConsecutiveDaysMinutes = $this->minutesFromHours($validated['restAfterConsecutiveDaysHours']);
        $anchorWindowMinutes = $this->minutesFromHours($validated['anchorWindowHours']);
        $overtimeTriggerMinutes = filled($validated['overtimeTriggerHours'] ?? null)
            ? $this->minutesFromHours($validated['overtimeTriggerHours'])
            : null;

        if ($weeklyAbsoluteCeilingMinutes < $weeklyStandardMinutes) {
            $this->addError('weeklyAbsoluteCeilingHours', 'Weekly absolute ceiling must be greater than or equal to the weekly standard.');
            return;
        }

        if ($dailyNetCapMinutes > $weeklyAbsoluteCeilingMinutes) {
            $this->addError('dailyNetCapHours', 'Daily net cap cannot exceed the weekly absolute ceiling.');
            return;
        }

        if (
            (bool) $validated['versionIsActive']
            && $this->hasOverlappingActiveVersion(
                $policy,
                (string) $validated['effectiveFrom'],
                $validated['effectiveTo'] ?: null,
                $this->editingVersionId
            )
        ) {
            $this->addError('version', 'Another active version for this policy already covers part of that effective date range.');
            return;
        }

        $version = DB::transaction(function () use (
            $policy,
            $validated,
            $weeklyStandardMinutes,
            $weeklyAbsoluteCeilingMinutes,
            $dailyNetCapMinutes,
            $minimumRestGapMinutes,
            $restAfterConsecutiveDaysMinutes,
            $anchorWindowMinutes,
            $overtimeTriggerMinutes
        ) {
            $version = $this->editingVersionId
                ? $this->versionQuery()->findOrFail($this->editingVersionId)
                : new HrPolicyVersion([
                    'organization_id' => $policy->organization_id,
                    'regional_policy_id' => $policy->id,
                ]);

            $version->fill([
                'version_label' => trim($validated['versionLabel']),
                'effective_from' => $validated['effectiveFrom'],
                'effective_to' => $validated['effectiveTo'] ?: null,
                'is_active' => (bool) $validated['versionIsActive'],
                'weekly_standard_minutes' => $weeklyStandardMinutes,
                'weekly_absolute_ceiling_minutes' => $weeklyAbsoluteCeilingMinutes,
                'daily_net_cap_minutes' => $dailyNetCapMinutes,
                'minimum_rest_gap_minutes' => $minimumRestGapMinutes,
                'consecutive_work_days_limit' => (int) $validated['consecutiveWorkDaysLimit'],
                'rest_after_consecutive_days_minutes' => $restAfterConsecutiveDaysMinutes,
                'anchor_window_minutes' => $anchorWindowMinutes,
                'overtime_trigger_minutes' => $overtimeTriggerMinutes,
                'metadata' => array_merge($version->metadata ?? [], [
                    'holiday_compensatory_credit_settings' => [
                        HrPolicyVersion::HOLIDAY_COMPENSATORY_SCOPE_CROSSING_PUBLIC_HOLIDAY => [
                            'rule' => $validated['crossingHolidayCreditRule'],
                            'credit_days' => (float) $validated['crossingHolidayCreditDays'],
                        ],
                        HrPolicyVersion::HOLIDAY_COMPENSATORY_SCOPE_WITHIN_PUBLIC_HOLIDAY => [
                            'rule' => $validated['withinHolidayCreditRule'],
                            'credit_days' => (float) $validated['withinHolidayCreditDays'],
                        ],
                    ],
                ]),
                'notes' => filled($validated['versionNotes']) ? trim($validated['versionNotes']) : null,
            ]);
            $version->save();

            return $version->fresh();
        });

        $this->editingVersionId = $version->id;
        $this->fillVersionForm($version);
        $this->message = 'Policy version saved.';
    }

    public function activateVersion(int $versionId): void
    {
        $this->assertCanEditPolicies();

        $version = $this->versionQuery()->findOrFail($versionId);
        $policy = $this->selectedPolicy();

        if (! $policy) {
            $this->addError('version', 'Select a policy before activating a version.');
            return;
        }

        if (
            $this->hasOverlappingActiveVersion(
                $policy,
                $version->effective_from->toDateString(),
                $version->effective_to?->toDateString(),
                $version->id
            )
        ) {
            $this->addError('version', 'Deactivate or date-shift the overlapping active version before activating this one.');
            return;
        }

        $version->update(['is_active' => true]);
        $this->message = 'Policy version activated.';

        if ($this->editingVersionId === $version->id) {
            $this->fillVersionForm($version->fresh());
        }
    }

    public function deactivateVersion(int $versionId): void
    {
        $this->assertCanEditPolicies();

        $version = $this->versionQuery()->findOrFail($versionId);
        $version->update(['is_active' => false]);
        $this->message = 'Policy version deactivated.';

        if ($this->editingVersionId === $version->id) {
            $this->fillVersionForm($version->fresh());
        }
    }

    public function currentVersion(HrRegionalPolicy $policy): ?HrPolicyVersion
    {
        return $policy->versions
            ->filter(fn (HrPolicyVersion $version): bool => $this->versionCoversDate($version, now()->toDateString()) && $version->is_active)
            ->sortByDesc('effective_from')
            ->first();
    }

    public function formatHours(?int $minutes): string
    {
        if ($minutes === null) {
            return '-';
        }

        $hours = round($minutes / 60, 2);
        $formatted = rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.');

        return $formatted.' h';
    }

    public function formatDateRange(HrPolicyVersion $version): string
    {
        return trim(sprintf(
            '%s to %s',
            $version->effective_from?->format('M j, Y'),
            $version->effective_to?->format('M j, Y') ?? 'Open'
        ));
    }

    private function applySelectedPolicy(HrRegionalPolicy $policy): void
    {
        $this->creatingPolicy = false;
        $this->selectedPolicyId = $policy->id;
        $this->editingPolicyId = $policy->id;
        $this->fillPolicyForm($policy);
        $this->editingVersionId = null;
        $this->resetVersionForm();
    }

    private function fillPolicyForm(HrRegionalPolicy $policy): void
    {
        $this->policyCode = (string) $policy->policy_code;
        $this->policyName = (string) $policy->name;
        $this->countryCode = (string) ($policy->country_code ?? '');
        $this->jurisdiction = (string) ($policy->jurisdiction ?? '');
        $this->policyDescription = (string) ($policy->description ?? '');
        $this->policyIsActive = (bool) $policy->is_active;
    }

    private function fillVersionForm(HrPolicyVersion $version): void
    {
        $this->versionLabel = (string) $version->version_label;
        $this->effectiveFrom = $version->effective_from?->toDateString();
        $this->effectiveTo = $version->effective_to?->toDateString();
        $this->versionIsActive = (bool) $version->is_active;
        $this->weeklyStandardHours = $this->hoursFromMinutes((int) $version->weekly_standard_minutes);
        $this->weeklyAbsoluteCeilingHours = $this->hoursFromMinutes((int) $version->weekly_absolute_ceiling_minutes);
        $this->dailyNetCapHours = $this->hoursFromMinutes((int) $version->daily_net_cap_minutes);
        $this->minimumRestGapHours = $this->hoursFromMinutes((int) $version->minimum_rest_gap_minutes);
        $this->consecutiveWorkDaysLimit = (int) $version->consecutive_work_days_limit;
        $this->restAfterConsecutiveDaysHours = $this->hoursFromMinutes((int) $version->rest_after_consecutive_days_minutes);
        $this->anchorWindowHours = $this->hoursFromMinutes((int) $version->anchor_window_minutes);
        $this->overtimeTriggerHours = $version->overtime_trigger_minutes !== null
            ? $this->hoursFromMinutes((int) $version->overtime_trigger_minutes)
            : null;
        $crossingSetting = $version->holidayCompensatoryCreditSettingFor(HrPolicyVersion::HOLIDAY_COMPENSATORY_SCOPE_CROSSING_PUBLIC_HOLIDAY);
        $withinSetting = $version->holidayCompensatoryCreditSettingFor(HrPolicyVersion::HOLIDAY_COMPENSATORY_SCOPE_WITHIN_PUBLIC_HOLIDAY);
        $this->crossingHolidayCreditRule = $crossingSetting['rule'];
        $this->crossingHolidayCreditDays = (float) $crossingSetting['credit_days'];
        $this->withinHolidayCreditRule = $withinSetting['rule'];
        $this->withinHolidayCreditDays = (float) $withinSetting['credit_days'];
        $this->versionNotes = (string) ($version->notes ?? '');
    }

    private function resetPolicyForm(): void
    {
        $this->policyCode = '';
        $this->policyName = '';
        $this->countryCode = '';
        $this->jurisdiction = '';
        $this->policyDescription = '';
        $this->policyIsActive = true;
    }

    private function resetVersionForm(): void
    {
        $this->versionLabel = '';
        $this->effectiveFrom = now()->toDateString();
        $this->effectiveTo = null;
        $this->versionIsActive = true;
        $this->weeklyStandardHours = 40.0;
        $this->weeklyAbsoluteCeilingHours = 56.0;
        $this->dailyNetCapHours = 10.0;
        $this->minimumRestGapHours = 12.0;
        $this->consecutiveWorkDaysLimit = 5;
        $this->restAfterConsecutiveDaysHours = 24.0;
        $this->anchorWindowHours = 0.0;
        $this->overtimeTriggerHours = null;
        $this->crossingHolidayCreditRule = HrPolicyVersion::HOLIDAY_COMPENSATORY_CREDIT_PER_SHIFT;
        $this->crossingHolidayCreditDays = 1.0;
        $this->withinHolidayCreditRule = HrPolicyVersion::HOLIDAY_COMPENSATORY_CREDIT_PER_SHIFT;
        $this->withinHolidayCreditDays = 1.0;
        $this->versionNotes = '';
    }

    private function minutesFromHours(int|float|string|null $hours): int
    {
        return (int) round(((float) $hours) * 60);
    }

    private function hoursFromMinutes(?int $minutes): ?float
    {
        if ($minutes === null) {
            return null;
        }

        return round($minutes / 60, 2);
    }

    private function selectedPolicy(): ?HrRegionalPolicy
    {
        if (! $this->selectedPolicyId) {
            return null;
        }

        return $this->policyQuery()->find($this->selectedPolicyId);
    }

    private function policyQuery()
    {
        $organization = Organization::current();

        abort_unless($organization, 403);

        return HrRegionalPolicy::query()->where('organization_id', $organization->id);
    }

    private function versionQuery()
    {
        $organization = Organization::current();

        abort_unless($organization, 403);

        return HrPolicyVersion::query()
            ->where('organization_id', $organization->id)
            ->when($this->selectedPolicyId, fn ($query) => $query->where('regional_policy_id', $this->selectedPolicyId));
    }

    private function hasOverlappingActiveVersion(
        HrRegionalPolicy $policy,
        string $effectiveFrom,
        ?string $effectiveTo,
        ?int $ignoreVersionId = null
    ): bool {
        return HrPolicyVersion::query()
            ->where('organization_id', $policy->organization_id)
            ->where('regional_policy_id', $policy->id)
            ->where('is_active', true)
            ->when($ignoreVersionId, fn ($query) => $query->whereKeyNot($ignoreVersionId))
            ->whereDate('effective_from', '<=', $effectiveTo ?: '9999-12-31')
            ->where(function ($query) use ($effectiveFrom): void {
                $query
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $effectiveFrom);
            })
            ->exists();
    }

    private function versionCoversDate(HrPolicyVersion $version, string $date): bool
    {
        if ($date < $version->effective_from->toDateString()) {
            return false;
        }

        if ($version->effective_to && $date > $version->effective_to->toDateString()) {
            return false;
        }

        return true;
    }

    private function assertCanAddPolicies(): void
    {
        abort_unless(Auth::user()?->canAddHrSetup() ?? false, 403);
    }

    private function assertCanEditPolicies(): void
    {
        abort_unless(Auth::user()?->canEditHrSetup() ?? false, 403);
    }
}
