<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <a href="{{ route('inventory.reports.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; All reports</a>
        <h2 class="mt-2 text-2xl font-bold text-gray-900">Demand Forecast Report</h2>
        <p class="mt-1 text-sm text-gray-500">Period-based order quantities (AF) and forecast amounts (AG) using graduated moving averages.</p>
        @include('inventory.partials.subnav')
        <div class="mt-6 bg-white shadow sm:rounded-lg p-6">@livewire('inventory.demand-forecast-report-table')</div>
    </div>
</div>
</x-app-layout>
