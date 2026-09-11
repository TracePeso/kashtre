<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
    <div class="flex flex-wrap items-end justify-between gap-2 mb-3">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">My Work</h4>
        <div class="flex flex-wrap gap-2 items-end">
            <div>
                <label class="block text-[10px] font-medium text-gray-500 dark:text-gray-400 mb-1">Scope</label>
                <select wire:model.live="scope" class="text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                    <option value="MY_PATIENTS">My Patients</option>
                    <option value="MY_WARD">My Ward</option>
                    <option value="MY_TEAM">My Team</option>
                    <option value="MY_CURRENT_WORK">My Current Work</option>
                </select>
            </div>
            @if ($scope === 'MY_WARD')
                <input type="text" wire:model.live.debounce.500ms="wardCode" placeholder="Ward code"
                    class="text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
            @endif
            <input type="text" wire:model.live.debounce.500ms="specialty" placeholder="Specialty"
                class="text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 w-24">
            <input type="text" wire:model.live.debounce.500ms="roomNumber" placeholder="Room"
                class="text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 w-20">
            <input type="text" wire:model.live.debounce.500ms="pathwayCode" placeholder="Pathway"
                class="text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 w-28">
        </div>
    </div>

    @if ($needsWard)
        <p class="text-xs text-gray-400">Enter a ward code for this scope.</p>
    @elseif (! $result)
        <p class="text-xs text-gray-400">Nothing to show.</p>
    @else
        @php $summary = $result['summary'] ?? []; @endphp
        <div class="flex gap-4 mb-3 text-xs text-gray-500 dark:text-gray-400">
            @if (($result['patient_count'] ?? null) !== null)
                <span>{{ $result['patient_count'] }} patient(s)</span>
            @endif
            <span>{{ $summary['work_orders'] ?? 0 }} work orders</span>
            <span>{{ $summary['observations_due'] ?? 0 }} observations due</span>
            <span class="{{ ($summary['alerts'] ?? 0) > 0 ? 'text-red-600 dark:text-red-400 font-medium' : '' }}">{{ $summary['alerts'] ?? 0 }} alerts</span>
        </div>

        @foreach ([
            'work_orders' => 'Work Orders',
            'outstanding_observations' => 'Outstanding Observations',
            'unacknowledged_alerts' => 'Unacknowledged Alerts',
            'enterprise_queues' => 'Enterprise Queues',
        ] as $key => $label)
            @if (! empty($result[$key]))
                <div class="mt-2">
                    <h5 class="text-[10px] font-medium text-gray-400 uppercase mb-1">{{ $label }}</h5>
                    <ul class="text-xs text-gray-700 dark:text-gray-300 space-y-0.5">
                        @foreach ($result[$key] as $item)
                            <li wire:key="{{ $key }}-{{ $loop->index }}">
                                {{ is_array($item) ? ($item['description'] ?? $item['label'] ?? $item['title'] ?? json_encode($item)) : $item }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endforeach

        @if (empty($result['work_orders']) && empty($result['outstanding_observations']) && empty($result['unacknowledged_alerts']) && empty($result['enterprise_queues']))
            <p class="text-xs text-gray-400">Nothing outstanding in this scope.</p>
        @endif
    @endif
</div>
