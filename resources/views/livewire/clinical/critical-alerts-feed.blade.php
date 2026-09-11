<!-- Every 30s of background polling here also re-renders every other
     component on this page (Livewire batches a page's components into one
     request), so this alone was generating ~4x/minute of Clinical Module
     traffic from a tab that may just be sitting open unattended — a real,
     confirmed contributor to the "Clinical Module could not be reached"
     failures elsewhere (this page's own MyWorkBoard reads were timing out
     in lockstep with this exact cadence, per storage/logs/laravel.log
     2026-08-22 22:18-22:20). Widened to 2 minutes — critical alerts still
     refresh well within a clinically meaningful window, at a quarter of
     the request volume against a single-threaded dev server. -->
<div wire:poll.120s class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
    <div class="flex items-center justify-between mb-2">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Critical Alerts</h4>
        <select wire:model.live="scope" class="text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
            <option value="MY_PATIENTS">My Patients</option>
            <option value="WARD">Ward</option>
            <option value="ALL">All</option>
        </select>
    </div>

    @if ($scope === 'WARD')
        <input type="text" wire:model.live.debounce.500ms="wardCode" placeholder="Ward code, e.g. ICU"
            class="w-full text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 mb-2">
    @endif

    @if ($needsWard)
        <p class="text-xs text-gray-400">Enter a ward code.</p>
    @elseif (empty($feed['alerts']))
        <p class="text-xs text-gray-400">Nothing outstanding.</p>
    @else
        @if (! empty($feed['by_severity']))
            <div class="flex gap-2 mb-2">
                @foreach ($feed['by_severity'] as $tier => $count)
                    <span class="text-[10px] px-2 py-0.5 rounded uppercase
                        {{ $tier === 'CRITICAL_PANIC' ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' }}">
                        {{ $tier }}: {{ $count }}
                    </span>
                @endforeach
            </div>
        @endif

        @if ($stepError)
            <div class="mb-2 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 text-[11px] rounded p-2">{{ $stepError }}</div>
        @endif

        <div class="space-y-1 max-h-80 overflow-y-auto">
            @foreach ($feed['alerts'] as $alert)
                <div wire:key="alert-{{ $alert['id'] }}" class="text-xs py-1.5 border-t border-gray-50 dark:border-gray-700">
                    <div class="flex items-center justify-between
                        {{ ($alert['severity_tier'] ?? '') === 'CRITICAL_PANIC' ? 'text-red-700 dark:text-red-300' : 'text-amber-700 dark:text-amber-300' }}">
                        <div>
                            <span class="font-medium">{{ $alert['alert_label'] ?? '' }}</span>
                            — {{ $alert['patient_id'] ?? '' }}
                            @if (isset($alert['observed_value']))
                                ({{ $alert['observed_value'] }}{{ $alert['unit_label'] ?? '' }})
                            @endif
                            <span class="text-gray-400">{{ $alert['created_at'] ?? '' }}</span>
                        </div>
                        @if (empty($alert['acknowledged_at']))
                            <button wire:click="acknowledge('{{ $alert['id'] }}')" class="text-blue-700 dark:text-blue-300 hover:underline whitespace-nowrap ml-2">
                                Acknowledge
                            </button>
                        @else
                            {{-- v6.1 Phase 8 closed-loop follow-up: acknowledged is only
                                 the first step — review, action and close each get their
                                 own recorded state, not implied by this one flag. --}}
                            <div class="flex items-center gap-2 whitespace-nowrap ml-2">
                                <span class="text-gray-400">Acknowledged</span>
                                <button wire:click="openStep('{{ $alert['id'] }}', 'review')" class="text-blue-700 dark:text-blue-300 hover:underline">Review</button>
                                <button wire:click="openStep('{{ $alert['id'] }}', 'action')" class="text-blue-700 dark:text-blue-300 hover:underline">Action</button>
                                <button wire:click="openStep('{{ $alert['id'] }}', 'close')" class="text-green-700 dark:text-green-300 hover:underline">Close</button>
                            </div>
                        @endif
                    </div>

                    @if ($openAlertId == $alert['id'])
                        <div class="mt-1.5 flex items-center gap-2 bg-gray-50 dark:bg-gray-900/40 rounded p-2">
                            <span class="text-[10px] text-gray-500 uppercase w-14">{{ $openAlertStep }}</span>
                            <input type="text" wire:model="stepInput"
                                placeholder="{{ $openAlertStep === 'review' ? 'Review notes…' : ($openAlertStep === 'action' ? 'Action taken…' : 'Closure reason…') }}"
                                class="flex-1 text-[11px] rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                            <button wire:click="submitStep" class="text-[11px] text-white bg-blue-600 hover:bg-blue-700 rounded px-2 py-1">Save</button>
                            <button wire:click="closeStep" class="text-[11px] text-gray-500 hover:underline">Cancel</button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($feed['oldest_unacknowledged_at'] ?? null)
            <p class="text-[10px] text-gray-400 mt-2">Oldest unacknowledged: {{ $feed['oldest_unacknowledged_at'] }}</p>
        @endif
    @endif
</div>
