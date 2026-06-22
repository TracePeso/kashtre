<div>
    @if ($this->selectedStoreLabel())
        <div class="mb-4 rounded-lg border border-blue-100 bg-blue-50/70 px-4 py-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-blue-700">Your store</p>
                <p class="text-sm font-semibold text-blue-950">{{ $this->selectedStoreLabel() }}</p>
            </div>
            <p class="text-sm text-blue-800">Last {{ \App\Livewire\Inventory\ListDailyConsumptions::RECENT_DAYS }} days · {{ $this->periodLabel() }}</p>
        </div>
    @endif

    {{ $this->table }}

    <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Period</p>
            <p class="mt-1 text-lg font-bold text-gray-900">{{ $this->periodLabel() }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Last {{ \App\Livewire\Inventory\ListDailyConsumptions::RECENT_DAYS }} days</p>
        </div>
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Total consumed</p>
            <p class="mt-1 text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($summary['total_quantity_suom'], 0) }}</p>
            <p class="text-xs text-gray-500 mt-0.5">SUOM in this period</p>
        </div>
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Items with usage</p>
            <p class="mt-1 text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($summary['distinct_items']) }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Distinct items at this store</p>
        </div>
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Activity rows</p>
            <p class="mt-1 text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($summary['item_day_rows']) }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Item × day</p>
        </div>
    </div>
</div>
