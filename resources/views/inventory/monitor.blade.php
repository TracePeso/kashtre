<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">Monitor Stock</h2>
                <p class="mt-1 text-sm text-gray-500">System vs physical stock by store. Filter by store or open <strong>History</strong> for price movements.</p>
            </div>
        </div>

        @include('inventory.partials.subnav')

        <div class="mt-6 bg-white shadow sm:rounded-lg p-6">
            @livewire('inventory.monitor-stock-table')
        </div>

        <p class="mt-4 text-xs text-gray-500">
            <strong>System stock</strong> follows GRN receipts and consumption.
            <strong>Physical stock</strong> comes from stock counts;
            <strong>Usable</strong> = physical (or system if not counted) minus damaged.
            <strong>Shrinkage</strong> compares system vs physical when a count exists.
            Moving averages feed order form suggestions.
        </p>
    </div>
</div>
</x-app-layout>
