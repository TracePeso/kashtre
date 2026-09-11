@php
    use App\Models\ImagingStudy;
    use App\Models\ImagingReadinessCheckType;
    use App\Models\ImagingCriticalFindingType;
    use App\Models\ContrastVial;

    $availableContrastVials = ContrastVial::query()
        ->forBusiness($study->business_id)
        ->available()
        ->orderBy('agent_name')
        ->get();

    $criticalFindingTypes = ImagingCriticalFindingType::query()
        ->active()
        ->availableToBusiness($study->business_id)
        ->orderBy('label')
        ->get(['code', 'label']);

    $checklistLabel = function (string $code) use ($study) {
        $type = ImagingReadinessCheckType::query()
            ->where('code', $code)
            ->availableToBusiness($study->business_id)
            ->first();

        return $type?->label ?? ucfirst(str_replace('_', ' ', $code));
    };

    $statusLabel = match ($study->status) {
        ImagingStudy::STATUS_ORDER_RECEIVED => ['Order Received', 'bg-gray-100 text-gray-800'],
        ImagingStudy::STATUS_PREPARATION_REQUIRED => ['Preparation Required', 'bg-gray-100 text-gray-800'],
        ImagingStudy::STATUS_PREPARATION_COMPLETE => ['Preparation Complete', 'bg-amber-100 text-amber-800'],
        ImagingStudy::STATUS_READY_FOR_STUDY => ['Ready For Study', 'bg-amber-100 text-amber-800'],
        ImagingStudy::STATUS_IN_PROGRESS => ['In Progress', 'bg-blue-100 text-blue-800'],
        ImagingStudy::STATUS_IMAGE_ACQUIRED => ['Image Acquired', 'bg-indigo-100 text-indigo-800'],
        ImagingStudy::STATUS_REPORT_PENDING => ['Report Pending', 'bg-indigo-100 text-indigo-800'],
        ImagingStudy::STATUS_REPORTED => ['Reported', 'bg-green-100 text-green-800'],
        ImagingStudy::STATUS_VERIFIED => ['Verified', 'bg-green-100 text-green-800'],
        default => [ucfirst($study->status), 'bg-gray-100 text-gray-800'],
    };

    $protocol = $study->protocol();
    $client = $study->resolveClient();
    $room = $study->resolveRoom();
    $checkedItems = array_keys(array_filter($study->readiness_check_results ?? []));

    $report = $study->reports()->latest()->first();
    $reportSections = $protocol->reporting_template['sections'] ?? [];
    $showReportCard = in_array($study->status, [
        ImagingStudy::STATUS_REPORT_PENDING,
        ImagingStudy::STATUS_REPORTED,
        ImagingStudy::STATUS_VERIFIED,
    ]) || $report;

    $contrastAdministrations = $study->contrastAdministrations()->latest()->get();
    $consumptions = $study->consumptions()->latest()->get();
    $radiationLogs = $study->radiationExposureLogs()->latest()->get();
    $lifetimeRadiationLogs = \App\Models\RadiationExposureLog::forClient($study->client_id)
        ->whereHas('imagingStudy', fn ($q) => $q->where('business_id', $study->business_id))
        ->with('imagingStudy')
        ->latest()
        ->get();
    $priorStudies = $study->priorStudies();

    $priorityLabel = match ($study->priority) {
        ImagingStudy::PRIORITY_URGENT => ['Urgent', 'bg-red-100 text-red-800'],
        ImagingStudy::PRIORITY_HIGH => ['High', 'bg-amber-100 text-amber-800'],
        ImagingStudy::PRIORITY_LOW => ['Low', 'bg-gray-100 text-gray-800'],
        default => ['Normal', 'bg-gray-100 text-gray-800'],
    };
@endphp
<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <a href="{{ route('imaging-studies.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to worklist</a>
                <h2 class="mt-2 text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">{{ $study->accession_number }}</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $protocol->name ?? $study->protocol_code }} ({{ $study->modality_type }})
                    @if($client)
                        · {{ $client->full_name }} ({{ $study->client_id }})
                    @endif
                    @if($room)
                        · {{ $room->name }}
                    @endif
                </p>
            </div>
            <div class="mt-4 md:mt-0 flex items-center gap-2">
                @if($study->priority !== \App\Models\ImagingStudy::PRIORITY_NORMAL)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $priorityLabel[1] }}">{{ $priorityLabel[0] }}</span>
                @endif
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $statusLabel[1] }}">{{ $statusLabel[0] }}</span>
            </div>
        </div>

        {{-- Pillars 7/8 (stub) & 19: PACS actions, audited regardless of the stub --}}
        @if(!in_array($study->status, [\App\Models\ImagingStudy::STATUS_ORDER_RECEIVED, \App\Models\ImagingStudy::STATUS_PREPARATION_REQUIRED, \App\Models\ImagingStudy::STATUS_PREPARATION_COMPLETE, \App\Models\ImagingStudy::STATUS_READY_FOR_STUDY, \App\Models\ImagingStudy::STATUS_IN_PROGRESS]))
            <div class="mt-3 flex gap-2">
                <form action="{{ route('imaging-studies.open-images', $study) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 border border-gray-300 text-gray-700 text-xs font-medium rounded-md hover:bg-gray-50">
                        Open Images
                    </button>
                </form>
                <form action="{{ route('imaging-studies.export-images', $study) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 border border-gray-300 text-gray-700 text-xs font-medium rounded-md hover:bg-gray-50">
                        Export Images
                    </button>
                </form>
            </div>
        @endif

        @if (session('success'))
            <div class="mt-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">{{ session('error') }}</div>
        @endif

        <div class="mt-6 space-y-6">

            {{-- Pillar 3: Preparation & Readiness Verification --}}
            @if(count($study->preparationChecklistItems()) || count($study->readinessChecklistItems()))
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Preparation &amp; Readiness Checklist</h3>
                    <form action="{{ route('imaging-studies.checklist', $study) }}" method="POST" class="space-y-4">
                        @csrf
                        @if(count($study->preparationChecklistItems()))
                            <div>
                                <h4 class="text-sm font-semibold text-gray-700 mb-2">Preparation Requirements</h4>
                                <div class="space-y-2">
                                    @foreach($study->preparationChecklistItems() as $item)
                                        <label class="flex items-center gap-2 text-sm text-gray-700">
                                            <input type="checkbox" name="checked[]" value="{{ $item }}"
                                                @checked(in_array($item, $checkedItems))
                                                class="rounded border-gray-300">
                                            {{ $checklistLabel($item) }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        @if(count($study->readinessChecklistItems()))
                            <div>
                                <h4 class="text-sm font-semibold text-gray-700 mb-2">Readiness Checks</h4>
                                <div class="space-y-2">
                                    @foreach($study->readinessChecklistItems() as $item)
                                        <label class="flex items-center gap-2 text-sm text-gray-700">
                                            <input type="checkbox" name="checked[]" value="{{ $item }}"
                                                @checked(in_array($item, $checkedItems))
                                                class="rounded border-gray-300">
                                            {{ $checklistLabel($item) }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        {{-- Preparation-only items post here too (same map) so unchecked ones save as false --}}
                        <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-md hover:bg-gray-900">
                            Save Checklist
                        </button>
                    </form>
                </div>
            @endif

            {{-- Pillar 11: Consent Management --}}
            @if($protocol?->requires_consent)
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Consent</h3>
                    @if($study->consent_verified)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Verified</span>
                        @php $consentVerifier = $study->resolveConsentVerifiedBy(); @endphp
                        <p class="mt-2 text-xs text-gray-500">
                            Verified by {{ $consentVerifier->name ?? 'Unknown' }}
                            on {{ $study->consent_verified_at?->format('M d, Y H:i') }}.
                        </p>
                        @if($study->consent_notes)
                            <p class="mt-2 text-sm text-gray-600"><strong>Notes:</strong> {{ $study->consent_notes }}</p>
                        @endif
                    @else
                        <p class="text-sm text-gray-500 mb-3">This protocol requires signed consent before the study can proceed.</p>
                        <form action="{{ route('imaging-studies.consent', $study) }}" method="POST" class="space-y-3 max-w-md"
                              onsubmit="return confirm('Confirm that signed consent has been obtained?');">
                            @csrf
                            <textarea name="consent_notes" rows="2" placeholder="Optional notes (form used, witness, etc.)"
                                      class="block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                            <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-md hover:bg-gray-900">
                                Verify Consent
                            </button>
                        </form>
                    @endif
                </div>
            @endif

            {{-- Pillar 12: Contrast Management --}}
            @if($protocol?->is_contrast_enhanced)
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Contrast Administration</h3>

                    @if($contrastAdministrations->isNotEmpty())
                        <ul class="space-y-3 mb-4">
                            @foreach($contrastAdministrations as $admin)
                                <li class="border border-gray-200 rounded-md p-3 text-sm">
                                    <div class="flex items-center justify-between">
                                        <span class="font-medium text-gray-900">{{ $admin->contrast_agent_name }}</span>
                                        <span class="text-gray-500">{{ $admin->volume_ml }} mL</span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">
                                        Administered by {{ $admin->resolveAdministeredBy()?->name ?? 'Unknown' }}
                                        @if($admin->injection_time)
                                            at {{ $admin->injection_time->format('M d, Y H:i') }}
                                        @endif
                                    </p>
                                    @if($admin->adverse_reactions)
                                        <p class="text-xs text-red-700 mt-1"><strong>Adverse reactions:</strong> {{ $admin->adverse_reactions }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if(in_array('Progress Imaging Studies', $permissions))
                        <details @if($contrastAdministrations->isEmpty()) open @endif>
                            <summary class="text-sm text-blue-600 cursor-pointer">Record contrast administration</summary>
                            <form action="{{ route('imaging-studies.contrast.store', $study) }}" method="POST" class="mt-3 space-y-3 max-w-md"
                                  x-data="{
                                      vials: {{ Illuminate\Support\Js::from($availableContrastVials->map(fn ($v) => ['id' => $v->id, 'agent_name' => $v->agent_name, 'remaining' => (float) $v->remaining_volume_ml])) }},
                                      selectedVial: '',
                                      applyVial() {
                                          const v = this.vials.find(v => v.id == this.selectedVial);
                                          if (v) {
                                              this.$refs.agentName.value = v.agent_name;
                                              this.$refs.volumeMl.value = v.remaining;
                                          }
                                      },
                                  }">
                                @csrf
                                @if($availableContrastVials->isNotEmpty())
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Contrast Vial (optional)</label>
                                        <select name="contrast_vial_id" x-model="selectedVial" @change="applyVial()"
                                                class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                            <option value="">Free text (no vial tracking)</option>
                                            @foreach($availableContrastVials as $vial)
                                                <option value="{{ $vial->id }}">{{ $vial->agent_name }} — {{ $vial->remaining_volume_ml }} mL remaining @if($vial->lot_number)(Lot {{ $vial->lot_number }})@endif</option>
                                            @endforeach
                                        </select>
                                        <p class="mt-1 text-xs text-gray-500">Picking a vial prefills the fields below and deducts from its remaining volume — still editable.</p>
                                    </div>
                                @endif
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Contrast Agent Name</label>
                                    <input type="text" name="contrast_agent_name" required x-ref="agentName"
                                           value="{{ old('contrast_agent_name', $protocol?->resolveDefaultContrastItem()?->name) }}"
                                           class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Volume (mL)</label>
                                    <input type="number" step="0.01" min="0.01" name="volume_ml" required x-ref="volumeMl"
                                           value="{{ old('volume_ml', $protocol?->default_contrast_volume_ml) }}"
                                           class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Injection Time</label>
                                    <input type="datetime-local" name="injection_time"
                                           class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Adverse Reactions</label>
                                    <textarea name="adverse_reactions" rows="2" placeholder="None, unless noted here"
                                              class="block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                                </div>
                                <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-md hover:bg-gray-900">
                                    Save
                                </button>
                            </form>
                        </details>
                    @endif
                </div>
            @endif

            {{-- Pillar 13: Radiation Dose Tracking --}}
            @if($study->isIonizingModality())
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Radiation Exposure</h3>

                    @if($radiationLogs->isNotEmpty())
                        <ul class="space-y-2 mb-4">
                            @foreach($radiationLogs as $log)
                                <li class="text-sm border-b border-gray-100 pb-2 last:border-0">
                                    <span class="font-medium text-gray-900">{{ $log->dose_area_product_gy ?? '—' }} Gy·cm²</span>
                                    <span class="text-gray-500">
                                        @if($log->exposure_time_ms) · {{ $log->exposure_time_ms }} ms @endif
                                        @if($log->kvp_metrics) · {{ $log->kvp_metrics }} kVp @endif
                                        · {{ $log->created_at->format('M d, Y H:i') }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if(in_array('Progress Imaging Studies', $permissions))
                        <details @if($radiationLogs->isEmpty()) open @endif>
                            <summary class="text-sm text-blue-600 cursor-pointer">Log radiation exposure</summary>
                            <form action="{{ route('imaging-studies.radiation.store', $study) }}" method="POST" class="mt-3 space-y-3 max-w-md">
                                @csrf
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Dose Area Product (Gy·cm²)</label>
                                    <input type="number" step="0.0001" min="0" name="dose_area_product_gy"
                                           class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Exposure Time (ms)</label>
                                    <input type="number" min="0" name="exposure_time_ms"
                                           class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">kVp Metrics</label>
                                    <input type="text" name="kvp_metrics" placeholder="e.g. 120 kVp"
                                           value="{{ old('kvp_metrics', $protocol?->default_kvp_metrics) }}"
                                           class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                </div>
                                <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-md hover:bg-gray-900">
                                    Save
                                </button>
                            </form>
                        </details>
                    @endif

                    @if($lifetimeRadiationLogs->count() > 1)
                        <details class="mt-4">
                            <summary class="text-sm text-gray-600 cursor-pointer">Lifetime exposure history ({{ $lifetimeRadiationLogs->count() }} studies)</summary>
                            <ul class="mt-3 space-y-2">
                                @foreach($lifetimeRadiationLogs as $log)
                                    <li class="text-xs text-gray-500 border-b border-gray-100 pb-2 last:border-0">
                                        <a href="{{ route('imaging-studies.show', $log->imagingStudy) }}" class="text-blue-600 hover:text-blue-800">
                                            {{ $log->imagingStudy?->accession_number }}
                                        </a>
                                        — {{ $log->dose_area_product_gy ?? '—' }} Gy·cm² on {{ $log->created_at->format('M d, Y') }}
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </div>
            @endif

            {{-- Pillar 16: Recovery & Post-Procedure Tracking --}}
            @if($study->requiresRecovery())
                @php $recovery = $study->recoveryRecord; @endphp
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Recovery &amp; Discharge</h3>

                    @if($recovery?->isDischarged())
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Discharged</span>
                        <p class="mt-2 text-xs text-gray-500">
                            Cleared by {{ $recovery->resolveDischargedBy()?->name ?? 'Unknown' }}
                            on {{ $recovery->discharge_cleared_at->format('M d, Y H:i') }}.
                        </p>
                        @if($recovery->discharge_notes)
                            <p class="mt-2 text-sm text-gray-600"><strong>Notes:</strong> {{ $recovery->discharge_notes }}</p>
                        @endif
                    @else
                        @if(in_array('Progress Imaging Studies', $permissions))
                            <form action="{{ route('imaging-studies.recovery.update', $study) }}" method="POST" class="space-y-3 max-w-md">
                                @csrf
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Vital Signs / Monitoring Notes</label>
                                    <textarea name="vital_signs_notes" rows="3"
                                              class="block w-full rounded-md border-gray-300 shadow-sm text-sm">{{ $recovery->vital_signs_notes ?? '' }}</textarea>
                                </div>
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="discharge_criteria_met" value="1"
                                        @checked($recovery?->discharge_criteria_met) class="rounded border-gray-300">
                                    Discharge criteria met (stable vitals, alert, ambulatory)
                                </label>
                                <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-md hover:bg-gray-900">
                                    Save Monitoring
                                </button>
                            </form>

                            @if($recovery?->discharge_criteria_met)
                                <form action="{{ route('imaging-studies.recovery.discharge', $study) }}" method="POST" class="mt-4 space-y-3 max-w-md"
                                      onsubmit="return confirm('Clear this patient for discharge?');">
                                    @csrf
                                    <textarea name="discharge_notes" rows="2" placeholder="Optional discharge notes"
                                              class="block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                                    <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700">
                                        Clear For Discharge
                                    </button>
                                </form>
                            @else
                                <p class="mt-3 text-xs text-gray-500">Confirm discharge criteria are met above before clearing for discharge.</p>
                            @endif
                        @endif
                    @endif
                </div>
            @endif

            {{-- Next action --}}
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Next Step</h3>

                @if($study->isStatus(ImagingStudy::STATUS_ORDER_RECEIVED))
                    @if($protocol?->requires_preparation ?? true)
                        <form action="{{ route('imaging-studies.start-preparation', $study) }}" method="POST">
                            @csrf
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                                Start Preparation
                            </button>
                        </form>
                    @else
                        {{-- This protocol is configured to skip the Preparation phase
                             (Settings > Imaging Module Settings > Manage Imaging Protocols
                             > Status Flow) — go straight to Ready For Study. --}}
                        @if($study->canMarkReadyForStudy())
                            <form action="{{ route('imaging-studies.ready-for-study', $study) }}" method="POST">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                                    Mark Ready For Study
                                </button>
                            </form>
                        @else
                            <button type="button" disabled class="px-4 py-2 bg-gray-300 text-gray-500 text-sm font-medium rounded-md cursor-not-allowed">
                                Mark Ready For Study
                            </button>
                            <p class="mt-2 text-xs text-gray-500">All readiness checks{{ $protocol?->requires_consent ? ' and consent' : '' }} must be satisfied first.</p>
                        @endif
                    @endif

                @elseif($study->isStatus(ImagingStudy::STATUS_PREPARATION_REQUIRED))
                    @if($study->canMarkPreparationComplete())
                        <form action="{{ route('imaging-studies.complete-preparation', $study) }}" method="POST">
                            @csrf
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                                Mark Preparation Complete
                            </button>
                        </form>
                    @else
                        <button type="button" disabled class="px-4 py-2 bg-gray-300 text-gray-500 text-sm font-medium rounded-md cursor-not-allowed">
                            Mark Preparation Complete
                        </button>
                        <p class="mt-2 text-xs text-gray-500">All preparation requirements must be checked off first.</p>
                    @endif

                @elseif($study->isStatus(ImagingStudy::STATUS_PREPARATION_COMPLETE))
                    @if($study->canMarkReadyForStudy())
                        <form action="{{ route('imaging-studies.ready-for-study', $study) }}" method="POST">
                            @csrf
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                                Mark Ready For Study
                            </button>
                        </form>
                    @else
                        <button type="button" disabled class="px-4 py-2 bg-gray-300 text-gray-500 text-sm font-medium rounded-md cursor-not-allowed">
                            Mark Ready For Study
                        </button>
                        <p class="mt-2 text-xs text-gray-500">All readiness checks{{ $protocol?->requires_consent ? ' and consent' : '' }} must be satisfied first.</p>
                    @endif

                @elseif($study->isStatus(ImagingStudy::STATUS_READY_FOR_STUDY))
                    <form action="{{ route('imaging-studies.start', $study) }}" method="POST">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                            Start Study
                        </button>
                    </form>

                @elseif($study->isStatus(ImagingStudy::STATUS_IN_PROGRESS))
                    <form action="{{ route('imaging-studies.image-acquired', $study) }}" method="POST">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                            Mark Image Acquired
                        </button>
                    </form>

                @elseif($study->isStatus(ImagingStudy::STATUS_IMAGE_ACQUIRED))
                    @if($study->requiresRecovery() && ! $study->isDischargeCleared())
                        <button type="button" disabled class="px-4 py-2 bg-gray-300 text-gray-500 text-sm font-medium rounded-md cursor-not-allowed">
                            Send To Radiologist Queue
                        </button>
                        <p class="mt-2 text-xs text-gray-500">Recovery monitoring and discharge clearance must be completed first.</p>
                    @else
                        <form action="{{ route('imaging-studies.report-pending', $study) }}" method="POST">
                            @csrf
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                                Send To Radiologist Queue
                            </button>
                        </form>
                    @endif

                @else
                    <p class="text-sm text-gray-500">
                        This study is in the reporting workflow ({{ $statusLabel[0] }}). No worklist action here.
                    </p>
                @endif
            </div>

            {{-- Pillar 15: Prior Study Comparison --}}
            @if($priorStudies->isNotEmpty())
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Prior Studies ({{ $protocol->name ?? $study->protocol_code }})</h3>
                    <ul class="space-y-2">
                        @foreach($priorStudies as $prior)
                            <li class="flex items-center justify-between text-sm border-b border-gray-100 pb-2 last:border-0">
                                <a href="{{ route('imaging-studies.show', $prior) }}" class="text-blue-600 hover:text-blue-800 font-medium">
                                    {{ $prior->accession_number }}
                                </a>
                                <span class="text-gray-500">
                                    {{ $prior->created_at->format('M d, Y') }} · {{ ucfirst(strtolower(str_replace('_', ' ', $prior->status))) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Pillars 4 & 5: Structured Reporting + Reporting Lifecycle --}}
            @if($showReportCard)
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Diagnostic Report</h3>
                        @if($report)
                            @php
                                $reportStatusLabel = match ($report->status) {
                                    \App\Models\ImagingReport::STATUS_DRAFT => ['Draft', 'bg-gray-100 text-gray-800'],
                                    \App\Models\ImagingReport::STATUS_REPORTED => ['Awaiting Verification', 'bg-amber-100 text-amber-800'],
                                    \App\Models\ImagingReport::STATUS_VERIFIED => ['Verified', 'bg-green-100 text-green-800'],
                                    \App\Models\ImagingReport::STATUS_AMENDED => ['Amended', 'bg-orange-100 text-orange-800'],
                                    default => [ucfirst($report->status), 'bg-gray-100 text-gray-800'],
                                };
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $reportStatusLabel[1] }}">{{ $reportStatusLabel[0] }}</span>
                        @endif
                    </div>

                    @if(! $report || $report->isStatus(\App\Models\ImagingReport::STATUS_DRAFT))
                        @if(in_array('Report Imaging Studies', $permissions))
                        {{-- Editable draft form --}}
                        <form action="{{ route('imaging-studies.report.draft', $study) }}" method="POST" class="space-y-4"
                              x-data="{ criticalFinding: {{ $report?->is_critical_finding ? 'true' : 'false' }} }">
                            @csrf
                            @forelse($reportSections as $section)
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ $section }}</label>
                                    <textarea name="sections[{{ $section }}]" rows="2"
                                              class="block w-full rounded-md border-gray-300 shadow-sm text-sm">{{ $report->structured_data_payload[$section] ?? '' }}</textarea>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">This protocol has no reporting template configured.</p>
                            @endforelse

                            <label class="flex items-center gap-2 text-sm text-red-700">
                                <input type="checkbox" name="is_critical_finding" value="1" x-model="criticalFinding"
                                    class="rounded border-gray-300">
                                Flag as critical finding
                            </label>

                            <div x-show="criticalFinding" x-cloak>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Critical Finding</label>
                                <select name="critical_finding_code" class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="">Select a condition</option>
                                    @foreach($criticalFindingTypes as $type)
                                        <option value="{{ $type->code }}" @selected($report?->critical_finding_code === $type->code)>{{ $type->label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="flex gap-3">
                                <button type="submit" formaction="{{ route('imaging-studies.report.draft', $study) }}"
                                        class="px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-md hover:bg-gray-700">
                                    Save Draft
                                </button>
                                @if($report)
                                    <button type="submit" formaction="{{ route('imaging-studies.report.submit', $study) }}"
                                            onclick="return confirm('Submit this report for verification? It will no longer be editable as a draft.');"
                                            class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                                        Submit Report
                                    </button>
                                @endif
                            </div>
                        </form>
                        @else
                            <p class="text-sm text-gray-500">You don't have permission to write imaging reports.</p>
                        @endif

                    @else
                        {{-- Read-only rendered report --}}
                        <div class="space-y-3">
                            @if($report->is_critical_finding)
                                <div class="bg-red-50 border border-red-200 text-red-800 px-3 py-2 rounded text-sm font-semibold">
                                    ⚠ Critical Finding{{ $report->resolveCriticalFindingType() ? ': '.$report->resolveCriticalFindingType()->label : '' }}
                                </div>
                            @endif
                            @foreach($reportSections as $section)
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-700">{{ $section }}</h4>
                                    <p class="text-sm text-gray-600 whitespace-pre-line">{{ $report->structured_data_payload[$section] ?? '—' }}</p>
                                </div>
                            @endforeach
                        </div>

                        @if($report->isStatus(\App\Models\ImagingReport::STATUS_VERIFIED))
                            <p class="mt-4 text-xs text-gray-500">
                                Verified by {{ optional(\App\Models\User::find($report->verified_by_user_id))->name ?? 'Unknown' }}
                                on {{ $report->verified_at?->format('M d, Y H:i') }}.
                            </p>
                        @endif

                        @php
                            $reportAwaitingVerification = in_array($report->status, [\App\Models\ImagingReport::STATUS_REPORTED, \App\Models\ImagingReport::STATUS_AMENDED]);
                            $isReportAuthor = Auth::id() === $report->author_user_id;
                            $isEligibleVerifier = \App\Models\ImagingModuleConfig::isEligibleReviewer($study->business_id, Auth::id());
                        @endphp

                        @if($reportAwaitingVerification && $isReportAuthor)
                            <p class="mt-4 text-xs text-gray-500 italic">
                                Awaiting verification by another radiologist — you authored this report, so you can't verify it yourself.
                            </p>
                        @elseif($reportAwaitingVerification && in_array('Verify Imaging Reports', $permissions) && ! $isEligibleVerifier)
                            <p class="mt-4 text-xs text-gray-500 italic">
                                Awaiting verification — you aren't in this business's configured radiologist verification pool, so an eligible colleague needs to sign this off.
                            </p>
                        @elseif($reportAwaitingVerification && in_array('Verify Imaging Reports', $permissions) && $isEligibleVerifier)
                            <form action="{{ route('imaging-studies.report.verify', $study) }}" method="POST" class="mt-4"
                                  onsubmit="return confirm('Verify and digitally sign this report?');">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                                    Verify Report
                                </button>
                            </form>
                        @endif

                        @if($report->isStatus(\App\Models\ImagingReport::STATUS_VERIFIED) && in_array('Verify Imaging Reports', $permissions) && $isEligibleVerifier)
                            <details class="mt-4">
                                <summary class="text-sm text-blue-600 cursor-pointer">Amend this report</summary>
                                <form action="{{ route('imaging-studies.report.amend', $study) }}" method="POST" class="mt-3 space-y-4"
                                      onsubmit="return confirm('Record this amendment? The prior verified content stays preserved in the version history.');">
                                    @csrf
                                    @foreach($reportSections as $section)
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $section }}</label>
                                            <textarea name="sections[{{ $section }}]" rows="2"
                                                      class="block w-full rounded-md border-gray-300 shadow-sm text-sm">{{ $report->structured_data_payload[$section] ?? '' }}</textarea>
                                        </div>
                                    @endforeach
                                    <textarea name="justification" rows="2" required placeholder="Reason for amendment (required)"
                                              class="block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                                    <button type="submit" class="px-4 py-2 bg-orange-600 text-white text-sm font-medium rounded-md hover:bg-orange-700">
                                        Record Amendment
                                    </button>
                                </form>
                            </details>
                        @endif
                    @endif

                    @php $versions = $report?->versions()->latest()->get() ?? collect(); @endphp
                    @if($versions->isNotEmpty())
                        <details class="mt-6">
                            <summary class="text-sm text-gray-600 cursor-pointer">Version history ({{ $versions->count() }})</summary>
                            <ul class="mt-3 space-y-2">
                                @foreach($versions as $version)
                                    <li class="text-xs text-gray-500 border-b border-gray-100 pb-2">
                                        <span class="font-medium text-gray-700">{{ ucfirst(strtolower($version->status)) }}</span>
                                        by {{ optional(\App\Models\User::find($version->modifier_user_id))->name ?? ('User #'.$version->modifier_user_id) }}
                                        on {{ $version->created_at->format('M d, Y H:i') }}
                                        @if($version->amendment_justification_reason)
                                            — {{ $version->amendment_justification_reason }}
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </div>
            @endif

            {{-- Pillar 12.1: Material Consumable Tracking --}}
            @if($consumptions->isNotEmpty())
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Consumables Used</h3>
                    <ul class="space-y-2">
                        @foreach($consumptions as $consumption)
                            <li class="flex items-center justify-between text-sm border-b border-gray-100 pb-2 last:border-0">
                                <span class="text-gray-800">
                                    {{ $consumption->resolveItem()?->name ?? $consumption->inventory_sku }}
                                    <span class="text-gray-400">({{ $consumption->inventory_sku }})</span>
                                </span>
                                <span class="text-gray-500">{{ $consumption->quantity_used }} · {{ ucfirst(strtolower(str_replace('_', ' ', $consumption->consumption_type))) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @if($consumptions->contains(fn ($c) => ! $c->item_id))
                        <p class="mt-3 text-xs text-amber-600">
                            Some items have no matching Inventory item for this business (no stock was deducted) — logged locally only.
                        </p>
                    @endif
                </div>
            @endif

            {{-- Pillar 10: Imaging Procedure Record --}}
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Procedure Record</h3>
                @php $history = $study->status_history ?? []; @endphp
                @if(empty($history))
                    <p class="text-sm text-gray-500">No transitions recorded yet.</p>
                @else
                    <ul class="space-y-2">
                        @foreach(array_reverse($history) as $entry)
                            <li class="flex items-center justify-between text-sm border-b border-gray-100 pb-2 last:border-0">
                                <span class="font-medium text-gray-800">{{ ucfirst(str_replace('_', ' ', $entry['status'] ?? '')) }}</span>
                                <span class="text-gray-500">
                                    {{ \Illuminate\Support\Carbon::parse($entry['at'])->format('M d, Y H:i') }}
                                    @if(!empty($entry['user_id']))
                                        · {{ optional(\App\Models\User::find($entry['user_id']))->name ?? ('User #'.$entry['user_id']) }}
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

        </div>
    </div>
</div>
</x-app-layout>
