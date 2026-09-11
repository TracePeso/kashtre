<div class="space-y-4">
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 flex flex-wrap items-end gap-3">
        <div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Shift Handover</h3>
            @if (! $needsWard && ($result['generated_at'] ?? null))
                <p class="text-xs text-gray-500 dark:text-gray-400">Compiled {{ $result['generated_at'] }}</p>
            @endif
        </div>
        <div class="flex-1"></div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Scope</label>
            <select wire:model.live="scope" class="text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                <option value="MY_PATIENTS">My Patients</option>
                <option value="MY_TEAM">My Team</option>
                <option value="MY_WARD">My Ward</option>
            </select>
        </div>
        @if ($scope === 'MY_WARD')
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Ward Code</label>
                <input type="text" wire:model.live.debounce.500ms="wardCode" placeholder="e.g. ICU"
                    class="text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
            </div>
        @endif
    </div>

    @if ($needsWard)
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 text-sm text-gray-500 dark:text-gray-400">
            Enter a ward code to hand over a ward.
        </div>
    @elseif (empty($result['patients']))
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 text-sm text-gray-500 dark:text-gray-400">
            Nothing to hand over in this scope right now.
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr class="text-left text-xs text-gray-500 dark:text-gray-400 uppercase">
                        <th class="px-4 py-2">Patient</th>
                        <th class="px-4 py-2">Location</th>
                        <th class="px-4 py-2">Ownership</th>
                        <th class="px-4 py-2">Critical Alerts</th>
                        <th class="px-4 py-2">Open Tasks</th>
                        <th class="px-4 py-2">Observations</th>
                        <th class="px-4 py-2">Meds Due</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($result['patients'] as $patient)
                        @php
                            $loc = $patient['location'] ?? [];
                            $own = $patient['ownership'] ?? [];
                            $alerts = $patient['critical_alerts'] ?? ['count' => 0];
                            $tasks = $patient['open_tasks'] ?? ['count' => 0, 'overdue' => 0];
                            $obs = $patient['observations'] ?? ['outstanding' => 0, 'overdue' => 0, 'missing' => 0];
                            $meds = $patient['medications'] ?? ['doses_due' => 0];
                        @endphp
                        <tr wire:key="ho-{{ $patient['patient_id'] ?? $loop->index }}" class="border-t border-gray-100 dark:border-gray-700
                            {{ ($alerts['count'] ?? 0) > 0 ? 'bg-red-50/50 dark:bg-red-900/10' : '' }}">
                            <td class="px-4 py-2 font-medium text-gray-900 dark:text-gray-100">
                                <a href="{{ route('clinical.observations.show', ['clientId' => $patient['patient_id'] ?? '']) }}" class="hover:underline">
                                    {{ $patient['patient_id'] ?? '—' }}
                                </a>
                            </td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                                {{ $loc['ward_name'] ?? $loc['ward_code'] ?? '—' }}
                                @if (! empty($loc['bed_code']))
                                    <span class="text-gray-400">/ {{ $loc['bed_code'] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                                {{ $own['assignment_model'] ?? '—' }}
                                @if (($own['co_managing_count'] ?? 0) > 0)
                                    <span class="text-[10px] text-blue-600 dark:text-blue-400">+{{ $own['co_managing_count'] }} co-managing</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                @if (($alerts['count'] ?? 0) > 0)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">
                                        {{ $alerts['count'] }}
                                    </span>
                                @else
                                    <span class="text-gray-300 dark:text-gray-600">0</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                                {{ $tasks['count'] ?? 0 }}
                                @if (($tasks['overdue'] ?? 0) > 0)
                                    <span class="text-[10px] text-amber-600 dark:text-amber-400">({{ $tasks['overdue'] }} overdue)</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                                {{ $obs['outstanding'] ?? 0 }} outstanding
                                @if (($obs['missing'] ?? 0) > 0)
                                    <span class="text-[10px] text-red-600 dark:text-red-400">({{ $obs['missing'] }} missing)</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $meds['doses_due'] ?? 0 }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
