<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6" x-data="consumptionCreateForm(
    @js($items->map(fn ($i) => [
        'id' => $i->id,
        'name' => $i->name,
        'code' => $i->code,
        'suom' => $i->itemUnit?->name,
    ])->values()),
    @js($stockByStore),
    @js(old('store_id')),
    @js(collect(old('lines', []))->map(fn ($line) => [
        'item_id' => (string) ($line['item_id'] ?? ''),
        'quantity_suom' => (string) ($line['quantity_suom'] ?? ''),
    ])->values()->all())
)">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('inventory.consumption.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to consumption log</a>
        </div>

        <div class="md:flex md:items-center md:justify-between mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Record daily consumption</h2>
                <p class="mt-1 text-sm text-gray-500">Enter usage for many items in one go. Each line updates system stock and moving averages.</p>
            </div>
        </div>

        @include('inventory.partials.subnav')

        @if ($errors->any())
            <div class="mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                <ul class="list-disc list-inside text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('inventory.consumption.store') }}" class="mt-6 space-y-6">
            @csrf

            <div class="bg-white shadow sm:rounded-lg p-6 space-y-5">
                <h3 class="text-lg font-medium text-gray-900 border-b border-gray-200 pb-3">Header</h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div>
                        <label for="store_id" class="block text-sm font-medium text-gray-700">Store <span class="text-red-500">*</span></label>
                        <select name="store_id" id="store_id" required x-model="storeId"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <option value="">Select store</option>
                            @foreach($stores as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="consumption_date" class="block text-sm font-medium text-gray-700">Date <span class="text-red-500">*</span></label>
                        <input type="date" name="consumption_date" id="consumption_date" required
                               value="{{ old('consumption_date', now()->toDateString()) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    </div>
                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-700">Notes (optional)</label>
                        <input type="text" name="notes" id="notes" value="{{ old('notes') }}"
                               placeholder="e.g. Ward A morning issue"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-3 mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Consumption lines</h3>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="loadStockedItems()"
                                :disabled="!storeId"
                                class="inline-flex items-center px-3 py-1.5 border border-blue-200 rounded-md text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 disabled:opacity-50 disabled:cursor-not-allowed">
                            Load stocked items
                        </button>
                        <button type="button" @click="addLine()"
                                class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            + Add line
                        </button>
                    </div>
                </div>

                <p class="text-xs text-gray-500 mb-4" x-show="storeId">
                    <span x-text="stockedCount()"></span> item(s) with system stock in this store.
                    Use <strong>Load stocked items</strong> to add them all, then fill quantities only where consumption occurred.
                </p>

                <template x-if="lines.length === 0">
                    <p class="text-sm text-gray-500 py-8 text-center">Select a store, then add lines or load stocked items.</p>
                </template>

                <div class="overflow-x-auto" x-show="lines.length > 0">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-600 w-[45%]">Item</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600 w-[12%]">SUOM</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600 w-[12%]">System stock</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600 w-[18%]">Qty consumed</th>
                                <th class="px-3 py-2 w-[8%]"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <template x-for="(line, index) in lines" :key="index">
                                <tr>
                                    <td class="px-3 py-2 align-top">
                                        <select :name="'lines[' + index + '][item_id]'" x-model="line.item_id" @change="onItemChange(line, index)" required
                                                class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                            <option value="">Select item</option>
                                            <template x-for="item in items" :key="item.id">
                                                <option :value="String(item.id)"
                                                        :disabled="isItemTaken(item.id, index)"
                                                        x-text="itemLabel(item, index)"></option>
                                            </template>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2 align-middle text-gray-600" x-text="itemSuom(line) || '—'"></td>
                                    <td class="px-3 py-2 align-middle text-right tabular-nums text-gray-500" x-text="systemQty(line)"></td>
                                    <td class="px-3 py-2 align-top">
                                        <input type="number" step="0.0001" min="0"
                                               :name="'lines[' + index + '][quantity_suom]'" x-model="line.quantity_suom"
                                               placeholder="0"
                                               class="block w-full rounded-md border-gray-300 shadow-sm text-sm text-right focus:border-blue-500 focus:ring-blue-500">
                                    </td>
                                    <td class="px-3 py-2 align-middle text-right">
                                        <button type="button" @click="removeLine(index)"
                                                class="text-sm text-red-600 hover:text-red-800 font-medium">
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <p class="mt-4 text-xs text-gray-500" x-show="lines.length > 0">
                    Each item can appear once. Leave quantity blank on lines with no consumption — only filled rows are saved.
                </p>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('inventory.consumption.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</a>
                <button type="submit" :disabled="lines.length === 0"
                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    Save all lines
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function consumptionCreateForm(items, stockByStore, initialStoreId, initialLines) {
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
        storeId: initialStoreId ? String(initialStoreId) : '',
        lines: normalizeLines(initialLines),
        addLine() {
            this.lines.push(blankLine());
        },
        removeLine(index) {
            this.lines.splice(index, 1);
        },
        stockedCount() {
            if (!this.storeId) return 0;
            return (this.stockByStore[this.storeId] || []).length;
        },
        loadStockedItems() {
            if (!this.storeId) return;

            const stocked = this.stockByStore[this.storeId] || [];
            const existing = new Set(
                this.lines.map(line => String(line.item_id)).filter(Boolean)
            );

            let added = 0;

            stocked.forEach(row => {
                const id = String(row.item_id);
                if (existing.has(id)) return;
                this.lines.push({ item_id: id, quantity_suom: '' });
                existing.add(id);
                added++;
            });

            if (added === 0 && this.lines.length === 1 && !this.lines[0].item_id && stocked.length > 0) {
                this.lines = stocked.map(row => ({
                    item_id: String(row.item_id),
                    quantity_suom: '',
                }));
                added = stocked.length;
            }

            if (added === 0 && stocked.length === 0) {
                alert('No items with system stock in this store.');
            }
        },
        isItemTaken(itemId, currentIndex) {
            const id = String(itemId);
            if (!id) return false;
            return this.lines.some((line, i) => i !== currentIndex && String(line.item_id) === id);
        },
        itemLabel(item, currentIndex) {
            const label = item.name + (item.code ? ' (' + item.code + ')' : '');
            return this.isItemTaken(item.id, currentIndex) ? label + ' — already added' : label;
        },
        onItemChange(line, index) {
            if (line.item_id && this.isItemTaken(line.item_id, index)) {
                line.item_id = '';
            }
        },
        itemSuom(line) {
            const item = this.items.find(i => String(i.id) === String(line.item_id));
            return item?.suom || '';
        },
        systemQty(line) {
            if (!this.storeId || !line.item_id) return '—';
            const stocked = (this.stockByStore[this.storeId] || []).find(
                row => String(row.item_id) === String(line.item_id)
            );
            if (!stocked) return '—';
            return Number(stocked.system_qty).toLocaleString(undefined, { maximumFractionDigits: 0 });
        },
    };
}
</script>
</x-app-layout>
