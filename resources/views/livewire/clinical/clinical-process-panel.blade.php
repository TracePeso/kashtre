<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Major Clinical Transitions</h4>

    @if ($errorMessage)
        <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 text-sm rounded p-3">
            {{ $errorMessage }}
        </div>
    @endif

    @if ($activeExecution)
        <div class="mb-4">
            <div class="flex items-center justify-between">
                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $activeExecution->process->process_name }}</span>
                <span class="text-xs px-2 py-0.5 rounded bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">{{ $activeExecution->status }}</span>
            </div>
            @if ($activeExecution->initiation_note)
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $activeExecution->initiation_note }}</p>
            @endif
        </div>

        <ol class="space-y-2">
            @foreach ($steps as $step)
                @php $execRecord = $stepExecutions->get($step->id); @endphp
                <li wire:key="step-{{ $step->id }}" class="flex items-start gap-3 p-2 rounded
                    @if ($execRecord) bg-gray-50 dark:bg-gray-700/50
                    @elseif ($activeExecution->current_step_id === $step->id) bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800
                    @else opacity-50 @endif">
                    <div class="flex-1">
                        <div class="text-sm text-gray-900 dark:text-gray-100">
                            {{ $step->step_order }}. {{ $step->step_name }}
                            @unless ($step->is_mandatory)
                                <span class="text-[10px] text-gray-400">(optional)</span>
                            @endunless
                            @if ($step->required_role)
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">{{ $step->required_role }} only</span>
                            @endif
                        </div>

                        @if ($execRecord)
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $execRecord->status }} @if ($execRecord->override_reason) — {{ $execRecord->override_reason }} @endif
                            </div>
                        @elseif ($activeExecution->current_step_id === $step->id)
                            @php
                                $rolePermission = match ($step->required_role) {
                                    'WARD_NURSE' => 'Act As Ward Nurse (Clinical)',
                                    'CONSULTANT' => 'Act As Consultant (Clinical)',
                                    default => null,
                                };
                                $canActInRole = ! $step->required_role || in_array($rolePermission, auth()->user()->permissions ?? []);
                            @endphp
                            @if (! $canActInRole)
                                <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                                    Only a user with the "{{ $step->required_role }}" capacity can complete this step.
                                </p>
                            @else
                                <div class="mt-2 space-y-2" wire:key="active-step-{{ $step->id }}">
                                    @if ($step->side_effect === 'ALLOCATE_BED')
                                        <select wire:model="selectedBedId" class="text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                                            <option value="">Select bed&hellip;</option>
                                            @foreach ($availableBeds as $bed)
                                                <option value="{{ $bed->id }}">{{ $bed->ward->ward_name }} — {{ $bed->bed_code }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    <input type="text" wire:model="stepNotes" placeholder="Notes (optional)"
                                        class="w-full text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                                    <div class="flex gap-2">
                                        <button wire:click="completeStep({{ $activeExecution->id }})"
                                            class="text-xs text-white bg-blue-600 hover:bg-blue-700 rounded px-3 py-1">Complete</button>
                                        @unless ($step->is_mandatory)
                                            <button wire:click="skipStep({{ $activeExecution->id }})"
                                                class="text-xs text-gray-500 hover:underline">Skip</button>
                                        @else
                                            <input type="text" wire:model="skipReason" placeholder="Override reason to skip"
                                                class="text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                                            <button wire:click="skipStep({{ $activeExecution->id }})"
                                                class="text-xs text-amber-600 hover:underline">Override &amp; Skip</button>
                                        @endunless
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @else
        <div class="flex gap-2 items-end">
            <div class="flex-1">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Start Transition</label>
                <select wire:model="selectedProcessCode" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                    <option value="">Select&hellip;</option>
                    @foreach ($availableProcesses as $process)
                        <option value="{{ $process->process_code }}">{{ $process->process_name }}</option>
                    @endforeach
                </select>
                @error('selectedProcessCode') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
            </div>
            <div class="flex-1">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Note</label>
                <input type="text" wire:model="initiationNote" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            </div>
            <button wire:click="startProcess" class="text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">
                Start
            </button>
        </div>
    @endif

    @if ($history->isNotEmpty())
        <div class="mt-6">
            <h5 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">History</h5>
            <table class="min-w-full text-sm">
                <tbody>
                    @foreach ($history as $execution)
                        <tr wire:key="hist-{{ $execution->id }}" class="border-t border-gray-100 dark:border-gray-700">
                            <td class="py-1.5 text-gray-900 dark:text-gray-100">{{ $execution->process->process_name }}</td>
                            <td class="py-1.5 text-gray-500 dark:text-gray-400">{{ $execution->status }}</td>
                            <td class="py-1.5 text-gray-500 dark:text-gray-400">{{ $execution->completed_at }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
