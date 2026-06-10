<div>
    <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h2 class="text-2xl font-semibold text-gray-900">HR Policies</h2>
            <p class="mt-1 text-sm text-gray-500">Configure the active jurisdiction, policy versions, and the roster limits enforced during generation, save, and approval.</p>
        </div>
    </div>

    @if($message)
        <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ $message }}
        </div>
    @endif

    @error('policy')
        <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            {{ $message }}
        </div>
    @enderror
    @error('version')
        <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            {{ $message }}
        </div>
    @enderror

    <div class="grid gap-6 xl:grid-cols-[21rem_minmax(0,1fr)]">
        <section class="rounded-lg border border-gray-200 bg-white p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Policies</h3>
                    <p class="mt-1 text-sm text-gray-500">Choose the jurisdiction policy that should govern roster validation.</p>
                </div>
                @if($canAddPolicies)
                    <button type="button" wire:click="startCreatingPolicy" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        New Policy
                    </button>
                @endif
            </div>

            @if($policies->isEmpty())
                <div class="mt-4 rounded-md border border-dashed border-gray-300 bg-gray-50 px-4 py-5 text-sm text-gray-500">
                    No regional policies have been configured for this organization yet.
                </div>
            @else
                <div class="mt-4 space-y-3">
                    @foreach($policies as $policy)
                        @php($policyCurrentVersion = $this->currentVersion($policy))
                        <button type="button" wire:click="selectPolicy({{ $policy->id }})" class="block w-full rounded-md border px-4 py-3 text-left transition {{ $selectedPolicy && $selectedPolicy->id === $policy->id ? 'border-gray-900 bg-gray-50' : 'border-gray-200 bg-white hover:border-gray-300' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-gray-900">{{ $policy->name }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ $policy->policy_code }} @if($policy->jurisdiction) / {{ $policy->jurisdiction }} @endif</p>
                                    <p class="mt-2 text-xs text-gray-500">
                                        {{ $policyCurrentVersion?->version_label ? 'Current version: '.$policyCurrentVersion->version_label : 'No current active version' }}
                                    </p>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $policy->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                                    {{ $policy->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </button>
                    @endforeach
                </div>
            @endif
        </section>

        <div class="space-y-6">
            <section class="rounded-lg border border-gray-200 bg-white p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ $editingPolicyId ? 'Edit Policy' : 'Create Policy' }}</h3>
                        <p class="mt-1 text-sm text-gray-500">Only the selected active policy is used by the roster validator and generator.</p>
                    </div>
                    @if($selectedPolicy && $canEditPolicies)
                        <div class="flex flex-wrap gap-2">
                            @if($selectedPolicy->is_active)
                                <button type="button" wire:click="deactivatePolicy({{ $selectedPolicy->id }})" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                    Deactivate
                                </button>
                            @else
                                <button type="button" wire:click="activatePolicy({{ $selectedPolicy->id }})" class="inline-flex items-center justify-center rounded-md border border-transparent bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                                    Set Active
                                </button>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Policy Code</label>
                        <input type="text" wire:model="policyCode" @disabled(! $canAddPolicies && ! $canEditPolicies) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 sm:text-sm">
                        @error('policyCode') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Policy Name</label>
                        <input type="text" wire:model="policyName" @disabled(! $canAddPolicies && ! $canEditPolicies) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 sm:text-sm">
                        @error('policyName') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Country Code</label>
                        <input type="text" wire:model="countryCode" maxlength="3" @disabled(! $canAddPolicies && ! $canEditPolicies) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 sm:text-sm">
                        @error('countryCode') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Jurisdiction</label>
                        <input type="text" wire:model="jurisdiction" @disabled(! $canAddPolicies && ! $canEditPolicies) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 sm:text-sm">
                        @error('jurisdiction') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Description</label>
                        <textarea wire:model="policyDescription" rows="3" @disabled(! $canAddPolicies && ! $canEditPolicies) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 sm:text-sm"></textarea>
                        @error('policyDescription') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="inline-flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="policyIsActive" @disabled(! $canAddPolicies && ! $canEditPolicies) class="rounded border-gray-300 text-gray-900 shadow-sm focus:ring-gray-900">
                            Make this the active policy for this organization
                        </label>
                    </div>
                </div>

                @if($canAddPolicies || $canEditPolicies)
                    <div class="mt-5 flex flex-wrap justify-end gap-3">
                        @if($canAddPolicies)
                            <button type="button" wire:click="startCreatingPolicy" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                New Policy
                            </button>
                        @endif
                        <button type="button" wire:click="savePolicy" class="inline-flex items-center justify-center rounded-md border border-transparent bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                            Save Policy
                        </button>
                    </div>
                @endif
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Policy Versions</h3>
                        <p class="mt-1 text-sm text-gray-500">Versions carry the actual roster limits and effective date ranges.</p>
                    </div>
                    @if($selectedPolicy && $canAddPolicies)
                        <button type="button" wire:click="startCreatingVersion" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            New Version
                        </button>
                    @endif
                </div>

                @if(! $selectedPolicy)
                    <div class="mt-4 rounded-md border border-dashed border-gray-300 bg-gray-50 px-4 py-5 text-sm text-gray-500">
                        Create or select a policy before configuring versions.
                    </div>
                @else
                    <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
                        <div class="overflow-x-auto rounded-md border border-gray-200">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Version</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Effective</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Weekly Std</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Daily Cap</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    @forelse($selectedPolicyVersions as $version)
                                        <tr>
                                            <td class="px-4 py-3 align-top">
                                                <p class="text-sm font-semibold text-gray-900">{{ $version->version_label }}</p>
                                                <p class="mt-1 text-xs text-gray-500">Abs: {{ $this->formatHours($version->weekly_absolute_ceiling_minutes) }}</p>
                                            </td>
                                            <td class="px-4 py-3 align-top text-sm text-gray-700">{{ $this->formatDateRange($version) }}</td>
                                            <td class="px-4 py-3 align-top text-sm text-gray-700">{{ $this->formatHours($version->weekly_standard_minutes) }}</td>
                                            <td class="px-4 py-3 align-top text-sm text-gray-700">{{ $this->formatHours($version->daily_net_cap_minutes) }}</td>
                                            <td class="px-4 py-3 align-top">
                                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $version->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                                                    {{ $version->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 align-top text-right">
                                                <div class="flex flex-wrap justify-end gap-2">
                                                    @if($canEditPolicies)
                                                        <button type="button" wire:click="editVersion({{ $version->id }})" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                                            Edit
                                                        </button>
                                                        @if($version->is_active)
                                                            <button type="button" wire:click="deactivateVersion({{ $version->id }})" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                                                Deactivate
                                                            </button>
                                                        @else
                                                            <button type="button" wire:click="activateVersion({{ $version->id }})" class="inline-flex items-center justify-center rounded-md border border-transparent bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-800">
                                                                Activate
                                                            </button>
                                                        @endif
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-4 py-6 text-sm text-gray-500">
                                                No versions have been added for this policy yet.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="rounded-md border border-gray-200 bg-gray-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Current Coverage</p>
                            @if($selectedPolicy->is_active && $currentVersion)
                                <div class="mt-3 space-y-2">
                                    <p class="text-sm font-semibold text-gray-900">{{ $currentVersion->version_label }}</p>
                                    <p class="text-xs text-gray-500">{{ $this->formatDateRange($currentVersion) }}</p>
                                    <p class="text-sm text-gray-700">Weekly standard: {{ $this->formatHours($currentVersion->weekly_standard_minutes) }}</p>
                                    <p class="text-sm text-gray-700">Weekly absolute ceiling: {{ $this->formatHours($currentVersion->weekly_absolute_ceiling_minutes) }}</p>
                                    <p class="text-sm text-gray-700">Daily cap: {{ $this->formatHours($currentVersion->daily_net_cap_minutes) }}</p>
                                    <p class="text-sm text-gray-700">Rest gap: {{ $this->formatHours($currentVersion->minimum_rest_gap_minutes) }}</p>
                                    <p class="text-sm text-gray-700">Anchor window: {{ $this->formatHours($currentVersion->anchor_window_minutes) }}</p>
                                </div>
                            @elseif($selectedPolicy->is_active)
                                <p class="mt-3 text-sm text-amber-700">This policy is active, but there is no active version covering today.</p>
                            @else
                                <p class="mt-3 text-sm text-gray-600">This policy is inactive. Versions here will not be enforced until the policy is activated.</p>
                            @endif
                        </div>
                    </div>

                    <div class="mt-6 border-t border-gray-200 pt-6">
                        <h4 class="text-base font-semibold text-gray-900">{{ $editingVersionId ? 'Edit Version' : 'Create Version' }}</h4>
                        <p class="mt-1 text-sm text-gray-500">Use hours for every threshold. Values are converted to minutes internally for roster validation.</p>

                        <div class="mt-4 grid gap-4 lg:grid-cols-3">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Version Label</label>
                                <input type="text" wire:model="versionLabel" @disabled(! $canAddPolicies && ! $canEditPolicies) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 sm:text-sm">
                                @error('versionLabel') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Effective From</label>
                                <input type="date" wire:model="effectiveFrom" @disabled(! $canAddPolicies && ! $canEditPolicies) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 sm:text-sm">
                                @error('effectiveFrom') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Effective To</label>
                                <input type="date" wire:model="effectiveTo" @disabled(! $canAddPolicies && ! $canEditPolicies) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 sm:text-sm">
                                @error('effectiveTo') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Weekly Standard Hours</label>
                                <input type="number" min="0.1" step="0.1" wire:model="weeklyStandardHours" @disabled(! $canAddPolicies && ! $canEditPolicies) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 sm:text-sm">
                                @error('weeklyStandardHours') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Weekly Absolute Ceiling Hours</label>
                                <input type="number" min="0.1" step="0.1" wire:model="weeklyAbsoluteCeilingHours" @disabled(! $canAddPolicies && ! $canEditPolicies) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 sm:text-sm">
                                @error('weeklyAbsoluteCeilingHours') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Daily Net Cap Hours</label>
                                <input type="number" min="0.1" step="0.1" wire:model="dailyNetCapHours" @disabled(! $canAddPolicies && ! $canEditPolicies) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 sm:text-sm">
                                @error('dailyNetCapHours') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Minimum Rest Gap Hours</label>
                                <input type="number" min="0" step="0.1" wire:model="minimumRestGapHours" @disabled(! $canAddPolicies && ! $canEditPolicies) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 sm:text-sm">
                                @error('minimumRestGapHours') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Consecutive Work Days Limit</label>
                                <input type="number" min="1" wire:model="consecutiveWorkDaysLimit" @disabled(! $canAddPolicies && ! $canEditPolicies) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 sm:text-sm">
                                @error('consecutiveWorkDaysLimit') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Rest After Consecutive Days Hours</label>
                                <input type="number" min="0" step="0.1" wire:model="restAfterConsecutiveDaysHours" @disabled(! $canAddPolicies && ! $canEditPolicies) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 sm:text-sm">
                                @error('restAfterConsecutiveDaysHours') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Anchor Window Hours</label>
                                <input type="number" min="0" step="0.1" wire:model="anchorWindowHours" @disabled(! $canAddPolicies && ! $canEditPolicies) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 sm:text-sm">
                                @error('anchorWindowHours') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Overtime Trigger Hours</label>
                                <input type="number" min="0" step="0.1" wire:model="overtimeTriggerHours" @disabled(! $canAddPolicies && ! $canEditPolicies) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 sm:text-sm">
                                @error('overtimeTriggerHours') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                            </div>
                            <div class="lg:col-span-3">
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Holiday Compensation Settings</label>
                                <p class="mt-1 text-xs text-gray-500">Configure crossing-holiday shifts and within-holiday shifts separately. Crossing-holiday shifts still use their own rule, but the awarded credit is calculated as 0%, 25%, 50%, 75% or 100% of the within-holiday credit.</p>
                                <div class="mt-3 grid gap-4 lg:grid-cols-2">
                                    <div class="rounded-md border border-gray-200 bg-white p-4">
                                        <p class="text-sm font-semibold text-gray-900">{{ $holidayCompensatoryCreditScopeOptions[\App\Models\HrPolicyVersion::HOLIDAY_COMPENSATORY_SCOPE_CROSSING_PUBLIC_HOLIDAY] }}</p>
                                        <div class="mt-3">
                                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Percentage Of Shift Within Public Holidays</label>
                                            @php
                                                $selectedCrossingHolidayRatio = ((int) $selectedCrossingHolidayPercentage) / 100;
                                                $withinHolidayCreditPreview = \App\Models\HrPolicyVersion::normalizeHolidayCompensatoryCreditDays((float) ($withinHolidayCreditDays ?? 0));
                                                $crossingHolidayCreditPreview = \App\Models\HrPolicyVersion::normalizeHolidayCompensatoryCreditDays($withinHolidayCreditPreview * $selectedCrossingHolidayRatio);
                                                $formattedCrossingHolidayCreditPreview = rtrim(rtrim(number_format($crossingHolidayCreditPreview, 2, '.', ''), '0'), '.');
                                                $formattedWithinHolidayCreditPreview = rtrim(rtrim(number_format($withinHolidayCreditPreview, 2, '.', ''), '0'), '.');
                                            @endphp
                                            <input type="number" min="0" max="100" step="25" wire:model.live="selectedCrossingHolidayPercentage" class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                            <p class="mt-2 text-xs font-medium text-sky-700">
                                                Preview: {{ (int) $selectedCrossingHolidayPercentage }}% of {{ $formattedWithinHolidayCreditPreview !== '' ? $formattedWithinHolidayCreditPreview : '0' }} day(s) = {{ $formattedCrossingHolidayCreditPreview !== '' ? $formattedCrossingHolidayCreditPreview : '0' }} day(s).
                                            </p>
                                            <p class="mt-2 text-xs text-gray-500">Increase or reduce this field in 25% steps. The system applies the selected percentage to the credit days configured for shifts fully within public holidays.</p>
                                        </div>
                                    </div>
                                    <div class="rounded-md border border-gray-200 bg-white p-4">
                                        <p class="text-sm font-semibold text-gray-900">{{ $holidayCompensatoryCreditScopeOptions[\App\Models\HrPolicyVersion::HOLIDAY_COMPENSATORY_SCOPE_WITHIN_PUBLIC_HOLIDAY] }}</p>
                                        <div class="mt-3">
                                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Credit Days</label>
                                            <input type="number" min="0" step="0.25" wire:model="withinHolidayCreditDays" @disabled(! $canAddPolicies && ! $canEditPolicies) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 sm:text-sm">
                                            <p class="mt-1 text-xs text-gray-500">Use this when the entire worked shift falls inside a public holiday period.</p>
                                            @error('withinHolidayCreditDays') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="lg:col-span-3">
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Notes</label>
                                <textarea wire:model="versionNotes" rows="3" @disabled(! $canAddPolicies && ! $canEditPolicies) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 sm:text-sm"></textarea>
                                @error('versionNotes') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                            </div>
                            <div class="lg:col-span-3">
                                <label class="inline-flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700">
                                    <input type="checkbox" wire:model="versionIsActive" @disabled(! $canAddPolicies && ! $canEditPolicies) class="rounded border-gray-300 text-gray-900 shadow-sm focus:ring-gray-900">
                                    Mark this version as active for its date range
                                </label>
                            </div>
                        </div>

                        @if($canAddPolicies || $canEditPolicies)
                            <div class="mt-5 flex flex-wrap justify-end gap-3">
                                @if($canAddPolicies)
                                    <button type="button" wire:click="startCreatingVersion" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                        New Version
                                    </button>
                                @endif
                                <button type="button" wire:click="saveVersion" class="inline-flex items-center justify-center rounded-md border border-transparent bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                                    Save Version
                                </button>
                            </div>
                        @endif
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>
