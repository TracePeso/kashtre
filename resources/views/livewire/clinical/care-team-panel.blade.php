<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Care Team</h4>

    @if ($statusMessage)
        <div class="mb-4 text-xs rounded p-2 bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-200">{{ $statusMessage }}</div>
    @endif

    @if ($errorMessage)
        <div class="mb-4 text-xs rounded p-2 bg-amber-50 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-200">
            {{ $errorMessage }}
        </div>
    @endif

    @php
        $members = $team['members'] ?? [];
        $assignments = $team['assignments'] ?? (($team['assignment'] ?? null) ? [$team['assignment']] : []);
        $isUnassigned = $team['is_unassigned'] ?? empty($assignments);
    @endphp

    @if ($isUnassigned)
        <p class="text-xs text-amber-600 dark:text-amber-400 mb-4">Nobody is currently responsible for this patient.</p>
    @else
        <div class="flex flex-wrap gap-2 mb-4">
            @foreach ($members as $member)
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs
                    {{ ($member['participation'] ?? 'PRIMARY') === 'CO_MANAGING'
                        ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300'
                        : 'bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300' }}">
                    {{ $member['role_code'] ?? 'MEMBER' }} — user #{{ $member['user_id'] ?? '?' }}
                    <span class="text-[10px] opacity-70">({{ $member['participation'] ?? 'PRIMARY' }})</span>
                </span>
            @endforeach
        </div>

        @if (($team['team'] ?? null))
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Team: {{ $team['team'] }}</p>
        @endif

        @if (! empty($assignments))
            <div class="mb-4">
                <table class="min-w-full text-xs">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400 uppercase">
                            <th class="pb-1">Model</th>
                            <th class="pb-1">Participation</th>
                            <th class="pb-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($assignments as $row)
                            <tr wire:key="assign-{{ $row['id'] ?? $loop->index }}" class="border-t border-gray-50 dark:border-gray-700">
                                <td class="py-1 text-gray-700 dark:text-gray-300">{{ $row['assignment_model'] ?? '—' }}</td>
                                <td class="py-1 text-gray-700 dark:text-gray-300">{{ $row['participation'] ?? 'PRIMARY' }}</td>
                                <td class="py-1 text-right">
                                    @if (! empty($row['id']))
                                        <button wire:click="endAssignment('{{ $row['id'] }}')"
                                            wire:confirm="End this assignment? The clinician named on it is no longer responsible for this patient."
                                            class="text-red-600 dark:text-red-400 hover:underline">End</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    <div class="pt-3 border-t border-gray-100 dark:border-gray-700">
        <h5 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Change of Care</h5>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Model</label>
                <select wire:model.live="assignmentModel" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                    <option value="INDIVIDUAL">Individual</option>
                    <option value="TEAM">Team</option>
                    <option value="ROLE">Role (on-duty)</option>
                    <option value="HYBRID">Hybrid</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Participation</label>
                <select wire:model="participation" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                    <option value="PRIMARY">Primary (hand over)</option>
                    <option value="CO_MANAGING">Co-managing (add alongside)</option>
                </select>
            </div>

            @if (in_array($assignmentModel, ['INDIVIDUAL', 'HYBRID']))
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Clinical Staff Available</label>
                    <select wire:model="selectedStaffId" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                        <option value="">None</option>
                        @foreach ($clinicalStaff as $staff)
                            <option value="{{ $staff->value }}">{{ $staff->label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if (in_array($assignmentModel, ['TEAM', 'HYBRID']))
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Care Team</label>
                    <select wire:model="assignedTeamId" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                        <option value="">Select&hellip;</option>
                        @foreach ($careTeams as $careTeam)
                            <option value="{{ $careTeam['id'] ?? '' }}">{{ $careTeam['team_name'] ?? $careTeam['name'] ?? ('Team '.($careTeam['id'] ?? '')) }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if ($assignmentModel === 'ROLE')
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Role Code</label>
                    <input type="text" wire:model="assignedRoleCode" placeholder="e.g. DUTY_RESIDENT"
                        class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                </div>
            @endif
        </div>

        <button wire:click="assign" class="mt-3 text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">
            {{ $participation === 'CO_MANAGING' ? 'Add Co-Managing' : 'Hand Over Care' }}
        </button>
    </div>
</div>
