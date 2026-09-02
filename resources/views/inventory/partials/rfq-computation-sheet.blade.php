@php
    $canAllocate = (bool) ($showSupplierSelection ?? false);
    $hasQuotes = count($sheet['suppliers'] ?? []) > 0;
    $hasSavedAllocations = (bool) ($hasSavedAllocations ?? false);
    $showAllocationForm = (bool) ($showAllocationForm ?? $canAllocate);
    $showAllocationSummary = $canAllocate && $hasSavedAllocations && ! $showAllocationForm;
@endphp

<section class="bg-white shadow sm:rounded-lg overflow-hidden border border-slate-200"
         @if($showAllocationForm && $computationForm)
             x-data="rfqComputationSheet(@js($computationForm))"
         @endif>
    <div class="px-5 py-4 border-b border-slate-200 bg-slate-50/80 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h3 class="text-sm font-semibold text-gray-900">
                @if($showAllocationSummary)
                    Saved allocation
                @else
                    Quotation comparison &amp; allocation
                @endif
            </h3>
            <p class="text-xs text-gray-500 mt-1 max-w-2xl">
                @if($showAllocationSummary)
                    Supplier selections are saved below. Generate LPOs when ready, or edit the allocation if you need to make changes.
                @else
                    Compare quoted unit prices, assign each item to the winning supplier (partial quantities allowed), and add internal notes before generating LPOs.
                    <span class="block mt-1 text-gray-400">Only rows in <strong>Your allocation</strong> with a supplier and quantity are saved.</span>
                @endif
            </p>
            @if($hasQuotes)
                <div class="mt-3 flex flex-wrap gap-3 text-[11px] text-gray-600">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="inline-block w-3 h-3 rounded-sm bg-emerald-100 ring-1 ring-emerald-300"></span>
                        Lowest price
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="inline-block w-3 h-3 rounded-sm bg-indigo-100 ring-2 ring-indigo-400"></span>
                        Selected for LPO
                    </span>
                </div>
            @endif
        </div>
        @if($canAllocate)
            <div class="shrink-0 flex flex-wrap gap-2">
                @if($showAllocationSummary)
                    <a href="{{ route('inventory.orders.quotations.compare', ['order' => $order, 'edit_allocation' => 1]) }}"
                       class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                        Edit allocation
                    </a>
                @else
                    @if($hasSavedAllocations)
                        <a href="{{ route('inventory.orders.quotations.compare', $order) }}"
                           class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                            Back to summary
                        </a>
                    @endif
                    <button type="button"
                            @click="selectLowestPricesForAll()"
                            class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium text-emerald-800 bg-emerald-50 border border-emerald-200 hover:bg-emerald-100"
                            x-show="suppliers.length > 0"
                            x-cloak>
                        Select lowest prices
                    </button>
                    <button type="button"
                            @click="expandAll()"
                            class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50"
                            x-show="suppliers.length > 0"
                            x-cloak>
                        Expand all
                    </button>
                    <button type="button"
                            @click="collapseAll()"
                            class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50"
                            x-show="suppliers.length > 0"
                            x-cloak>
                        Collapse all
                    </button>
                @endif
                <a href="{{ route('inventory.orders.purchase-orders.preview-awards', $order) }}"
                   class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 @if(! $hasQuotes) pointer-events-none opacity-50 @endif">
                    Generate LPOs
                </a>
            </div>
        @endif
    </div>

    @if(! $hasQuotes)
        <p class="px-5 py-8 text-sm text-center text-gray-500">
            No supplier quotations recorded yet. Invite suppliers and enter their quotes above to unlock comparison and allocation.
        </p>
    @elseif($showAllocationSummary)
        <div class="px-5 py-3 border-b border-slate-100 bg-emerald-50/50">
            <p class="text-sm text-emerald-900">
                <span class="font-medium">Allocation saved.</span>
                Review the selections below, then generate LPOs or edit if needed.
            </p>
        </div>
        <div class="px-5 py-3 border-b border-slate-100">
            <div class="flex flex-wrap gap-2">
                @foreach($sheet['suppliers'] as $sup)
                    <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs min-w-[9rem]">
                        <p class="font-medium text-gray-900 truncate">{{ $sup['supplier_name'] }}</p>
                        <p class="text-gray-500 mt-0.5">{{ $sup['status_label'] }}</p>
                        <p class="mt-1 font-mono font-semibold text-gray-900 tabular-nums">UGX {{ number_format($sup['total_amount'], 2) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-medium">Item</th>
                        <th class="px-4 py-2.5 text-right font-medium">RFQ qty</th>
                        <th class="px-4 py-2.5 text-left font-medium">Allocated to</th>
                        <th class="px-4 py-2.5 text-right font-medium">Qty</th>
                        <th class="px-4 py-2.5 text-right font-medium">Unit price</th>
                        <th class="px-4 py-2.5 text-right font-medium">Line total</th>
                        <th class="px-4 py-2.5 text-left font-medium">Comment</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($sheet['lines'] as $row)
                        @php
                            $orderLine = $order->lines->firstWhere('id', $row['order_line_id']);
                            $awards = collect($row['awards'] ?? []);
                            $qtyDecimals = $orderLine?->item?->usesPackagingUnits() ? 2 : 0;
                            $rfqQty = $orderLine ? (float) $orderLine->rfqQuantity() : (float) ($row['rfq_qty'] ?? 0);
                            $comment = $orderLine?->quotation_analysis_comment;
                        @endphp
                        @if($awards->isEmpty())
                            <tr class="bg-gray-50/50">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ $row['item_name'] }}</div>
                                    @if(! empty($row['item_code']))
                                        <div class="text-xs text-gray-500">{{ $row['item_code'] }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-500">{{ number_format($rfqQty, $qtyDecimals) }}</td>
                                <td colspan="4" class="px-4 py-3 text-sm text-gray-400 italic">Not allocated</td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ $comment ?: '—' }}</td>
                            </tr>
                        @else
                            @foreach($awards as $awardIndex => $award)
                                <tr @class(['bg-indigo-50/30' => $awardIndex === 0])>
                                    @if($awardIndex === 0)
                                        <td class="px-4 py-3 align-top" rowspan="{{ $awards->count() }}">
                                            <div class="font-medium text-gray-900">{{ $row['item_name'] }}</div>
                                            @if(! empty($row['item_code']))
                                                <div class="text-xs text-gray-500">{{ $row['item_code'] }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right tabular-nums align-top" rowspan="{{ $awards->count() }}">
                                            {{ number_format($rfqQty, $qtyDecimals) }}
                                        </td>
                                    @endif
                                    <td class="px-4 py-3 font-medium text-indigo-900">{{ $award['supplier_name'] }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $award['awarded_quantity_suom'], $qtyDecimals) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $award['unit_price'], 2) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums font-medium">
                                        UGX {{ number_format((float) $award['awarded_quantity_suom'] * (float) $award['unit_price'], 2) }}
                                    </td>
                                    @if($awardIndex === 0)
                                        <td class="px-4 py-3 text-xs text-gray-600 align-top" rowspan="{{ $awards->count() }}">
                                            {{ $comment ?: '—' }}
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @elseif($showAllocationForm)
        <form action="{{ route('inventory.orders.quotations.awards.store', $order) }}" method="POST" novalidate @submit="handleSubmit($event)">
            @csrf
            <div class="px-5 py-3 border-b border-slate-100 bg-white">
                <div class="flex flex-wrap gap-2">
                    @foreach($sheet['suppliers'] as $sup)
                        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs min-w-[9rem]">
                            <p class="font-medium text-gray-900 truncate">{{ $sup['supplier_name'] }}</p>
                            <p class="text-gray-500 mt-0.5">{{ $sup['status_label'] }}</p>
                            <p class="mt-1 font-mono font-semibold text-gray-900 tabular-nums">UGX {{ number_format($sup['total_amount'], 2) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="divide-y divide-slate-100">
                <template x-if="suppliers.length === 0">
                    <p class="px-5 py-6 text-sm text-center text-gray-500">Record at least one supplier quotation before allocating items.</p>
                </template>

                <template x-for="(line, lineIndex) in lines" :key="line.order_line_id">
                    <div class="border-b border-slate-100 last:border-b-0">
                        <button type="button"
                                @click="toggleLine(line.order_line_id)"
                                class="w-full px-5 py-3 flex flex-wrap items-start justify-between gap-3 text-left hover:bg-slate-50/80 transition-colors">
                            <div class="flex items-start gap-2 min-w-0">
                                <svg class="w-4 h-4 mt-0.5 shrink-0 text-gray-400 transition-transform"
                                     :class="{ 'rotate-90': isLineExpanded(line.order_line_id) }"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900" x-text="line.item_name"></p>
                                    <p class="text-xs text-gray-500" x-show="line.item_code" x-text="line.item_code"></p>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 text-xs shrink-0">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-gray-700">
                                    RFQ qty <span class="font-semibold tabular-nums" x-text="formatQty(line.rfq_qty, lineQtyDecimals(line))"></span>
                                </span>
                                <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-indigo-800">
                                    Allocated <span class="font-semibold tabular-nums" x-text="formatQty(allocatedTotal(line), lineQtyDecimals(line))"></span>
                                </span>
                                <span class="rounded-full px-2.5 py-1 font-medium"
                                      :class="fulfillmentClass(line)"
                                      x-text="fulfillmentLabel(line)"></span>
                            </div>
                        </button>

                        <div x-show="isLineExpanded(line.order_line_id)" x-collapse class="px-5 pb-4 space-y-4">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 mb-2">Quoted unit prices (UGX)</p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="supplier in sheetSuppliers" :key="supplier.supplier_id">
                                    <div class="rounded-lg border px-3 py-2 min-w-[8.5rem] text-xs"
                                         :class="quoteCardClass(line, supplier.supplier_id)">
                                        <p class="font-medium text-gray-900 truncate" x-text="supplier.supplier_name"></p>
                                        <template x-if="quoteForSupplier(line, supplier.supplier_id)">
                                            <div class="mt-1">
                                                <p class="text-sm font-semibold tabular-nums text-gray-900"
                                                   x-text="formatMoney(quoteForSupplier(line, supplier.supplier_id).unit_price)"></p>
                                                <p class="text-[10px] text-gray-500 mt-0.5">
                                                    Qty <span x-text="formatQty(quoteForSupplier(line, supplier.supplier_id).quoted_qty)"></span>
                                                </p>
                                                <p class="text-[10px] font-medium text-indigo-700 mt-0.5"
                                                   x-show="isAwardedSupplier(line, supplier.supplier_id)">
                                                    Selected
                                                </p>
                                                <p class="text-[10px] font-medium text-emerald-700 mt-0.5"
                                                   x-show="isBestPrice(line, supplier.supplier_id)">
                                                    Lowest price
                                                </p>
                                            </div>
                                        </template>
                                        <template x-if="! quoteForSupplier(line, supplier.supplier_id)">
                                            <p class="mt-1 text-gray-400">No quote</p>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Your allocation</p>
                                <button type="button"
                                        @click="addRow(lineIndex)"
                                        class="text-xs font-medium text-indigo-700 hover:text-indigo-900"
                                        x-show="canAddSupplierRow(line) || line.rows.length === 0">
                                    + Add supplier
                                </button>
                            </div>

                            <template x-if="line.rows.length === 0">
                                <p class="text-xs text-gray-500 italic rounded-md border border-dashed border-gray-200 px-3 py-2">
                                    No supplier selected yet. Click <strong>Add supplier</strong> to assign this item.
                                </p>
                            </template>

                            <div class="space-y-2">
                                <template x-for="(row, rowIndex) in line.rows" :key="rowIndex">
                                    <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-end rounded-md border border-gray-100 bg-gray-50/60 px-3 py-2">
                                        <input type="hidden"
                                               :value="line.order_line_id">

                                        <div class="md:col-span-4">
                                            <label class="block text-[10px] font-medium text-gray-500 uppercase tracking-wide">Supplier</label>
                                            <select class="mt-0.5 block w-full rounded-md border-gray-300 text-sm shadow-sm"
                                                    x-model="row.supplier_id"
                                                    @change="applyQuote(lineIndex, rowIndex)">
                                                <option value="">Select supplier…</option>
                                                <template x-for="supplier in suppliersForLine(line)" :key="supplier.supplier_id">
                                                    <option :value="String(supplier.supplier_id)" x-text="supplier.supplier_name"></option>
                                                </template>
                                            </select>
                                        </div>

                                        <div class="md:col-span-2">
                                            <label class="block text-[10px] font-medium text-gray-500 uppercase tracking-wide">Qty</label>
                                            <input type="number" step="any" min="0"
                                                   class="mt-0.5 block w-full rounded-md border-gray-300 text-sm text-right tabular-nums"
                                                   x-model="row.awarded_quantity_suom"
                                                   placeholder="—">
                                        </div>

                                        <div class="md:col-span-2">
                                            <label class="block text-[10px] font-medium text-gray-500 uppercase tracking-wide">Unit price</label>
                                            <input type="number" step="any" min="0"
                                                   class="mt-0.5 block w-full rounded-md border-gray-300 text-sm text-right tabular-nums"
                                                   x-model="row.unit_price"
                                                   placeholder="—">
                                        </div>

                                        <div class="md:col-span-2">
                                            <label class="block text-[10px] font-medium text-gray-500 uppercase tracking-wide">Line total</label>
                                            <p class="mt-1.5 text-sm font-medium text-right tabular-nums text-gray-900"
                                               x-text="'UGX ' + formatMoney(lineTotal(row))"></p>
                                        </div>

                                        <div class="md:col-span-2 flex justify-end">
                                            <button type="button"
                                                    @click="removeRow(lineIndex, rowIndex)"
                                                    class="text-xs font-medium text-red-600 hover:text-red-800">
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-semibold uppercase tracking-wide text-gray-500 mb-1"
                                   :for="`line-comment-${line.order_line_id}`">
                                Internal comment
                            </label>
                            <input type="hidden"
                                   :value="line.order_line_id">
                            <textarea :id="`line-comment-${line.order_line_id}`"
                                      rows="2"
                                      maxlength="2000"
                                      x-model="line.analysis_comment"
                                      placeholder="e.g. partial supply acceptable, preferred brand, delivery urgency…"
                                      class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="px-5 py-4 border-t border-slate-200 bg-slate-50/50 flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs text-gray-500">
                        Save your allocation and comments, then click <strong>Generate LPOs</strong> to create one draft purchase order per supplier.
                    </p>
                    <p x-show="submitError" x-text="submitError" class="mt-2 text-xs font-medium text-red-700" x-cloak></p>
                </div>
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-white bg-slate-800 hover:bg-slate-900 disabled:opacity-60"
                        :disabled="saving || suppliers.length === 0">
                    <span x-show="! saving">Save allocation &amp; comments</span>
                    <span x-show="saving" x-cloak>Saving…</span>
                </button>
            </div>
        </form>
    @else
        <div class="px-5 py-3 border-b border-slate-100">
            <div class="flex flex-wrap gap-2">
                @foreach($sheet['suppliers'] as $sup)
                    <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs min-w-[9rem]">
                        <p class="font-medium text-gray-900 truncate">{{ $sup['supplier_name'] }}</p>
                        <p class="text-gray-500 mt-0.5">{{ $sup['status_label'] }}</p>
                        <p class="mt-1 font-mono font-semibold text-gray-900 tabular-nums">UGX {{ number_format($sup['total_amount'], 2) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-medium">Item</th>
                        <th class="px-4 py-2.5 text-right font-medium">RFQ qty</th>
                        @foreach($sheet['suppliers'] as $sup)
                            <th class="px-4 py-2.5 text-right font-medium">{{ $sup['supplier_name'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($sheet['lines'] as $row)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">{{ $row['item_name'] }}</div>
                                @if(! empty($row['item_code']))
                                    <div class="text-xs text-gray-500">{{ $row['item_code'] }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['rfq_qty'], 0) }}</td>
                            @foreach($sheet['suppliers'] as $supplierColumn)
                                @php
                                    $supplierColumnId = $supplierColumn['supplier_id'];
                                    $quoteCell = $row['quotes'][$supplierColumnId] ?? null;
                                    $bestSupplierId = $row['best_supplier_id'];
                                    $isBestPrice = $quoteCell && $supplierColumnId === $bestSupplierId;
                                @endphp
                                <td @class([
                                    'px-4 py-3 text-right tabular-nums',
                                    'bg-emerald-50 font-semibold text-emerald-900' => $isBestPrice,
                                ])>
                                    @if($quoteCell && $quoteCell['unit_price'] !== null)
                                        {{ number_format($quoteCell['unit_price'], 2) }}
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

@if($showAllocationForm && $computationForm)
<script>
    function buildBestPriceRow(line, quoteLookup) {
        const lineQuotes = quoteLookup[line.order_line_id] || quoteLookup[String(line.order_line_id)] || {};
        const rfq = Number(line.rfq_qty || 0);
        let bestSupplierId = line.best_supplier_id ? String(line.best_supplier_id) : null;
        let bestQuote = bestSupplierId ? (lineQuotes[bestSupplierId] || null) : null;

        if (! bestQuote || Number(bestQuote.unit_price || 0) <= 0) {
            bestSupplierId = null;
            bestQuote = null;
            let bestPrice = null;

            Object.entries(lineQuotes).forEach(([supplierId, quote]) => {
                const unitPrice = Number(quote.unit_price || 0);
                const quotedQty = Number(quote.quoted_qty || 0);

                if (unitPrice <= 0) {
                    return;
                }

                if (quotedQty <= 0 && rfq <= 0) {
                    return;
                }

                if (bestPrice === null || unitPrice < bestPrice) {
                    bestPrice = unitPrice;
                    bestSupplierId = String(supplierId);
                    bestQuote = quote;
                }
            });
        }

        if (! bestSupplierId || ! bestQuote || Number(bestQuote.unit_price || 0) <= 0) {
            return [];
        }

        let qty = Number(bestQuote.quoted_qty || 0);
        if (qty <= 0 && rfq > 0) {
            qty = rfq;
        }
        if (rfq > 0 && qty > 0) {
            qty = Math.min(qty, rfq);
        }

        if (qty <= 0) {
            return [];
        }

        return [{
            supplier_id: bestSupplierId,
            awarded_quantity_suom: qty,
            unit_price: bestQuote.unit_price,
        }];
    }

    function rfqComputationSheet(initial) {
        const quoteLookup = initial.quote_lookup || {};

        return {
            suppliers: initial.suppliers || [],
            sheetSuppliers: initial.sheet_suppliers || [],
            saving: false,
            submitError: '',
            lines: (initial.lines || []).map((line) => {
                let rows = [];

                if (line.awards && line.awards.length > 0) {
                    rows = line.awards
                        .filter((award) => Number(award.supplier_id) > 0 && Number(award.awarded_quantity_suom) > 0)
                        .map((award) => ({
                            supplier_id: String(award.supplier_id),
                            awarded_quantity_suom: award.awarded_quantity_suom,
                            unit_price: award.unit_price,
                        }));
                }

                if (rows.length === 0) {
                    rows = buildBestPriceRow(line, quoteLookup);
                }

                if (rows.length === 0 && Number(line.rfq_qty) > 0) {
                    rows = [{ supplier_id: '', awarded_quantity_suom: '', unit_price: '' }];
                }

                return {
                    ...line,
                    analysis_comment: line.analysis_comment || '',
                    rows,
                };
            }),
            expandedLines: Object.fromEntries(
                (initial.lines || []).map((line) => [String(line.order_line_id), true])
            ),

            isLineExpanded(lineId) {
                const key = String(lineId);
                return this.expandedLines[key] !== false;
            },

            toggleLine(lineId) {
                const key = String(lineId);
                this.expandedLines[key] = ! this.isLineExpanded(lineId);
            },

            expandAll() {
                this.lines.forEach((line) => {
                    this.expandedLines[String(line.order_line_id)] = true;
                });
            },

            collapseAll() {
                this.lines.forEach((line) => {
                    this.expandedLines[String(line.order_line_id)] = false;
                });
            },

            selectLowestPricesForAll() {
                this.submitError = '';
                this.lines.forEach((line, lineIndex) => {
                    const bestRows = buildBestPriceRow(line, quoteLookup);
                    if (bestRows.length > 0) {
                        this.lines[lineIndex].rows = bestRows;
                    }
                });
            },

            canAddSupplierRow(line) {
                return this.suppliersForLine(line).length > line.rows.length;
            },

            fieldIndex(lineIndex, rowIndex) {
                let index = 0;
                for (let i = 0; i < lineIndex; i++) {
                    index += this.lines[i].rows.length;
                }
                return index + rowIndex;
            },

            formatQty(value, decimals = 0) {
                return Number(value || 0).toLocaleString(undefined, {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: decimals,
                });
            },

            lineQtyDecimals(line) {
                return Number(line.qty_decimals || 0);
            },

            skipRow(row) {
                return ! row.supplier_id || Number(row.awarded_quantity_suom || 0) <= 0;
            },

            validAwardCount() {
                return this.collectAwards().length;
            },

            collectAwards() {
                const awards = [];

                this.lines.forEach((line) => {
                    line.rows.forEach((row) => {
                        if (this.skipRow(row)) {
                            return;
                        }

                        awards.push({
                            inventory_order_line_id: line.order_line_id,
                            supplier_id: row.supplier_id,
                            awarded_quantity_suom: row.awarded_quantity_suom,
                            unit_price: row.unit_price ?? '',
                        });
                    });
                });

                return awards;
            },

            appendFormField(form, name, value) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value ?? '';
                input.dataset.rfqFormField = '1';
                form.appendChild(input);
            },

            handleSubmit(event) {
                event.preventDefault();
                this.submitError = '';

                const form = event.target;
                form.querySelectorAll('[data-rfq-form-field]').forEach((el) => el.remove());

                const awards = this.collectAwards();

                if (awards.length === 0) {
                    this.submitError = 'Pick a supplier and enter a quantity for at least one item under “Your allocation”, then save again.';
                    form.scrollIntoView({ behavior: 'smooth', block: 'end' });
                    return;
                }

                awards.forEach((award, index) => {
                    this.appendFormField(form, `awards[${index}][inventory_order_line_id]`, award.inventory_order_line_id);
                    this.appendFormField(form, `awards[${index}][supplier_id]`, award.supplier_id);
                    this.appendFormField(form, `awards[${index}][awarded_quantity_suom]`, award.awarded_quantity_suom);
                    this.appendFormField(form, `awards[${index}][unit_price]`, award.unit_price);
                });

                this.lines.forEach((line, index) => {
                    this.appendFormField(form, `line_comments[${index}][inventory_order_line_id]`, line.order_line_id);
                    this.appendFormField(form, `line_comments[${index}][quotation_analysis_comment]`, line.analysis_comment || '');
                });

                this.saving = true;
                form.submit();
            },

            formatMoney(value) {
                return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },

            quoteForSupplier(line, supplierId) {
                const quotes = line.quotes || {};
                return quotes[supplierId] || quotes[String(supplierId)] || null;
            },

            isBestPrice(line, supplierId) {
                return line.best_supplier_id && Number(line.best_supplier_id) === Number(supplierId);
            },

            isAwardedSupplier(line, supplierId) {
                const selectedInForm = line.rows.some((row) => {
                    return String(row.supplier_id) === String(supplierId)
                        && Number(row.awarded_quantity_suom || 0) > 0;
                });

                if (selectedInForm) {
                    return true;
                }

                const quote = this.quoteForSupplier(line, supplierId);

                return quote && quote.is_awarded;
            },

            quoteCardClass(line, supplierId) {
                const classes = ['border-gray-200', 'bg-white'];
                if (this.isAwardedSupplier(line, supplierId)) {
                    classes.push('ring-2', 'ring-indigo-400', 'bg-indigo-50', 'border-indigo-200');
                } else if (this.isBestPrice(line, supplierId)) {
                    classes.push('bg-emerald-50', 'border-emerald-200');
                }
                return classes.join(' ');
            },

            fulfillmentLabel(line) {
                const total = this.allocatedTotal(line);
                const rfq = Number(line.rfq_qty || 0);
                const decimals = this.lineQtyDecimals(line);
                if (total <= 0) {
                    return 'Unallocated';
                }
                if (rfq > 0 && total >= rfq - 0.0001) {
                    return 'Fully allocated';
                }
                if (rfq > 0) {
                    return `Partial (${this.formatQty(total, decimals)} / ${this.formatQty(rfq, decimals)})`;
                }
                return `Allocated ${this.formatQty(total, decimals)}`;
            },

            fulfillmentClass(line) {
                const label = this.fulfillmentLabel(line);
                if (label.startsWith('Fully')) {
                    return 'bg-emerald-50 text-emerald-800';
                }
                if (label.startsWith('Partial')) {
                    return 'bg-amber-50 text-amber-800';
                }
                return 'bg-gray-100 text-gray-600';
            },

            quoteFor(line, row) {
                if (! row.supplier_id) {
                    return null;
                }
                const lineQuotes = quoteLookup[line.order_line_id] || quoteLookup[String(line.order_line_id)] || {};
                return lineQuotes[row.supplier_id] || lineQuotes[String(row.supplier_id)] || null;
            },

            allocatedTotal(line) {
                return line.rows.reduce((sum, row) => sum + Number(row.awarded_quantity_suom || 0), 0);
            },

            remainingQty(line) {
                return Math.max(0, Number(line.rfq_qty || 0) - this.allocatedTotal(line));
            },

            lineTotal(row) {
                return Number(row.awarded_quantity_suom || 0) * Number(row.unit_price || 0);
            },

            suppliersForLine(line) {
                const lineQuotes = quoteLookup[line.order_line_id] || quoteLookup[String(line.order_line_id)] || {};
                const quotedIds = new Set(Object.keys(lineQuotes).map((id) => String(id)));
                return this.suppliers.filter((supplier) => quotedIds.has(String(supplier.supplier_id)));
            },

            addRow(lineIndex) {
                const line = this.lines[lineIndex];
                const remaining = this.remainingQty(line);
                this.lines[lineIndex].rows.push({
                    supplier_id: '',
                    awarded_quantity_suom: remaining > 0 ? remaining : '',
                    unit_price: '',
                });
            },

            removeRow(lineIndex, rowIndex) {
                this.lines[lineIndex].rows.splice(rowIndex, 1);
            },

            applyQuote(lineIndex, rowIndex) {
                const line = this.lines[lineIndex];
                const row = line.rows[rowIndex];
                const quote = this.quoteFor(line, row);
                if (! quote) {
                    return;
                }
                if (row.unit_price === '' || row.unit_price === null || Number(row.unit_price) === 0) {
                    row.unit_price = quote.unit_price;
                }
                if (row.awarded_quantity_suom === '' || row.awarded_quantity_suom === null || Number(row.awarded_quantity_suom) === 0) {
                    const otherTotal = line.rows.reduce((sum, r) => {
                        if (r === row) {
                            return sum;
                        }
                        return sum + Number(r.awarded_quantity_suom || 0);
                    }, 0);
                    const rfqCap = Math.max(0, Number(line.rfq_qty || 0) - otherTotal);
                    const quotedQty = Number(quote.quoted_qty || 0);
                    if (quotedQty > 0) {
                        row.awarded_quantity_suom = rfqCap > 0
                            ? Math.min(quotedQty, rfqCap)
                            : quotedQty;
                    } else if (rfqCap > 0) {
                        row.awarded_quantity_suom = rfqCap;
                    }
                }
            },
        };
    }
</script>
@endif
