@php
    $item = $consumption->item;
    $sourceLabel = match ($consumption->source) {
        \App\Models\InventoryDailyConsumption::SOURCE_SALE => 'POS / Sale',
        \App\Models\InventoryDailyConsumption::SOURCE_ISSUE => 'Issue',
        \App\Models\InventoryDailyConsumption::SOURCE_MANUAL => 'Manual',
        default => ucfirst($consumption->source),
    };
@endphp
<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <a href="{{ route('inventory.consumption.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to consumption log</a>

        <div class="mt-4 md:flex md:items-start md:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Consumption entry</h2>
                <p class="mt-1 text-lg text-gray-700">{{ $item?->name ?? '—' }}</p>
                @if($item?->code)
                    <p class="text-sm text-gray-500">{{ $item->code }}</p>
                @endif
            </div>
            <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
                @if($item)
                    <a href="{{ route('inventory.monitor.history', $item) }}"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Item stock history
                    </a>
                @endif
                <a href="{{ route('inventory.monitor') }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Monitor stock
                    </a>
            </div>
        </div>

        @include('inventory.partials.subnav')

        <div class="mt-6 bg-white shadow sm:rounded-lg divide-y divide-gray-200">
            <div class="px-6 py-5 grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Date</p>
                    <p class="mt-1 font-medium text-gray-900">{{ $consumption->consumption_date->format('M d, Y') }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Quantity (SUOM)</p>
                    <p class="mt-1 font-medium text-gray-900 tabular-nums">{{ number_format((float) $consumption->quantity_suom, 4) }}</p>
                    @if($item?->itemUnit)
                        <p class="text-xs text-gray-500">{{ $item->itemUnit->name }}</p>
                    @endif
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Store</p>
                    <p class="mt-1 font-medium text-gray-900">{{ $consumption->store?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Source</p>
                    <p class="mt-1 font-medium text-gray-900">{{ $sourceLabel }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Recorded by</p>
                    <p class="mt-1 font-medium text-gray-900">{{ $consumption->recordedBy?->email ?? '—' }}</p>
                    @if($consumption->recordedBy?->name)
                        <p class="text-xs text-gray-500">{{ $consumption->recordedBy->name }}</p>
                    @endif
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Logged at</p>
                    <p class="mt-1 font-medium text-gray-900">{{ $consumption->created_at?->format('M d, Y H:i') ?? '—' }}</p>
                </div>
            </div>
            @if($consumption->notes)
                <div class="px-6 py-4 text-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Notes</p>
                    <p class="mt-1 text-gray-700">{{ $consumption->notes }}</p>
                </div>
            @endif
        </div>

        <div class="mt-6 bg-blue-50 border border-blue-100 rounded-lg px-6 py-5 text-sm text-blue-950">
            <h3 class="font-semibold text-blue-900">How this entry helps test inventory</h3>
            <ul class="mt-3 space-y-2 list-disc list-inside text-blue-900/90">
                <li><strong>Moving averages</strong> on Monitor Stock are calculated from these daily quantities (15 / 30 / 90 / 180 / 360-day windows).</li>
                <li><strong>Stock (days)</strong> = current stock ÷ daily average — tells you how long stock will last.</li>
                <li><strong>Order forms</strong> use the same averages plus safety/buffer/lead time to suggest reorder quantities.</li>
                <li>Sample data simulates ~6 months of hospital usage with weekend dips and realistic variance, so you can test ordering without waiting for real sales.</li>
            </ul>
        </div>

        @if($stockLevel)
            <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">System stock</p>
                    <p class="mt-1 text-xl font-bold text-gray-900 tabular-nums">{{ number_format((float) $stockLevel->quantity_suom, 0) }}</p>
                    <p class="text-xs text-gray-500">At {{ $consumption->store?->name }}</p>
                </div>
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">30-day avg (stored)</p>
                    <p class="mt-1 text-xl font-bold text-gray-900 tabular-nums">{{ number_format((float) ($stockLevel->ma_30_days ?? 0), 4) }}</p>
                    <p class="text-xs text-gray-500">From consumption log</p>
                </div>
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">30-day avg (to this date)</p>
                    <p class="mt-1 text-xl font-bold text-gray-900 tabular-nums">{{ number_format($rolling30Avg, 4) }}</p>
                    <p class="text-xs text-gray-500">Rolling window ending {{ $consumption->consumption_date->format('M d, Y') }}</p>
                </div>
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Stock (days)</p>
                    <p class="mt-1 text-xl font-bold text-gray-900 tabular-nums">
                        {{ $stockLevel->stockDays() !== null ? number_format($stockLevel->stockDays(), 1) : '—' }}
                    </p>
                    <p class="text-xs text-gray-500">Stock ÷ daily usage</p>
                </div>
            </div>
        @endif

        <div class="mt-6 bg-white shadow sm:rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900">Same item — prior 14 days at this store</h3>
                <p class="text-xs text-gray-500 mt-0.5">Shows the consumption pattern that drives moving averages.</p>
            </div>
            @if($recentForItem->isEmpty())
                <p class="px-6 py-8 text-sm text-gray-500 text-center">No other entries in this window.</p>
            @else
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Date</th>
                            <th class="px-6 py-3 text-right font-medium text-gray-500">Qty (SUOM)</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Source</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($recentForItem as $row)
                            <tr class="{{ $row->id === $consumption->id ? 'bg-blue-50' : '' }}">
                                <td class="px-6 py-3 text-gray-900">
                                    {{ $row->consumption_date->format('M d, Y') }}
                                    @if($row->id === $consumption->id)
                                        <span class="ml-2 text-xs text-blue-700 font-medium">(this entry)</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-right tabular-nums text-gray-900">{{ number_format((float) $row->quantity_suom, 4) }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ ucfirst($row->source) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
</x-app-layout>
