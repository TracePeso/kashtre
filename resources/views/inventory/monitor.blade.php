<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">Monitor Stock</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Switch between <strong>local store</strong> stock (one store, full Excel metrics) and
                    <strong>network rollup</strong> (parent store + all child stores).
                </p>
            </div>
        </div>

        @include('inventory.partials.subnav')

        <div class="mt-6 bg-white shadow sm:rounded-lg p-6">
            @livewire('inventory.inventory-stock-monitor')
        </div>

        <p class="mt-4 text-xs text-gray-500">
            <strong>Local store</strong> shows <strong>System stock (AR)</strong> (ledger since financial year start) and
            <strong>Physical stock</strong> (live quantity at the store). Shrinkage compares the two.
            <strong>Network rollup</strong> sums physical stock across the store hierarchy.
            Open <strong>History</strong> on an item row in local view for price movements.
        </p>
    </div>
</div>
</x-app-layout>
