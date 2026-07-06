@php
    $stockLevels = \App\Models\InventoryStockLevel::query()
        ->where('business_id', $item->business_id)
        ->where('item_id', $item->id)
        ->with('store')
        ->get();
    $currentStock = (float) $stockLevels->sum('quantity_suom');
    $valuation = round($stockLevels->sum(fn ($level) => $level->valuationTotal()), 2);
    $lastPrice = (float) ($stockLevels->sortByDesc('updated_at')->first()?->last_purchase_price ?? 0);
    $primaryLevel = $stockLevels->sortByDesc('quantity_suom')->first();
    $avgCost = (float) ($primaryLevel?->weighted_avg_cost ?? $lastPrice);
    $stockDays = $primaryLevel?->stockDays();
@endphp
<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('inventory.monitor') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to Monitor Stock</a>
        </div>

        <div class="md:flex md:items-start md:justify-between gap-4 mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Stock history</h2>
                <p class="mt-1 text-lg text-gray-700">{{ $item->name }}</p>
                @if($item->code)
                    <p class="text-sm text-gray-500">Code: {{ $item->code }}</p>
                @endif
            </div>
        </div>

        @include('inventory.partials.subnav')

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-lg bg-white border border-gray-200 px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Physical stock</p>
                <p class="mt-1 text-xl font-bold text-gray-900 tabular-nums">{{ number_format($currentStock, 0) }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Sale units on hand (all stores)</p>
            </div>
            <div class="rounded-lg bg-white border border-gray-200 px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Last purchase price</p>
                <p class="mt-1 text-xl font-bold text-gray-900 tabular-nums">UGX {{ number_format($lastPrice, 2) }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Most recent goods receive note line price</p>
            </div>
            <div class="rounded-lg bg-white border border-emerald-200 bg-emerald-50/50 px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-emerald-800">Current valuation</p>
                <p class="mt-1 text-xl font-bold text-emerald-950 tabular-nums">UGX {{ number_format($valuation, 2) }}</p>
                <p class="text-xs text-emerald-800 mt-0.5">
                    @if($primaryLevel?->weighted_avg_cost)
                        Stock × avg cost (UGX {{ number_format($avgCost, 2) }})
                    @else
                        Stock × last purchase price
                    @endif
                </p>
            </div>
            <div class="rounded-lg bg-white border border-gray-200 px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Stock (days)</p>
                <p class="mt-1 text-xl font-bold text-gray-900 tabular-nums">
                    {{ $stockDays !== null ? number_format($stockDays, 1) : '—' }}
                </p>
                <p class="text-xs text-gray-500 mt-0.5">
                    @if($stockDays !== null)
                        Current stock ÷ daily usage
                    @else
                        Set daily usage on stock to enable
                    @endif
                </p>
            </div>
        </div>

        @if($stockLevels->isNotEmpty())
            <div class="mt-4 rounded-lg bg-white border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-sm font-medium text-gray-900">Stock by store</h3>
                </div>
                <ul class="divide-y divide-gray-200">
                    @foreach($stockLevels as $level)
                        <li class="px-4 py-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span class="font-medium text-gray-900">{{ $level->store?->name ?? '—' }}</span>
                            <span class="text-gray-600 tabular-nums">{{ number_format((float) $level->quantity_suom, 0) }} units · UGX {{ number_format($level->valuationTotal(), 2) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <p class="mt-4 text-sm text-gray-600">
            Each row below is one stock movement. <strong>Purchase price</strong> is the unit cost for that receipt;
            <strong>Receipt valuation</strong> is change × price for that goods receive note;
            <strong>Stock value after</strong> is total inventory value on hand (weighted average when prices differ).
        </p>

        <div class="mt-6 bg-white shadow sm:rounded-lg p-6">
            @livewire('inventory.list-item-stock-history', ['item' => $item])
        </div>
    </div>
</div>
</x-app-layout>
