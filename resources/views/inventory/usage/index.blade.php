<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('inventory.partials.subnav')

        <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 tracking-tight">Record usage</h1>
                <p class="mt-1 text-sm text-gray-500">Log patient, floor, or crash cart use. Pool, stock, and billing update automatically.</p>
            </div>
            @if(\App\Support\InventoryBusinessContext::crashCartEnabled() && \App\Support\InventoryBusinessContext::floorStockEnabled())
                <a href="{{ route('inventory.crash-carts.index') }}"
                   class="text-sm font-medium text-red-700 hover:text-red-800">
                    Crash carts →
                </a>
            @endif
        </div>

        <div class="mt-4 bg-white border border-gray-200 shadow-sm sm:rounded-lg p-6">
            @livewire('inventory.record-usage-table')
        </div>
    </div>
</div>
</x-app-layout>
