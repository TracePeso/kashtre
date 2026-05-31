<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">Monitor Stock</h2>
                <p class="mt-1 text-sm text-gray-500">Items with stock on hand appear here after goods are received and GRNs are approved.</p>
            </div>
        </div>

        @include('inventory.partials.subnav')

        <div class="mt-6 bg-white shadow sm:rounded-lg p-6">
            @livewire('inventory.monitor-stock-table')
        </div>

        <p class="mt-4 text-xs text-gray-500">
            Only items with physical stock greater than zero are listed.
            <strong>Stock (days)</strong> = current stock ÷ daily usage.
            <strong>Valuation</strong> = current stock × purchase price.
        </p>
    </div>
</div>
</x-app-layout>
