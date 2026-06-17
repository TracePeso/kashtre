<div>
    {{ $this->table }}

    <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Year</p>
            <p class="mt-1 text-2xl font-bold text-gray-900 tabular-nums">{{ $summary['year'] }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Monthly consumption view</p>
        </div>
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Total consumed</p>
            <p class="mt-1 text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($summary['total_quantity_suom'], 0) }}</p>
            <p class="text-xs text-gray-500 mt-0.5">SUOM in selected period</p>
        </div>
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Items with usage</p>
            <p class="mt-1 text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($summary['distinct_items']) }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Distinct items at this store</p>
        </div>
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Monthly rows</p>
            <p class="mt-1 text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($summary['month_rows']) }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Item × month</p>
        </div>
    </div>
</div>
