<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl font-bold text-gray-900">Inventory Reports</h2>
        @include('inventory.partials.subnav')

        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach([
                ['route' => 'inventory.reports.reorder', 'title' => 'Reorder Point'],
                ['route' => 'inventory.reports.valuation', 'title' => 'Inventory Valuation'],
                ['route' => 'inventory.reports.shrinkage', 'title' => 'Shrinkage'],
                ['route' => 'inventory.reports.demand', 'title' => 'Demand Forecast'],
                ['route' => 'inventory.reports.aging', 'title' => 'Stock Aging'],
            ] as $report)
                <a href="{{ route($report['route']) }}"
                   class="block bg-white shadow sm:rounded-lg p-5 hover:ring-2 hover:ring-blue-200 transition">
                    <h3 class="text-lg font-semibold text-gray-900">{{ $report['title'] }}</h3>
                </a>
            @endforeach
        </div>
    </div>
</div>
</x-app-layout>
