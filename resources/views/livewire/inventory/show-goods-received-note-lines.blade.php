<div class="w-full min-w-0">
    <div class="fi-ta-ctn w-full overflow-x-auto">
        {{ $this->table }}
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-3">
        <p class="text-xs text-gray-500">
            {{ $goodsReceivedNote->lines()->count() }} item(s)
        </p>
        @php
            $lines = $goodsReceivedNote->lines;
            $totalSaleUnits = $lines->sum(fn ($line) => (float) $line->sale_units_purchased);
            $totalValue = $lines->sum(fn ($line) => (float) $line->quantity * (float) $line->purchase_price);
        @endphp
        <p class="text-sm font-semibold text-gray-900">
            {{ number_format($totalSaleUnits, 0) }} sale units · UGX {{ number_format($totalValue, 2) }}
        </p>
    </div>
</div>
