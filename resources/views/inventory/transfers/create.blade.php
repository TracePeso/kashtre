<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6" x-data="transferCreateForm(
    @js($items->map(fn ($i) => [
        'id' => $i->id,
        'name' => $i->name,
        'code' => $i->code,
        'suom' => $i->itemUnit?->name,
    ])->values()),
    @js($stockByStore),
    @js($storesList),
    @js(old('from_store_id')),
    @js(old('to_store_id')),
    @js(collect(old('lines', []))->map(fn ($line) => [
        'item_id' => (string) ($line['item_id'] ?? ''),
        'quantity_suom' => (string) ($line['quantity_suom'] ?? ''),
    ])->values()->all())
)">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <a href="{{ route('inventory.transfers.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to transfers</a>
        <h2 class="mt-4 text-2xl font-bold text-gray-900">Make a transfer request</h2>
        <p class="mt-1 text-sm text-gray-500">Request stock between a store and its parent distribution store. Each transfer is submitted for approval before stock moves.</p>

        <div class="mt-4 rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-600">
            <p class="font-medium text-gray-900">How transfers work</p>
            <ol class="mt-2 list-decimal list-inside space-y-1">
                <li>Create and submit a transfer request with the items and quantities needed.</li>
                <li>The dispatch store approves — stock is deducted from that store.</li>
                <li>Goods are physically moved to the receiving store.</li>
                <li>The receiving store confirms receipt — stock is added there.</li>
            </ol>
        </div>

        @include('inventory.partials.subnav')

        @if ($errors->any())
            <div class="mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded text-sm">
                <ul class="list-disc list-inside">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('inventory.transfers.store') }}" class="mt-6 space-y-6" @submit="onSubmit">
            @csrf
            <div class="bg-white shadow sm:rounded-lg p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Dispatch store (from) <span class="text-red-500">*</span></label>
                    <select name="from_store_id" required x-model="fromStoreId" @change="onFromStoreChange()"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select dispatch store</option>
                        @foreach($stores as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Receiving store (to) <span class="text-red-500">*</span></label>
                    <select name="to_store_id" required x-model="toStoreId" @change="onToStoreChange()"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500"
                            :disabled="!fromStoreId">
                        <option value="">Select receiving store</option>
                        <template x-for="store in availableToStores()" :key="'to-' + store.id">
                            <option :value="String(store.id)" x-text="store.label"></option>
                        </template>
                    </select>
                    <p class="mt-1 text-xs text-gray-500" x-show="fromStoreId && availableToStores().length === 0" x-cloak>
                        No valid receiving stores for the selected dispatch store.
                    </p>
                </div>
                <div class="md:col-span-2" x-show="fromStoreId && toStoreId && !storesAreSame() && !transferIsAllowed()" x-cloak>
                    <p class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-md px-4 py-3">
                        Child stores cannot transfer stock directly to other child stores. Move stock through the parent distribution store first.
                    </p>
                </div>
                <div class="md:col-span-2" x-show="storesAreSame()" x-cloak>
                    <p class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-md px-4 py-3">
                        Dispatch and receiving stores must be different.
                    </p>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea name="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-3 mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Items to transfer</h3>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="loadStockedItems()"
                                :disabled="!fromStoreId || !toStoreId || storesAreSame() || !transferIsAllowed()"
                                class="inline-flex items-center px-3 py-1.5 border border-blue-200 rounded-md text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 disabled:opacity-50 disabled:cursor-not-allowed">
                            Load stocked items
                        </button>
                        <button type="button" @click="addLine()"
                                :disabled="!fromStoreId || !toStoreId || storesAreSame() || !transferIsAllowed()"
                                class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            + Add line
                        </button>
                    </div>
                </div>

                <p class="text-xs text-gray-500 mb-4" x-show="fromStoreId && toStoreId && transferIsAllowed() && !storesAreSame()">
                    <span x-text="stockedCount()"></span> item(s) with system stock at the dispatch store.
                    Select items from the dropdown — only goods stocked at the dispatch store are listed.
                </p>

                <template x-if="!fromStoreId || !toStoreId || storesAreSame() || !transferIsAllowed()">
                    <p class="text-sm text-gray-500 py-8 text-center">Select valid dispatch and receiving stores, then add transfer lines.</p>
                </template>

                <div class="overflow-x-auto" x-show="fromStoreId && toStoreId && !storesAreSame() && transferIsAllowed() && lines.length > 0">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-600 w-[45%]">Item</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600 w-[12%]">SUOM</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600 w-[12%]">System stock</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600 w-[18%]">Qty to transfer</th>
                                <th class="px-3 py-2 w-[8%]"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <template x-for="(line, index) in lines" :key="index">
                                <tr>
                                    <td class="px-3 py-2 align-top">
                                        <select :name="'lines[' + index + '][item_id]'" x-model="line.item_id"
                                                @change="onItemChange(line, index)" required
                                                class="transfer-item-select block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                            <option value="">Select item</option>
                                            <template x-for="row in dispatchStock()" :key="row.item_id + '-' + index + '-' + takenItemsKey()">
                                                <option :value="String(row.item_id)"
                                                        :disabled="isItemTakenByOtherLine(row.item_id, index)"
                                                        :class="isItemTakenByOtherLine(row.item_id, index) ? 'text-gray-400' : ''"
                                                        x-text="itemLabel(row, index)"></option>
                                            </template>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2 align-middle text-gray-600" x-text="itemSuom(line) || '—'"></td>
                                    <td class="px-3 py-2 align-middle text-right tabular-nums text-gray-500" x-text="systemQty(line)"></td>
                                    <td class="px-3 py-2 align-top">
                                        <input type="number" step="1" min="1"
                                               :max="maxQty(line)"
                                               :name="'lines[' + index + '][quantity_suom]'" x-model="line.quantity_suom" required
                                               class="block w-full rounded-md border-gray-300 shadow-sm text-sm text-right focus:border-blue-500 focus:ring-blue-500">
                                    </td>
                                    <td class="px-3 py-2 align-middle text-right">
                                        <button type="button" @click="removeLine(index)" class="text-sm text-red-600 hover:text-red-800 font-medium">Remove</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('inventory.transfers.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-sm bg-white">Cancel</a>
                <button type="submit"
                        :disabled="!canSubmit()"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                    Create request
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function transferCreateForm(items, stockByStore, storesList, initialFrom, initialTo, initialLines) {
    const blankLine = () => ({ item_id: '', quantity_suom: '' });

    const normalizeLines = (rows) => {
        if (!rows || rows.length === 0) {
            return [blankLine()];
        }

        return rows.map(row => ({
            item_id: String(row.item_id ?? ''),
            quantity_suom: String(row.quantity_suom ?? ''),
        }));
    };

    return {
        items,
        stockByStore,
        storesList,
        fromStoreId: initialFrom ? String(initialFrom) : '',
        toStoreId: initialTo ? String(initialTo) : '',
        lines: normalizeLines(initialLines),
        storeById(id) {
            const key = String(id);
            return this.storesList.find(store => String(store.id) === key) || null;
        },
        canTransferTo(fromId, toId) {
            if (!fromId || !toId || String(fromId) === String(toId)) {
                return false;
            }

            const from = this.storeById(fromId);
            const to = this.storeById(toId);

            if (!from || !to) {
                return false;
            }

            if (from.parent_id !== null && Number(from.parent_id) === Number(to.id)) {
                return true;
            }

            if (to.parent_id !== null && Number(to.parent_id) === Number(from.id)) {
                return true;
            }

            if ((from.parent_id === null || from.parent_id === '') && (to.parent_id === null || to.parent_id === '')) {
                return true;
            }

            return false;
        },
        availableToStores() {
            if (!this.fromStoreId) {
                return [];
            }

            return this.storesList.filter(store => this.canTransferTo(this.fromStoreId, store.id));
        },
        transferIsAllowed() {
            return this.canTransferTo(this.fromStoreId, this.toStoreId);
        },
        storesAreSame() {
            return this.fromStoreId && this.toStoreId && String(this.fromStoreId) === String(this.toStoreId);
        },
        onFromStoreChange() {
            if (this.storesAreSame() || (this.toStoreId && !this.transferIsAllowed())) {
                this.toStoreId = '';
            }
            this.pruneInvalidLines();
        },
        onToStoreChange() {
            if (this.storesAreSame() || !this.transferIsAllowed()) {
                this.toStoreId = '';
            }
        },
        dispatchStock() {
            if (!this.fromStoreId) return [];
            const key = String(this.fromStoreId);
            return this.stockByStore[key] || this.stockByStore[Number(key)] || [];
        },
        stockedCount() {
            return this.dispatchStock().length;
        },
        addLine() {
            this.lines.push(blankLine());
        },
        removeLine(index) {
            this.lines.splice(index, 1);
        },
        loadStockedItems() {
            const stocked = this.dispatchStock();
            if (stocked.length === 0) {
                alert('No items with system stock at the dispatch store.');
                return;
            }

            const existing = new Set(this.lines.map(l => String(l.item_id)).filter(Boolean));
            let added = 0;

            stocked.forEach(row => {
                const id = String(row.item_id);
                if (existing.has(id)) return;
                this.lines.push({ item_id: id, quantity_suom: '' });
                existing.add(id);
                added++;
            });

            if (added === 0) {
                this.lines = stocked.map(row => ({
                    item_id: String(row.item_id),
                    quantity_suom: '',
                }));
            }
        },
        pruneInvalidLines() {
            const validIds = new Set(this.dispatchStock().map(r => String(r.item_id)));
            this.lines = this.lines.filter(line => !line.item_id || validIds.has(String(line.item_id)));
            if (this.lines.length === 0) {
                this.lines = [blankLine()];
            }
        },
        isItemTakenByOtherLine(itemId, currentIndex) {
            const id = String(itemId);
            if (!id) return false;
            return this.lines.some((line, i) => i !== currentIndex && String(line.item_id) === id);
        },
        takenItemsKey() {
            return this.lines.map(line => String(line.item_id || '')).join('|');
        },
        isItemTaken(itemId, currentIndex) {
            return this.isItemTakenByOtherLine(itemId, currentIndex);
        },
        itemLabel(row, currentIndex) {
            const label = row.name + (row.code ? ' (' + row.code + ')' : '');
            return this.isItemTakenByOtherLine(row.item_id, currentIndex) ? label + ' — already added' : label;
        },
        onItemChange(line, index) {
            if (line.item_id && this.isItemTakenByOtherLine(line.item_id, index)) {
                line.item_id = '';
            }
        },
        itemSuom(line) {
            const row = this.dispatchStock().find(r => String(r.item_id) === String(line.item_id));
            return row?.suom || this.items.find(i => String(i.id) === String(line.item_id))?.suom || '';
        },
        systemQty(line) {
            if (!line.item_id) return '—';
            const row = this.dispatchStock().find(r => String(r.item_id) === String(line.item_id));
            if (!row) return '—';
            return Number(row.system_qty).toLocaleString(undefined, { maximumFractionDigits: 0 });
        },
        maxQty(line) {
            const row = this.dispatchStock().find(r => String(r.item_id) === String(line.item_id));
            return row ? row.system_qty : undefined;
        },
        canSubmit() {
            if (!this.fromStoreId || !this.toStoreId || this.storesAreSame() || !this.transferIsAllowed()) return false;
            return this.lines.some(line => line.item_id && parseFloat(line.quantity_suom) > 0);
        },
        onSubmit(event) {
            if (this.storesAreSame()) {
                event.preventDefault();
                alert('Dispatch and receiving stores must be different.');
                return;
            }
            if (!this.transferIsAllowed()) {
                event.preventDefault();
                alert('Child stores cannot transfer stock directly to other child stores. Move stock through the parent distribution store first.');
                return;
            }
            if (!this.canSubmit()) {
                event.preventDefault();
                alert('Add at least one item with a quantity greater than zero.');
            }
        },
    };
}
</script>
<style>
    .transfer-item-select option:disabled {
        color: #9ca3af;
        background-color: #f3f4f6;
    }
</style>
</x-app-layout>
