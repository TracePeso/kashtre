<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">Monitor Stock</h2>
                <p class="mt-1 text-sm text-gray-500">Stock on hand by <strong>receiving store</strong> after GRNs are approved. Filter by store or open <strong>History</strong> for price movements.</p>
            </div>
        </div>

        @include('inventory.partials.subnav')

        <div class="mt-6 bg-white shadow sm:rounded-lg p-6">
            @livewire('inventory.monitor-stock-table')
        </div>

        <p class="mt-4 text-xs text-gray-500">
            Stock is tracked per receiving store. Only items with stock on hand are listed.
            <strong>Last price</strong> is from the latest GRN;
            <strong>Avg cost</strong> is weighted across receipts when purchase prices differ;
            <strong>Valuation</strong> = current stock × avg cost.
            <strong>Stock (days)</strong> = current stock ÷ daily usage (when daily usage is set).
        </p>
    </div>
</div>
</x-app-layout>
