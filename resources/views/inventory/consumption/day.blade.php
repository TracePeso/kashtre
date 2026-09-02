<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <a href="{{ route('inventory.consumption.month', ['item' => $item, 'month' => $month, 'store_id' => $store->id]) }}"
           class="text-sm text-blue-600 hover:text-blue-800">
            &larr; Back to {{ \Carbon\Carbon::parse($month.'-01')->format('F Y') }} days
        </a>

        <div class="mt-4">
            <h2 class="text-2xl font-bold text-gray-900">Daily consumption detail</h2>
            <p class="mt-1 text-lg text-gray-700">{{ $item->name }}</p>
            @if($item->code)
                <p class="text-sm text-gray-500">{{ $item->code }}</p>
            @endif
            <p class="mt-2 text-sm text-gray-600">
                {{ $store->selectLabel() }} ·
                {{ \Carbon\Carbon::parse($date)->format('l, M d, Y') }}
            </p>
        </div>

        @include('inventory.partials.subnav')

        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white shadow sm:rounded-lg p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Consumed this day</p>
                <p class="mt-1 text-xl font-bold text-gray-900 tabular-nums">{{ number_format($totalQuantity, 0) }}</p>
                @if($item->itemUnit)
                    <p class="text-xs text-gray-500">{{ $item->itemUnit->name }}</p>
                @endif
            </div>
            <div class="bg-white shadow sm:rounded-lg p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Sales records</p>
                <p class="mt-1 text-xl font-bold text-gray-900 tabular-nums">{{ number_format($salesSummary['sales_count']) }}</p>
                <p class="text-xs text-gray-500">On this date</p>
            </div>
            <div class="bg-white shadow sm:rounded-lg p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Qty from sales</p>
                <p class="mt-1 text-xl font-bold text-gray-900 tabular-nums">{{ number_format($salesSummary['sales_quantity'], 0) }}</p>
                <p class="text-xs text-gray-500">Sale units in sales rows</p>
            </div>
            <div class="bg-white shadow sm:rounded-lg p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Sales value</p>
                <p class="mt-1 text-xl font-bold text-gray-900 tabular-nums">UGX {{ number_format($salesSummary['sales_total_ugx'], 0) }}</p>
                <p class="text-xs text-gray-500">Total on this date</p>
            </div>
        </div>

        <div class="mt-6 bg-white shadow sm:rounded-lg p-6">
            <div class="mb-4">
                <h3 class="text-sm font-semibold text-gray-900">Sales records</h3>
                <p class="text-xs text-gray-500 mt-0.5">Detailed sales for this item on the selected date.</p>
            </div>
            @livewire('inventory.item-consumption-sales-table', [
                'itemId' => $item->id,
                'storeId' => $store->id,
                'date' => $date,
            ])
        </div>

        <div class="mt-6 bg-white shadow sm:rounded-lg p-6">
            <div class="mb-4">
                <h3 class="text-sm font-semibold text-gray-900">Hourly consumption</h3>
                <p class="text-xs text-gray-500 mt-0.5">How much was used in each hour of this day.</p>
            </div>
            @livewire('inventory.item-consumption-hourly-table', [
                'itemId' => $item->id,
                'storeId' => $store->id,
                'date' => $date,
            ])
        </div>
    </div>
</div>
</x-app-layout>
