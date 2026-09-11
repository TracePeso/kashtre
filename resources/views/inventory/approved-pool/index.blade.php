<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('inventory.partials.subnav')

        <div class="mt-4">
            <h1 class="text-xl font-semibold text-gray-900 tracking-tight">Approved Pool</h1>
            <p class="mt-1 text-sm text-gray-500">
                Prepaid goods dispensed from an End Store and available for the client (not physical floor stock).
            </p>
        </div>

        <div class="mt-4 bg-white border border-gray-200 shadow-sm sm:rounded-lg p-6">
            @livewire('inventory.approved-pool-table')
        </div>
    </div>
</div>
</x-app-layout>
