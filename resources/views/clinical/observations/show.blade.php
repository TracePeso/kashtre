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
            <div class="flex justify-end">
                <a href="{{ route('clinical.fhir-export', ['clientId' => $clientId]) }}" target="_blank"
                    class="text-xs text-gray-500 dark:text-gray-400 hover:underline">Export FHIR Bundle</a>
            </div>

            @livewire('clinical.bedside-scratchpad', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.diagnoses-panel', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.capture-observations', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.clinical-process-panel', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.medication-orders-panel', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.place-diagnostic-order', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.place-lab-order', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.record-consumption', ['clientId' => $clientId, 'visitId' => $visitId])
            @livewire('clinical.audit-trail', ['clientId' => $clientId])
        </div>
    </div>
</x-app-layout>
