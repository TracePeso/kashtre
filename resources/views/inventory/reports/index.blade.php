<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl font-bold text-gray-900">Inventory Reports</h2>
        <p class="mt-1 text-sm text-gray-500">Excel-aligned stock analytics and ordering insights.</p>
        @include('inventory.partials.subnav')

        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach([
                ['route' => 'inventory.reports.reorder', 'title' => 'Reorder Point', 'desc' => 'Days left to order (AM) and notification dates (AY).'],
                ['route' => 'inventory.reports.valuation', 'title' => 'Inventory Valuation', 'desc' => 'Current stock value (O = M × F/J).'],
                ['route' => 'inventory.reports.shrinkage', 'title' => 'Shrinkage', 'desc' => 'System vs current stock variance (AV / AW).'],
                ['route' => 'inventory.reports.demand', 'title' => 'Demand Forecast', 'desc' => 'Suggested order quantities and amounts (AF / AG).'],
                ['route' => 'inventory.reports.aging', 'title' => 'Stock Aging', 'desc' => 'Days since last GRN delivery (U).'],
            ] as $report)
                <a href="{{ route($report['route']) }}"
                   class="block bg-white shadow sm:rounded-lg p-5 hover:ring-2 hover:ring-blue-200 transition">
                    <h3 class="text-lg font-semibold text-gray-900">{{ $report['title'] }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ $report['desc'] }}</p>
                </a>
            @endforeach
        </div>
    </div>
</div>
</x-app-layout>
