<section class="bg-white shadow sm:rounded-lg overflow-hidden border border-violet-100"
         x-data="rfqItemAwards(@js($awardForm))">
    <div class="px-5 py-4 border-b border-violet-100 bg-violet-50/50">
        <h3 class="text-sm font-semibold text-gray-900">Supplier selection per item</h3>
        <p class="text-xs text-gray-500 mt-0.5">
            Split each RFQ line across suppliers when needed. Award quantity can be less than the RFQ quantity (partial supply)
            or less than the supplier quote. Totals cannot exceed the RFQ line quantity.
        </p>
    </div>

    <form action="{{ route('inventory.orders.quotations.awards.store', $order) }}" method="POST" class="px-5 py-4 space-y-4">
        @csrf

        <template x-if="suppliers.length === 0">
            <p class="text-sm text-gray-500 py-4 text-center border border-dashed border-gray-200 rounded-lg">
                Record at least one supplier quotation before allocating items.
            </p>
        </template>

        <template x-for="(line, lineIndex) in lines" :key="line.order_line_id">
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-900" x-text="line.item_name"></p>
                        <p class="text-xs text-gray-500" x-show="line.item_code" x-text="line.item_code"></p>
                        <p class="text-xs text-slate-600 italic mt-1" x-show="line.analysis_comment" x-text="line.analysis_comment"></p>
                    </div>
                    <div class="text-right text-xs text-gray-600 space-y-0.5">
                        <p>RFQ qty: <span class="font-semibold tabular-nums" x-text="formatQty(line.rfq_qty)"></span></p>
                        <p>Allocated: <span class="font-semibold tabular-nums text-indigo-700" x-text="formatQty(allocatedTotal(line))"></span></p>
                        <p x-show="remainingQty(line) > 0">
                            Remaining: <span class="font-semibold tabular-nums text-amber-700" x-text="formatQty(remainingQty(line))"></span>
                        </p>
                        <p x-show="remainingQty(line) <= 0" class="text-emerald-700 font-medium">Fully allocated</p>
                    </div>
                </div>

                <div class="px-4 py-3 space-y-2">
                    <template x-if="line.rows.length === 0">
                        <p class="text-xs text-gray-500 italic">No supplier selected for this item yet.</p>
                    </template>

                    <template x-for="(row, rowIndex) in line.rows" :key="rowIndex">
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-end border-b border-gray-100 pb-2 last:border-0 last:pb-0">
                            <input type="hidden" :name="`awards[${fieldIndex(lineIndex, rowIndex)}][inventory_order_line_id]`" :value="line.order_line_id">

                            <div class="md:col-span-4">
                                <label class="block text-[10px] font-medium text-gray-500 uppercase tracking-wide">Supplier</label>
                                <select class="mt-0.5 block w-full rounded-md border-gray-300 text-sm shadow-sm"
                                        :name="`awards[${fieldIndex(lineIndex, rowIndex)}][supplier_id]`"
                                        x-model="row.supplier_id"
                                        @change="applyQuote(lineIndex, rowIndex)"
                                        required>
                                    <option value="">Select supplier…</option>
                                    <template x-for="supplier in suppliersForLine(line)" :key="supplier.supplier_id">
                                        <option :value="supplier.supplier_id" x-text="supplier.supplier_name"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-medium text-gray-500 uppercase tracking-wide">Award qty</label>
                                <input type="number" step="1" min="0"
                                       class="mt-0.5 block w-full rounded-md border-gray-300 text-sm text-right tabular-nums"
                                       :name="`awards[${fieldIndex(lineIndex, rowIndex)}][awarded_quantity_suom]`"
                                       x-model="row.awarded_quantity_suom"
                                       :max="maxAwardQty(line, row)"
                                       required>
                                <p class="text-[10px] text-gray-400 mt-0.5" x-show="quotedQty(line, row) !== null">
                                    Quoted: <span x-text="formatQty(quotedQty(line, row))"></span>
                                </p>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-medium text-gray-500 uppercase tracking-wide">Unit price</label>
                                <input type="number" step="0.01" min="0"
                                       class="mt-0.5 block w-full rounded-md border-gray-300 text-sm text-right tabular-nums"
                                       :name="`awards[${fieldIndex(lineIndex, rowIndex)}][unit_price]`"
                                       x-model="row.unit_price"
                                       required>
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

                    <button type="button"
                            @click="addRow(lineIndex)"
                            class="text-xs font-medium text-violet-700 hover:text-violet-900"
                            x-show="suppliersForLine(line).length > 0">
                        + Add supplier for this item
                    </button>
                </div>
            </div>
        </template>

        <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-gray-100" x-show="suppliers.length > 0">
            <button type="submit"
                    class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-white bg-violet-600 hover:bg-violet-700">
                Save supplier selections
            </button>
            <p class="text-xs text-gray-500">Then use <strong>Generate LPOs from selections</strong> above to create one LPO per supplier.</p>
        </div>
    </form>
</section>

<script>
    function rfqItemAwards(initial) {
        const quoteLookup = initial.quote_lookup || {};

        return {
            suppliers: initial.suppliers || [],
            lines: (initial.lines || []).map((line) => ({
                ...line,
                rows: (line.awards && line.awards.length > 0)
                    ? line.awards.map((award) => ({
                        supplier_id: String(award.supplier_id),
                        awarded_quantity_suom: award.awarded_quantity_suom,
                        unit_price: award.unit_price,
                    }))
                    : [],
            })),

            fieldIndex(lineIndex, rowIndex) {
                let index = 0;
                for (let i = 0; i < lineIndex; i++) {
                    index += this.lines[i].rows.length;
                }
                return index + rowIndex;
            },

            formatQty(value) {
                return Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 0 });
            },

            formatMoney(value) {
                return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },

            quoteFor(line, row) {
                if (! row.supplier_id) {
                    return null;
                }
                const lineQuotes = quoteLookup[line.order_line_id] || quoteLookup[String(line.order_line_id)] || {};
                return lineQuotes[row.supplier_id] || lineQuotes[String(row.supplier_id)] || null;
            },

            quotedQty(line, row) {
                const quote = this.quoteFor(line, row);
                return quote ? quote.quoted_qty : null;
            },

            allocatedTotal(line) {
                return line.rows.reduce((sum, row) => sum + Number(row.awarded_quantity_suom || 0), 0);
            },

            remainingQty(line) {
                return Math.max(0, Number(line.rfq_qty || 0) - this.allocatedTotal(line));
            },

            maxAwardQty(line, row) {
                const quote = this.quoteFor(line, row);
                const otherTotal = line.rows.reduce((sum, r) => {
                    if (r === row) {
                        return sum;
                    }
                    return sum + Number(r.awarded_quantity_suom || 0);
                }, 0);
                const rfqCap = Math.max(0, Number(line.rfq_qty || 0) - otherTotal);
                const quoteCap = quote ? Number(quote.quoted_qty || 0) : rfqCap;
                return Math.min(rfqCap, quoteCap);
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
                const defaultQty = remaining > 0 ? remaining : '';
                line.rows.push({
                    supplier_id: '',
                    awarded_quantity_suom: defaultQty,
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
                if (! row.unit_price || Number(row.unit_price) === 0) {
                    row.unit_price = quote.unit_price;
                }
                if (! row.awarded_quantity_suom || Number(row.awarded_quantity_suom) === 0) {
                    const remaining = this.remainingQty(line) + Number(row.awarded_quantity_suom || 0);
                    row.awarded_quantity_suom = Math.min(Number(quote.quoted_qty || 0), remaining);
                }
            },
        };
    }
</script>
