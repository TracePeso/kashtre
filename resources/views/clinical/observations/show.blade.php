<x-app-layout>
    @if ($ztnaOffPremises ?? false)
        {{-- Chunk 9 ZTNA: visible deterrent overlay for off-premises access,
             on top of the X-KashTre-Watermark-* response headers. --}}
        <div class="fixed inset-0 pointer-events-none z-50 flex items-center justify-center overflow-hidden">
            <div class="text-gray-400/20 dark:text-gray-100/10 text-3xl font-bold -rotate-45 whitespace-nowrap select-none">
                {{ auth()->user()->name }} (ID: {{ auth()->id() }}) &middot; {{ request()->ip() }} &middot; {{ now()->toIso8601String() }}
            </div>
        </div>
        <div class="bg-amber-50 dark:bg-amber-900/30 border-b border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-300 text-xs text-center py-1">
            Off-premises access — this session is watermarked. Live order placement and MAR administration are disabled.
        </div>
    @endif

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            {{-- Patient identity banner — every panel below only ever deals in
                 raw clientId/visitId strings, so without this the page never
                 actually said whose chart is on screen. --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ $client?->name ?: $client?->full_name ?: 'Unknown Patient' }}
                    </div>
                    <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                        <span>Patient <span class="font-medium text-gray-700 dark:text-gray-300">{{ $clientId }}</span></span>
                        @if ($visitId)
                            <span>Visit <span class="font-medium text-gray-700 dark:text-gray-300">{{ $visitId }}</span></span>
                        @endif
                        @if ($client?->age !== null)
                            <span>{{ $client->age }}y</span>
                        @endif
                        @if ($client?->sex)
                            <span>{{ $client->sex }}</span>
                        @endif
                    </div>
                </div>
                <a href="{{ route('clinical.fhir-export', ['clientId' => $clientId]) }}" target="_blank"
                    class="text-xs text-gray-500 dark:text-gray-400 hover:underline">Export FHIR Bundle</a>
            </div>

            @livewire('clinical.entitlement-balances-panel', ['clientId' => $clientId])
            @livewire('clinical.bedside-scratchpad', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.care-team-panel', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.encounter-sections-panel', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.work-orders-panel', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.maternity-panel', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.diagnoses-panel', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.capture-observations', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.triage-assessment', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.clinical-process-panel', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.care-transitions-panel', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.handover-records-panel', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.medication-orders-panel', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.medication-reconciliation-panel', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.medication-adverse-events-panel', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.place-diagnostic-order', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.place-lab-order', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.diagnostic-report-corrections-panel', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.crash-cart-reconciliation-panel', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.record-consumption', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.patient-messages-panel', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.break-glass-episode-review', ['episodeId' => session('break_glass_episode_id')])
            @livewire('clinical.audit-trail', ['clientId' => $clientId])

            {{-- SRD v6.1 Phase 2 — patient identity, encounters, sensitivity & longitudinal workspace. --}}
            @livewire('clinical.patient-workspace-panel', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.encounter-workspace-panel', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.identity-confirmations-panel', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.identity-concerns-panel', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.sensitivity-restrictions-panel', ['clientId' => $clientId, 'visitId' => $visitId])
        </div>
    </div>
</x-app-layout>
