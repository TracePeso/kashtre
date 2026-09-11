<div class="space-y-6">

    {{-- Patient/visit identity dropped here — the page-level banner above
         every clinical panel (clinical/observations/show.blade.php) already
         shows it; this block's only remaining job is the care-assignment
         notice, so it renders nothing at all once a relationship exists. --}}
    @unless ($hasActiveRelationship)
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
            <div class="flex items-center justify-end gap-2">
                <span class="text-xs text-amber-600 dark:text-amber-400">No active care assignment for you on this patient.</span>
                @if (in_array('Act As Ward Nurse (Clinical)', auth()->user()->permissions ?? []))
                    <button wire:click="claim('nurse')" class="text-xs px-2 py-1 rounded border border-gray-300 dark:border-gray-600">Claim as Nurse</button>
                @endif
                @if (in_array('Act As Consultant (Clinical)', auth()->user()->permissions ?? []))
                    <button wire:click="claim('doctor')" class="text-xs px-2 py-1 rounded border border-gray-300 dark:border-gray-600">Claim as Doctor</button>
                @endif
            </div>
        </div>
    @endunless

    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Record Observations</h4>

        @if (count($captureErrors))
            <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 text-sm rounded p-3">
                <ul class="list-disc list-inside">
                    @foreach ($captureErrors as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @php
                // BMI/eGFR are computed from other fields on this same form
                // (CaptureObservations::recalculateDerivedFields()) — never
                // hand-typed, so the field is locked and visibly says why.
                // The four drivers behind them get .live binding so a
                // clinician sees the derived value update as they type,
                // instead of only after Save.
                $derivedCdeCodes = ['BMI_CALCULATED', 'EGFR_CALCULATED'];
                $derivedInputCdeCodes = ['BODY_WEIGHT', 'BODY_HEIGHT', 'CREATININE_SERUM', 'AGE_YEARS'];
            @endphp
            @foreach ($cdes as $cde)
                @php
                    $isDerived = in_array($cde->cde_code, $derivedCdeCodes, true);
                    $feedsADerivedField = in_array($cde->cde_code, $derivedInputCdeCodes, true);
                @endphp
                <div wire:key="cde-{{ $cde->cde_code }}">
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">
                        {{ $cde->cde_name }}
                        @if ($isDerived)
                            <span class="text-[10px] text-gray-400 dark:text-gray-500 font-normal">(calculated)</span>
                        @endif
                    </label>
                    <div class="flex gap-1">
                        @if ($isDerived)
                            <input type="text" readonly tabindex="-1" wire:model="values.{{ $cde->cde_code }}"
                                placeholder="Awaiting inputs&hellip;"
                                class="flex-1 text-sm rounded border-gray-200 bg-gray-50 text-gray-600 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-400 cursor-not-allowed" />
                        @elseif ($feedsADerivedField)
                            <input type="number" step="any" wire:model.live.debounce.500ms="values.{{ $cde->cde_code }}"
                                class="flex-1 text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                        @else
                            <input type="number" step="any" wire:model="values.{{ $cde->cde_code }}"
                                class="flex-1 text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                        @endif
                        @if ($unitOptionsByCde[$cde->cde_code]->count() > 1)
                            <select wire:model="inputUnits.{{ $cde->cde_code }}"
                                class="text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                                @foreach ($unitOptionsByCde[$cde->cde_code] as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->unit_label }}</option>
                                @endforeach
                            </select>
                        @else
                            <span class="self-center text-xs text-gray-500 dark:text-gray-400 w-14">{{ $unitOptionsByCde[$cde->cde_code]->first()?->unit_label }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <button wire:click="save" class="mt-4 text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">
            Save Observations
        </button>
    </div>

    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Flowsheet</h4>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
            v6.1 Phase 7 — a captured value can be corrected, marked entered-in-error, or cancelled. The original
            row is always preserved (marked amended); a correction never rewrites it in place. Each action is
            terminal — a row already actioned once cannot be actioned again.
        </p>

        @if ($correctionError)
            <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 text-sm rounded p-3">{{ $correctionError }}</div>
        @endif
        @if ($correctionMessage)
            <div class="mb-4 text-xs rounded p-2 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300">{{ $correctionMessage }}</div>
        @endif

        @if ($recentObservations->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No observations recorded yet.</p>
        @else
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-500 dark:text-gray-400 uppercase">
                        <th class="pb-2">CDE</th>
                        <th class="pb-2">Value</th>
                        <th class="pb-2">Base Value</th>
                        <th class="pb-2">Captured At</th>
                        <th class="pb-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentObservations as $observation)
                        <tr wire:key="obs-{{ $observation->id }}" class="border-t border-gray-100 dark:border-gray-700">
                            <td class="py-1.5 text-gray-900 dark:text-gray-100">{{ $observation->cde_code }}</td>
                            <td class="py-1.5 text-gray-700 dark:text-gray-300">{{ $observation->captured_value_numeric ?? '—' }}</td>
                            <td class="py-1.5 text-gray-700 dark:text-gray-300">{{ $observation->base_value_numeric ?? '—' }}</td>
                            <td class="py-1.5 text-gray-500 dark:text-gray-400">{{ $observation->captured_at }}</td>
                            <td class="py-1.5 text-right space-x-2">
                                @if ($correctingObservationId === $observation->id)
                                    {{-- inline form rendered below the table --}}
                                @else
                                    <button wire:click="beginCorrection('{{ $observation->id }}', 'correct')" class="text-[11px] text-blue-700 hover:underline">Correct</button>
                                    <button wire:click="beginCorrection('{{ $observation->id }}', 'entered-in-error')" class="text-[11px] text-amber-700 hover:underline">Entered-in-error</button>
                                    <button wire:click="beginCorrection('{{ $observation->id }}', 'cancel')" class="text-[11px] text-red-700 hover:underline">Cancel</button>
                                @endif
                            </td>
                        </tr>
                        @if ($correctingObservationId === $observation->id)
                            <tr class="bg-gray-50 dark:bg-gray-900/40">
                                <td colspan="5" class="py-3 px-2">
                                    <div class="flex flex-wrap items-end gap-2">
                                        @if ($correctionAction === 'correct')
                                            <div>
                                                <label class="block text-[10px] font-medium text-gray-500 mb-1">Corrected value</label>
                                                <input type="number" step="any" wire:model="correctionValue" class="w-28 text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                                            </div>
                                        @endif
                                        <div class="flex-1 min-w-[10rem]">
                                            <label class="block text-[10px] font-medium text-gray-500 mb-1">Reason</label>
                                            <input type="text" wire:model="correctionReason" class="w-full text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                                            @error('correctionReason') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
                                        </div>
                                        <button wire:click="submitCorrection" class="text-xs text-white bg-blue-600 hover:bg-blue-700 rounded px-3 py-1.5">
                                            Confirm {{ str_replace('-', ' ', $correctionAction) }}
                                        </button>
                                        <button wire:click="cancelCorrection" class="text-xs text-gray-500 hover:underline">Dismiss</button>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
