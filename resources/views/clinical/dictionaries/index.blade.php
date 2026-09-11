<x-app-layout>
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <p class="text-sm text-gray-500 dark:text-gray-400">Master settings</p>
                <h2 class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">Clinical Module Settings</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    The Clinical Module's configuration dictionaries. These are its system of record —
                    edited here, stored there.
                </p>
            </div>

            {{-- Same strip as the connection page, so the two read as one area. --}}
            <div class="mb-6 flex flex-wrap gap-2 border-b border-gray-200 dark:border-gray-700 pb-4">
                @if(auth()->user()->business_id == 1 || in_array('View Clinical Module', (array) auth()->user()->permissions))
                    <a href="{{ route('settings.clinical-module.edit') }}"
                       class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-gray-600 dark:text-gray-300 border border-transparent hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-gray-100">
                        Connection
                    </a>
                @endif
                <span class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-700">
                    Clinical Dictionaries
                </span>
            </div>

            <div class="mb-6">
                @livewire('clinical.clinical-module-health')
            </div>

            @livewire('clinical.facility-provisioning')

            @livewire('clinical.clinical-dictionaries', ['dictionary' => $dictionary])

            {{-- v6.1 EDD volumes with facility-wide (not per-patient) scope. --}}
            <div class="mt-6 space-y-6">
                @livewire('clinical.ai-use-cases-panel')
                @livewire('clinical.content-governance-panel')
                @livewire('clinical.interoperability-panel')
            </div>

            {{-- SRD v6.1 Phase 1 — governance primitives (staff-level, not per-patient). --}}
            <div class="mt-6 space-y-6">
                @livewire('clinical.client-space-assignments-panel')
                @livewire('clinical.privileges-panel')
                @livewire('clinical.delegations-panel')
                @livewire('clinical.permission-catalog-panel')
            </div>
        </div>
    </div>
</x-app-layout>
