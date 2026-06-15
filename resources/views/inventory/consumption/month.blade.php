<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <a href="{{ route('inventory.consumption.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to monthly consumption</a>

        <div class="mt-4 md:flex md:items-start md:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Monthly consumption</h2>
                <p class="mt-1 text-lg text-gray-700">{{ $item->name }}</p>
                @if($item->code)
                    <p class="text-sm text-gray-500">{{ $item->code }}</p>
                @endif
                <p class="mt-2 text-sm text-gray-600">
                    {{ $store->selectLabel() }} ·
                    {{ \Carbon\Carbon::parse($month.'-01')->format('F Y') }}
                </p>
            </div>
            <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
                <a href="{{ route('inventory.monitor.history', $item) }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Item stock history
                </a>
                <a href="{{ route('inventory.monitor') }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Monitor stock
                </a>
            </div>
        </div>

        @include('inventory.partials.subnav')

        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white shadow sm:rounded-lg p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Consumed this month</p>
                <p class="mt-1 text-xl font-bold text-gray-900 tabular-nums">{{ number_format($summary['total_quantity_suom'], 0) }}</p>
                @if($item->itemUnit)
                    <p class="text-xs text-gray-500">{{ $item->itemUnit->name }}</p>
                @endif
            </div>
            <div class="bg-white shadow sm:rounded-lg p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Daily average</p>
                <p class="mt-1 text-xl font-bold text-gray-900 tabular-nums">{{ number_format($summary['daily_average'], 2) }}</p>
                <p class="text-xs text-gray-500">Total ÷ {{ $summary['days_in_month'] }} days</p>
            </div>
            <div class="bg-white shadow sm:rounded-lg p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Days with usage</p>
                <p class="mt-1 text-xl font-bold text-gray-900 tabular-nums">{{ number_format($summary['days_with_usage']) }}</p>
                <p class="text-xs text-gray-500">Of {{ $summary['days_in_month'] }} days in month</p>
            </div>
            @if($stockLevel)
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Current system stock</p>
                    <p class="mt-1 text-xl font-bold text-gray-900 tabular-nums">{{ number_format((float) $stockLevel->quantity_suom, 0) }}</p>
                    <p class="text-xs text-gray-500">At this store today</p>
                </div>
            @endif
        </div>

        <div class="mt-6 bg-white shadow sm:rounded-lg p-6">
            <div class="mb-4">
                <h3 class="text-sm font-semibold text-gray-900">Daily consumption</h3>
                <p class="text-xs text-gray-500 mt-0.5">Click a day to view sales records and hourly breakdown.</p>
            </div>
            @livewire('inventory.item-consumption-daily-table', [
                'itemId' => $item->id,
                'storeId' => $store->id,
                'month' => $month,
            ])
        </div>

        <div class="mt-6 bg-blue-50 border border-blue-100 rounded-lg px-6 py-5 text-sm text-blue-950">
            <h3 class="font-semibold text-blue-900">Drill-down</h3>
            <p class="mt-2 text-blue-900/90">
                <strong>Month</strong> → <strong>Day</strong> → <strong>Hour</strong>.
                These totals drive stock-days and order suggestions on Monitor Stock.
            </p>
        </div>
    </div>
</div>
</x-app-layout>
