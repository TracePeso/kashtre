<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Major Clinical Transitions</h4>

    @if ($statusMessage)
        <div class="mb-4 text-xs rounded p-2 bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-200">{{ $statusMessage }}</div>
    @endif

    @if ($errorMessage)
        <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 text-sm rounded p-3">
            {{ $errorMessage }}
        </div>
    @endif

    @if ($activeInstance)
        @php
            // The instance only carries process_code + next_step; the full
            // ordered step list (names, mandatory flag, required role) comes
            // from the process-registry dictionary already loaded above —
            // matched here so the progress list can be drawn without a
            // second round trip.
            $processDef = collect($availableProcesses)->firstWhere('process_code', $activeInstance->processCode);
            $steps = $processDef['steps'] ?? [];
            $nextOrder = $activeInstance->nextStep['step_order'] ?? null;
        @endphp

        <div class="mb-4">
            <div class="flex items-center justify-between">
                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $processDef['process_name'] ?? $activeInstance->processCode }}</span>
                <span class="text-xs px-2 py-0.5 rounded bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">{{ $activeInstance->status }}</span>
            </div>
        </div>

        <ol class="space-y-2">
            @forelse ($steps as $step)
                @php
                    $isDone = $nextOrder !== null && $step['step_order'] < $nextOrder;
                    $isCurrent = $nextOrder !== null && $step['step_order'] === $nextOrder;
                    // required_role may be a comma-separated list — a step is
                    // owned by ANY of them, not necessarily all (e.g. every
                    // seeded step ships as WARD_NURSE,DUTY_RESIDENT,CONSULTANT).
                    $requiredRoles = array_filter(array_map('trim', explode(',', (string) ($step['required_role'] ?? ''))));
                    $rolePermissionMap = [
                        'WARD_NURSE' => 'Act As Ward Nurse (Clinical)',
                        'DUTY_RESIDENT' => 'Manage Care Assignments',
                        'CONSULTANT' => 'Act As Consultant (Clinical)',
                    ];
                    $canActInRole = $requiredRoles === [] || collect($requiredRoles)
                        ->contains(fn ($role) => in_array($rolePermissionMap[$role] ?? null, auth()->user()->permissions ?? []));
                @endphp
                <li wire:key="step-{{ $step['step_code'] }}" class="flex items-start gap-3 p-2 rounded border-l-4
                    @if ($isDone) bg-gray-50 dark:bg-gray-700/50 border-l-green-400 dark:border-l-green-600
                    @elseif ($isCurrent) bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 border-l-amber-400 dark:border-l-amber-500
                    @else bg-white dark:bg-gray-800 border-l-gray-200 dark:border-l-gray-700 opacity-60 @endif">
                    {{-- A status badge on every step, not just the ones with
                         something to click — a step with nothing below it
                         (not yet reached) previously rendered completely
                         blank, indistinguishable at a glance from one whose
                         content had simply not rendered. Every step now says
                         its own state in words, regardless of contrast or
                         how quickly someone scans the list. --}}
                    <span class="mt-0.5 flex-shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold
                        {{ $isDone ? 'bg-green-500 text-white' : ($isCurrent ? 'bg-amber-500 text-white' : 'bg-gray-200 dark:bg-gray-600 text-gray-500 dark:text-gray-300') }}">
                        {{ $isDone ? '✓' : $step['step_order'] }}
                    </span>
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-900 dark:text-gray-100">{{ $step['step_name'] }}</span>
                            <span class="text-[10px] px-1.5 py-0.5 rounded font-medium uppercase
                                {{ $isDone ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                                    : ($isCurrent ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'
                                    : 'bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500') }}">
                                {{ $isDone ? 'Done' : ($isCurrent ? 'Current' : 'Not yet reached') }}
                            </span>
                            @unless ($step['is_mandatory'] ?? true)
                                <span class="text-[10px] text-gray-400">(optional)</span>
                            @endunless
                            @if ($requiredRoles !== [])
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">{{ implode(' / ', $requiredRoles) }}</span>
                            @endif
                        </div>

                        @if ($isCurrent)
                            @if (! $canActInRole)
                                <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                                    Only a user with one of these capacities can act on this step: {{ implode(', ', $requiredRoles) }}.
                                </p>
                            @elseif ($blockedStepCode === $step['step_code'])
                                {{-- PROCESS_STEP_BLOCKED — the refusal names why, and an audited
                                     override reason is the only way past it. --}}
                                <div class="mt-2 space-y-2 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded p-2" wire:key="blocked-{{ $step['step_code'] }}">
                                    <div class="text-xs font-medium text-red-700 dark:text-red-300">
                                        Cannot {{ $blockedWasSkip ? 'skip' : 'complete' }} this step:
                                    </div>
                                    <ul class="list-disc list-inside text-xs text-red-700 dark:text-red-300">
                                        @foreach ($blockedReasons as $reason)
                                            <li>{{ $reason }}</li>
                                        @endforeach
                                    </ul>
                                    <select wire:model="overrideReasonCode" class="w-full text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                                        <option value="">Select override reason&hellip;</option>
                                        @foreach ($overrideReasons as $reason)
                                            <option value="{{ $reason->code }}">{{ $reason->display_label }}</option>
                                        @endforeach
                                    </select>
                                    @error('overrideReasonCode') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
                                    <input type="text" wire:model="overrideNote" placeholder="Justification"
                                        class="w-full text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                                    <div class="flex gap-2">
                                        <button wire:click="overrideAndRetry('{{ $activeInstance->id }}')"
                                            class="text-xs text-white bg-red-600 hover:bg-red-700 rounded px-3 py-1">
                                            Override &amp; {{ $blockedWasSkip ? 'Skip' : 'Complete' }}
                                        </button>
                                        <button wire:click="cancelOverride" class="text-xs text-gray-500 hover:underline">Cancel</button>
                                    </div>
                                </div>
                            @else
                                @php
                                    // Confirmed against Clinical's own TransitionStepEffects
                                    // source 2026-08-22: these are the ONLY step codes whose
                                    // payload is read at all — every other step (the
                                    // majority — attestation/documentation steps) takes
                                    // nothing beyond an optional completion note, so it gets
                                    // nothing extra rendered here either.
                                    $needsBed = in_array($step['step_code'], ['BED_ALLOCATION', 'TRANSFER_REQUEST', 'BED_CUSTODY_TRANSFER'], true);
                                    $bedRequired = $step['step_code'] === 'BED_ALLOCATION';
                                    $needsLockNote = $step['step_code'] === 'DEATH_CERTIFICATE_SIGN_OFF';
                                    $needsExportFormat = $step['step_code'] === 'REFERRAL_SIGN_OFF';
                                @endphp
                                <div class="mt-2 space-y-2" wire:key="active-step-{{ $step['step_code'] }}">
                                    @if ($needsBed)
                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label class="block text-[10px] text-gray-500 dark:text-gray-400 mb-0.5">Ward</label>
                                                <select wire:model.live="stepTargetWardCode" class="w-full text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                                                    <option value="">Select&hellip;</option>
                                                    @foreach ($stepWards as $ward)
                                                        <option value="{{ $ward->ward_code }}">{{ $ward->ward_name ?? $ward->ward_code }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-gray-500 dark:text-gray-400 mb-0.5">
                                                    Bed {{ $bedRequired ? '(required)' : '(optional — can be named later)' }}
                                                </label>
                                                <select wire:model="selectedBedId" class="w-full text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                                                    <option value="">{{ $bedRequired ? 'Select…' : 'Leave unset for now' }}</option>
                                                    @foreach ($stepBedOptions as $bed)
                                                        <option value="{{ $bed->id }}">{{ $bed->bed_code }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    @elseif ($needsLockNote)
                                        <div>
                                            <label class="block text-[10px] text-gray-500 dark:text-gray-400 mb-0.5">Chart lock note</label>
                                            <textarea wire:model="lockNote" rows="2" placeholder="Defaults to a standard note if left blank"
                                                class="w-full text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600"></textarea>
                                        </div>
                                    @elseif ($needsExportFormat)
                                        <div>
                                            <label class="block text-[10px] text-gray-500 dark:text-gray-400 mb-0.5">Export format</label>
                                            <select wire:model="exportFormat" class="w-full text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                                                <option value="">FHIR JSON (default)</option>
                                                <option value="FHIR_XML">FHIR XML</option>
                                            </select>
                                        </div>
                                    @endif

                                    <input type="text" wire:model="stepNotes" placeholder="Notes (optional)"
                                        class="w-full text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                                    <div class="flex gap-2">
                                        <button wire:click="completeStep('{{ $activeInstance->id }}', '{{ $step['step_code'] }}')"
                                            class="text-xs text-white bg-blue-600 hover:bg-blue-700 rounded px-3 py-1">Complete</button>
                                        @unless ($step['is_mandatory'] ?? true)
                                            <button wire:click="skipStep('{{ $activeInstance->id }}', '{{ $step['step_code'] }}')"
                                                class="text-xs text-gray-600 dark:text-gray-300 hover:underline">Skip</button>
                                        @else
                                            <button wire:click="skipStep('{{ $activeInstance->id }}', '{{ $step['step_code'] }}')"
                                                class="text-xs text-amber-600 dark:text-amber-400 hover:underline">Skip (mandatory — needs override)</button>
                                        @endunless
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                </li>
            @empty
                <li class="text-xs text-gray-500 dark:text-gray-400">
                    Next: {{ $activeInstance->nextStep['step_name'] ?? '—' }}
                </li>
            @endforelse
        </ol>

        <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Abandon reason</label>
                <select wire:model="abandonReasonCode" class="text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                    <option value="">Select&hellip;</option>
                    @foreach ($abandonReasons as $reason)
                        <option value="{{ $reason->code }}">{{ $reason->display_label }}</option>
                    @endforeach
                </select>
                @error('abandonReasonCode') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
            </div>
            <input type="text" wire:model="abandonNote" placeholder="Note"
                class="text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
            <button wire:click="abandon('{{ $activeInstance->id }}')"
                wire:confirm="Abandon this {{ $processDef['process_name'] ?? $activeInstance->processCode }}? This closes out the whole transition, not just one step — it cannot be resumed."
                class="text-xs text-red-600 hover:underline pb-1.5">Abandon whole process</button>
        </div>
    @else
        <div class="flex gap-2 items-end">
            <div class="flex-1">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Start Transition</label>
                <select wire:model="selectedProcessCode" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                    <option value="">Select&hellip;</option>
                    @foreach ($availableProcesses as $process)
                        <option value="{{ $process['process_code'] }}">{{ $process['process_name'] }}</option>
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
            <button wire:click="toggleDecisionToAdmit" class="text-xs text-blue-700 dark:text-blue-300 hover:underline pb-2.5">
                {{ $showDecisionToAdmit ? 'Cancel' : 'Decision to Admit instead →' }}
            </button>
        </div>

        {{-- SRD §4.2: the OPD-to-ward handshake. Starts ADMISSION and, if a
             bed is named, reserves it — either way the receiving ward is
             told a patient is coming, which plain "Start Transition" does
             not do. --}}
        @if ($showDecisionToAdmit)
            <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700 grid grid-cols-2 sm:grid-cols-4 gap-2 items-end">
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Target Ward</label>
                    <select wire:model.live="admitTargetWardCode" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                        <option value="">Select&hellip;</option>
                        @foreach ($wards as $ward)
                            <option value="{{ $ward->ward_code }}">{{ $ward->ward_name ?? $ward->ward_code }}</option>
                        @endforeach
                    </select>
                    @error('admitTargetWardCode') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Specialty</label>
                    <input type="text" wire:model="admitTargetSpecialty" placeholder="Optional"
                        class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Bed</label>
                    <select wire:model="admitBedId" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                        <option value="">Leave for the ward to find one</option>
                        @foreach ($admitWardBeds as $bed)
                            <option value="{{ $bed->id }}">{{ $bed->bed_code }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Note</label>
                    <input type="text" wire:model="admitNote" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                </div>
                <button wire:click="decisionToAdmit" class="text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">
                    Request Admission
                </button>
            </div>
        @endif
    @endif

    @if ($history->isNotEmpty())
        <div class="mt-6">
            <h5 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">History</h5>
            <table class="min-w-full text-sm">
                <tbody>
                    @foreach ($history as $instance)
                        <tr wire:key="hist-{{ $instance->id }}" class="border-t border-gray-100 dark:border-gray-700">
                            <td class="py-1.5 text-gray-900 dark:text-gray-100">
                                {{ collect($availableProcesses)->firstWhere('process_code', $instance->processCode)['process_name'] ?? $instance->processCode }}
                            </td>
                            <td class="py-1.5 text-gray-500 dark:text-gray-400">{{ $instance->status }}</td>
                            <td class="py-1.5 text-gray-500 dark:text-gray-400">{{ $instance->completedAt }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
