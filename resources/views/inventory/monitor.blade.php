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
            <strong>Physical usable stock</strong> = physical (or system if not counted) minus damaged and expired.
            <strong>Verifiable shrinkage</strong> = damaged + expired (on shelf but unusable).
            <strong>Unverified loss</strong> = system − physical (missing from shelf).
            <strong>Total shrinkage %</strong> shown to 4 decimal places.
            <strong>Fixed daily avg</strong> is set under Manage Inventory module settings (Excel AA).
            Moving averages (15 / 30 / 90 / 180 / 360 days) come from automatically recorded consumption.
        </p>
    </div>
</div>
</x-app-layout>
