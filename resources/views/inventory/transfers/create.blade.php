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
        <p class="mt-1 text-sm text-gray-500">Request stock along the store hierarchy. End Stores cannot transfer directly to other End Stores — use a Distribution hub (or transfer to a Satellite under the same End Store).</p>

        <div class="mt-4 rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-600">
            <p class="font-medium text-gray-900">How transfers work</p>
            <ol class="mt-2 list-decimal list-inside space-y-1">
                <li>Create and submit a transfer request with the items and quantities needed.</li>
                <li>Configured approvers sign off in order — stock is moved to in-transit at the dispatch store after the last approval.</li>
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
                    <p class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-md px-4 py-3" x-text="transferDenialMessage()"></p>
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
                <div class="flex items-center justify-between border-b border-gray-200 pb-3 mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Line items</h3>
                </div>

                <template x-if="!fromStoreId || !toStoreId || storesAreSame() || !transferIsAllowed()">
                    <p class="text-sm text-gray-500 py-6 text-center">Select valid dispatch and receiving stores, then add items below.</p>
                </template>

                <div x-show="fromStoreId && toStoreId && !storesAreSame() && transferIsAllowed()" x-cloak>
                    {{-- Summary of added lines (like receive goods) --}}
                    <div x-show="lines.length > 0" x-cloak
                         class="mb-6 rounded-lg border border-blue-200 bg-blue-50/60 overflow-hidden">
                        <div class="px-4 py-3 border-b border-blue-200/80 flex flex-wrap items-center justify-between gap-2">
                            <h4 class="text-sm font-semibold text-blue-900">
                                Summary
                                <span class="font-normal text-blue-700" x-text="'(' + lines.length + ' item' + (lines.length === 1 ? '' : 's') + ')'"></span>
                            </h4>
                            <p class="text-sm text-blue-800">
                                <span x-text="stockedCount()"></span> item(s) with stock at dispatch
                            </p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-blue-100/50 text-xs tracking-wide text-blue-800">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium">#</th>
                                        <th class="px-4 py-2 text-left font-medium">Item</th>
                                        <th class="px-4 py-2 text-left font-medium">Sale unit</th>
                                        <th class="px-4 py-2 text-right font-medium">System stock</th>
                                        <th class="px-4 py-2 text-right font-medium">Qty to transfer</th>
                                        <th class="px-4 py-2 text-right font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-blue-100 bg-white/80">
                                    <template x-for="(line, index) in lines" :key="'summary-' + index + '-' + line.item_id">
                                        <tr class="hover:bg-blue-50/50" :class="editingIndex === index ? 'ring-2 ring-inset ring-blue-400' : ''">
                                            <td class="px-4 py-2 text-gray-500 tabular-nums" x-text="index + 1"></td>
                                            <td class="px-4 py-2">
                                                <div class="font-medium text-gray-900" x-text="itemName(line)"></div>
                                                <div class="text-xs text-gray-500" x-text="itemCode(line)"></div>
                                            </td>
                                            <td class="px-4 py-2 text-gray-700" x-text="itemSuom(line) || '—'"></td>
                                            <td class="px-4 py-2 text-right tabular-nums text-gray-500" x-text="systemQty(line)"></td>
                                            <td class="px-4 py-2 text-right tabular-nums font-medium text-gray-900" x-text="line.quantity_suom"></td>
                                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                                <button type="button" @click="editLine(index)"
                                                        class="text-xs font-medium text-blue-600 hover:text-blue-800">Edit</button>
                                                <span class="text-gray-300 mx-1">|</span>
                                                <button type="button" @click="removeLine(index)"
                                                        class="text-xs font-medium text-red-600 hover:text-red-800">Delete</button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <p x-show="lines.length === 0" class="mb-4 text-sm text-gray-500 text-center py-2">No items yet. Add an item below.</p>

                    {{-- Single add/edit draft (like receive goods) --}}
                    <div class="border border-gray-200 rounded-lg bg-gray-50/50 overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-200 bg-white flex flex-wrap items-center justify-between gap-2">
                            <h4 class="text-sm font-semibold text-gray-900" x-text="editingIndex !== null ? 'Edit item' : 'Add item'"></h4>
                            <button type="button" x-show="editingIndex !== null" @click="cancelEdit()" x-cloak
                                    class="text-xs font-medium text-gray-600 hover:text-gray-800">Cancel edit</button>
                        </div>
                        <div class="px-4 py-4 space-y-3 bg-white">
                            <p class="text-xs text-gray-500" x-show="stockedCount() === 0">
                                No goods with system stock at the dispatch store. Receive goods into that store first, then transfer to the End Store.
                            </p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
                                <div class="sm:col-span-2 lg:col-span-6">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Item <span class="text-red-500">*</span></label>
                                    <select x-model="draftLine.item_id" @change="onDraftItemChange()"
                                            class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500"
                                            :disabled="stockedCount() === 0">
                                        <option value="">Select item</option>
                                        <template x-for="row in dispatchStock()" :key="'draft-' + row.item_id">
                                            <option :value="String(row.item_id)"
                                                    :disabled="isItemTaken(row.item_id)"
                                                    x-text="stockItemLabel(row)"></option>
                                        </template>
                                    </select>
                                </div>
                                <div class="lg:col-span-2">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Sale unit</label>
                                    <div class="flex h-[38px] items-center rounded-md border border-gray-200 bg-gray-50 px-3 text-sm text-gray-800">
                                        <span x-text="draftSuom() || '—'"></span>
                                    </div>
                                </div>
                                <div class="lg:col-span-2">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">System stock</label>
                                    <div class="flex h-[38px] items-center justify-end rounded-md border border-gray-200 bg-gray-50 px-3 text-sm tabular-nums text-gray-800">
                                        <span x-text="draftSystemQty()"></span>
                                    </div>
                                </div>
                                <div class="lg:col-span-2">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Qty to transfer <span class="text-red-500">*</span></label>
                                    <input type="number" step="1" min="1" :max="draftMaxQty()"
                                           x-model="draftLine.quantity_suom"
                                           class="block w-full rounded-md border-gray-300 shadow-sm text-sm text-right focus:border-blue-500 focus:ring-blue-500"
                                           :disabled="!draftLine.item_id">
                                </div>
                            </div>

                            <div x-show="draftError" x-cloak class="rounded-md bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-800" x-text="draftError"></div>

                            <div class="flex justify-end pt-1">
                                <button type="button" @click="saveDraftLine()"
                                        :disabled="stockedCount() === 0"
                                        class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span x-text="editingIndex !== null ? 'Update item' : 'Add item'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <template x-for="(line, index) in lines" :key="'post-' + index + '-' + line.item_id">
                    <div class="hidden" aria-hidden="true">
                        <input type="hidden" :name="'lines[' + index + '][item_id]'" :value="line.item_id">
                        <input type="hidden" :name="'lines[' + index + '][quantity_suom]'" :value="line.quantity_suom">
                    </div>
                </template>
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
            return [];
        }

        return rows
            .filter(row => row.item_id && parseFloat(row.quantity_suom) > 0)
            .map(row => ({
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
        draftLine: blankLine(),
        editingIndex: null,
        draftError: '',
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

            const fromType = from.distribution_type || 'end_store';
            const toType = to.distribution_type || 'end_store';

            if (fromType === 'end_store' && toType === 'end_store') {
                return false;
            }

            if (from.parent_id !== null && Number(from.parent_id) === Number(to.id)) {
                return true;
            }

            if (to.parent_id !== null && Number(to.parent_id) === Number(from.id)) {
                return true;
            }

            const fromIsRoot = from.parent_id === null || from.parent_id === '';
            const toIsRoot = to.parent_id === null || to.parent_id === '';
            if (fromIsRoot && toIsRoot
                && fromType === 'interim_distribution'
                && toType === 'interim_distribution') {
                return true;
            }

            return false;
        },
        transferDenialMessage() {
            const from = this.storeById(this.fromStoreId);
            const to = this.storeById(this.toStoreId);
            if (!from || !to) {
                return 'This store pair is not allowed for stock transfer.';
            }
            if ((from.distribution_type || 'end_store') === 'end_store'
                && (to.distribution_type || 'end_store') === 'end_store') {
                return 'End Stores cannot transfer stock directly to other End Stores. Move stock through a Distribution store first (or to a Satellite under the same End Store).';
            }
            return 'Stock can only move between a store and its parent/child in the hierarchy (or between Distribution hubs).';
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
            this.resetDraft();
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
        stockRow(itemId) {
            return this.dispatchStock().find(r => String(r.item_id) === String(itemId)) || null;
        },
        catalogItem(itemId) {
            return this.items.find(i => String(i.id) === String(itemId)) || null;
        },
        isItemTaken(itemId) {
            const id = String(itemId);
            if (!id) return false;
            return this.lines.some((line, i) => {
                if (this.editingIndex !== null && i === this.editingIndex) {
                    return false;
                }
                return String(line.item_id) === id;
            });
        },
        stockItemLabel(row) {
            const label = row.name + (row.code ? ' (' + row.code + ')' : '') + ' — stock ' + Number(row.system_qty).toLocaleString();
            return this.isItemTaken(row.item_id) ? label + ' — already added' : label;
        },
        itemName(line) {
            return this.stockRow(line.item_id)?.name || this.catalogItem(line.item_id)?.name || '—';
        },
        itemCode(line) {
            return this.stockRow(line.item_id)?.code || this.catalogItem(line.item_id)?.code || '';
        },
        itemSuom(line) {
            return this.stockRow(line.item_id)?.suom || this.catalogItem(line.item_id)?.suom || '';
        },
        systemQty(line) {
            const row = this.stockRow(line.item_id);
            if (!row) return '—';
            return Number(row.system_qty).toLocaleString(undefined, { maximumFractionDigits: 0 });
        },
        draftSuom() {
            return this.itemSuom(this.draftLine);
        },
        draftSystemQty() {
            if (!this.draftLine.item_id) return '—';
            return this.systemQty(this.draftLine);
        },
        draftMaxQty() {
            const row = this.stockRow(this.draftLine.item_id);
            return row ? row.system_qty : undefined;
        },
        onDraftItemChange() {
            this.draftError = '';
            if (this.draftLine.item_id && this.isItemTaken(this.draftLine.item_id)) {
                this.draftError = 'That item is already on this transfer.';
                this.draftLine.item_id = '';
            }
        },
        resetDraft() {
            this.draftLine = blankLine();
            this.editingIndex = null;
            this.draftError = '';
        },
        cancelEdit() {
            this.resetDraft();
        },
        editLine(index) {
            const line = this.lines[index];
            if (!line) return;
            this.editingIndex = index;
            this.draftLine = {
                item_id: String(line.item_id),
                quantity_suom: String(line.quantity_suom),
            };
            this.draftError = '';
        },
        removeLine(index) {
            this.lines.splice(index, 1);
            if (this.editingIndex === index) {
                this.resetDraft();
            } else if (this.editingIndex !== null && this.editingIndex > index) {
                this.editingIndex -= 1;
            }
        },
        saveDraftLine() {
            this.draftError = '';

            if (!this.draftLine.item_id) {
                this.draftError = 'Select an item.';
                return;
            }

            if (this.isItemTaken(this.draftLine.item_id)) {
                this.draftError = 'That item is already on this transfer.';
                return;
            }

            const qty = parseFloat(this.draftLine.quantity_suom);
            if (!qty || qty <= 0) {
                this.draftError = 'Enter a quantity greater than zero.';
                return;
            }

            const max = this.draftMaxQty();
            if (max !== undefined && qty > Number(max)) {
                this.draftError = 'Quantity cannot exceed system stock (' + Number(max).toLocaleString() + ').';
                return;
            }

            const saved = {
                item_id: String(this.draftLine.item_id),
                quantity_suom: String(qty),
            };

            if (this.editingIndex !== null) {
                this.lines.splice(this.editingIndex, 1, saved);
            } else {
                this.lines.push(saved);
            }

            this.resetDraft();
        },
        pruneInvalidLines() {
            const validIds = new Set(this.dispatchStock().map(r => String(r.item_id)));
            this.lines = this.lines.filter(line => validIds.has(String(line.item_id)));
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
                alert(this.transferDenialMessage());
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
</x-app-layout>
