<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6" x-data="grnCreateForm(
    @js($itemUnits->pluck('name')->values()),
    @js($items->map(fn ($i) => [
        'id' => $i->id,
        'name' => $i->name,
        'code' => $i->code,
        'suom' => $i->itemUnit?->name,
        'default_price' => (float) ($i->default_price ?? 0),
    ])->values())
)">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('inventory.receive') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to Receive Goods</a>
        </div>

        <div class="md:flex md:items-center md:justify-between mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">New Goods Received Note</h2>
                <p class="mt-1 text-sm text-gray-500">Record incoming goods from a supplier. Stock updates after approvers sign off.</p>
            </div>
        </div>

        @include('inventory.partials.subnav')

        @if ($itemUnits->isEmpty())
            <div class="mt-4 bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded text-sm">
                Add item units for your organisation before creating a GRN (used for DUOM and SUOM on each line).
                <a href="{{ route('item-units.index') }}" class="font-medium underline hover:text-amber-950">Manage Item Units</a>
            </div>
        @endif

        @if ($errors->any())
            <div class="mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                <ul class="list-disc list-inside text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('inventory.receive.store') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
            @csrf

            <div class="bg-white shadow sm:rounded-lg p-6 space-y-5">
                <h3 class="text-lg font-medium text-gray-900 border-b border-gray-200 pb-3">Delivery note header</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label for="supplier_id" class="block text-sm font-medium text-gray-700">Supplier</label>
                        <select name="supplier_id" id="supplier_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            <option value="">— Select supplier —</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="store_id" class="block text-sm font-medium text-gray-700">Receiving store <span class="text-red-500">*</span></label>
                        <select name="store_id" id="store_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            <option value="">— Select store —</option>
                            @foreach($stores as $store)
                                <option value="{{ $store->id }}" @selected(old('store_id') == $store->id)>{{ $store->selectLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="date_of_order" class="block text-sm font-medium text-gray-700">Date of order <span class="text-red-500">*</span></label>
                        <input type="date" name="date_of_order" id="date_of_order" required
                               value="{{ old('date_of_order', now()->toDateString()) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                    </div>
                    <div>
                        <label for="date_of_delivery" class="block text-sm font-medium text-gray-700">Date of delivery <span class="text-red-500">*</span></label>
                        <input type="date" name="date_of_delivery" id="date_of_delivery" required
                               value="{{ old('date_of_delivery', now()->toDateString()) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Entry by</label>
                        <p class="mt-2 text-sm text-gray-900">{{ auth()->user()->name }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <span class="block text-sm font-medium text-gray-700">Attach Delivery note</span>
                        <div class="mt-1 flex flex-wrap items-center gap-3">
                            <label for="delivery_note"
                                   class="cursor-pointer inline-flex items-center px-4 py-2 rounded-md text-sm font-medium bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200">
                                Attach Delivery note
                            </label>
                            <input type="file" name="delivery_note" id="delivery_note" accept=".pdf,.jpg,.jpeg,.png"
                                   class="sr-only"
                                   @change="deliveryNoteName = $el.files[0]?.name ?? ''">
                            <span class="text-sm text-gray-500" x-text="deliveryNoteName || 'No file selected'"></span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">PDF or image, max 10 MB.</p>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="flex items-center justify-between border-b border-gray-200 pb-3 mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Line items</h3>
                    <button type="button" @click="addLine()"
                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        + Add line
                    </button>
                </div>

                <template x-if="lines.length === 0">
                    <p class="text-sm text-gray-500 py-6 text-center">Click “Add line” to add received items.</p>
                </template>

                <div class="space-y-4" x-show="lines.length > 0">
                    <div class="hidden lg:grid lg:grid-cols-12 gap-3 px-4 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <div class="lg:col-span-4">Item</div>
                        <div class="lg:col-span-1">Qty</div>
                        <div class="lg:col-span-2">Batch</div>
                        <div class="lg:col-span-2">Expiry</div>
                        <div class="lg:col-span-2">Purchase price</div>
                        <div class="lg:col-span-1"></div>
                    </div>

                    <template x-for="(line, index) in lines" :key="index">
                        <div class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                            <div class="px-4 py-3 space-y-3">
                                {{-- Row 1: item & receipt details --}}
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
                                    <div class="sm:col-span-2 lg:col-span-4">
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Item <span class="text-red-500">*</span></label>
                                        <select :name="'lines[' + index + '][item_id]'" x-model="line.item_id" @change="onItemChange(line, index)" required
                                                class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                            <option value="">Select item</option>
                                            <template x-for="item in items" :key="item.id">
                                                <option :value="item.id"
                                                        :disabled="isItemTaken(item.id, index)"
                                                        x-text="itemLabel(item, index)"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div class="lg:col-span-1">
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Qty <span class="text-red-500">*</span></label>
                                        <input type="number" step="0.0001" min="0.0001" :name="'lines[' + index + '][quantity]'" x-model.number="line.quantity" required
                                               class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    <div class="lg:col-span-2">
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Batch no.</label>
                                        <input type="text" :name="'lines[' + index + '][batch_number]'" x-model="line.batch_number"
                                               class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    <div class="lg:col-span-2">
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Expiry date</label>
                                        <input type="date" :name="'lines[' + index + '][expiry_date]'" x-model="line.expiry_date"
                                               class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    <div class="lg:col-span-2">
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Purchase price <span class="text-red-500">*</span></label>
                                        <input type="number" step="0.01" min="0" :name="'lines[' + index + '][purchase_price]'" x-model.number="line.purchase_price" required
                                               class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    <div class="lg:col-span-1 flex lg:justify-end">
                                        <button type="button" @click="removeLine(index)" x-show="lines.length > 1"
                                                class="text-sm text-red-600 hover:text-red-800 font-medium py-2">
                                            Remove
                                        </button>
                                    </div>
                                </div>

                                {{-- Row 2: units & stock calculation --}}
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end pt-3 border-t border-gray-100">
                                    <div class="lg:col-span-3">
                                        <label class="block text-xs font-medium text-gray-600 mb-1">DUOM <span class="text-red-500">*</span></label>
                                        <select :name="'lines[' + index + '][duom]'" x-model="line.duom" required
                                                class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500"
                                                :disabled="itemUnits.length === 0">
                                            <option value="">Select DUOM</option>
                                            <template x-for="name in itemUnits" :key="'duom-' + index + name">
                                                <option :value="name" x-text="name"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div class="lg:col-span-3">
                                        <label class="block text-xs font-medium text-gray-600 mb-1">SUOM (item unit) <span class="text-red-500">*</span></label>
                                        <select :name="'lines[' + index + '][suom]'" x-model="line.suom" required
                                                class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500"
                                                :disabled="itemUnits.length === 0">
                                            <option value="">Select SUOM</option>
                                            <template x-for="name in itemUnits" :key="'suom-' + index + name">
                                                <option :value="name" x-text="name"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div class="lg:col-span-3">
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Sale units per purchase <span class="text-red-500">*</span></label>
                                        <input type="number" step="0.0001" min="0.0001"
                                               :name="'lines[' + index + '][sale_units_per_purchase_unit]'" x-model.number="line.conversion" required
                                               placeholder="e.g. 30"
                                               class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    <div class="lg:col-span-3">
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Sale units to stock</label>
                                        <div class="flex h-[38px] items-center rounded-md border border-emerald-200 bg-emerald-50 px-3">
                                            <span class="text-lg font-semibold text-emerald-900 tabular-nums"
                                                  x-text="saleUnits(line).toLocaleString(undefined, {maximumFractionDigits: 4})"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('inventory.receive') }}" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</a>
                <button type="submit" name="action" value="draft" @disabled($itemUnits->isEmpty())
                        class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                    Save draft
                </button>
                <button type="submit" name="action" value="submit" @disabled($itemUnits->isEmpty())
                        class="px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    Submit for approval
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function grnCreateForm(itemUnits, items) {
    const blankLine = () => ({
        item_id: '', suom: '', duom: '', item_suom: '', quantity: 1, batch_number: '', expiry_date: '',
        purchase_price: 0, conversion: 1,
    });

    return {
        itemUnits,
        items,
        deliveryNoteName: '',
        lines: [blankLine()],
        addLine() {
            this.lines.push(blankLine());
        },
        removeLine(index) {
            this.lines.splice(index, 1);
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
            const item = this.items.find(i => String(i.id) === String(line.item_id));
            if (!item) {
                line.item_suom = '';
                return;
            }
            if (this.isItemTaken(item.id, index)) {
                line.item_id = '';
                line.item_suom = '';
                return;
            }
            line.item_suom = item.suom || '';
            if (item.suom && this.itemUnits.includes(item.suom)) {
                line.suom = item.suom;
                line.duom = item.suom;
            }
            line.purchase_price = item.default_price || 0;
        },
        saleUnits(line) {
            const q = parseFloat(line.quantity) || 0;
            const c = parseFloat(line.conversion) || 0;
            return Math.round(q * c * 10000) / 10000;
        },
    };
}
</script>
</x-app-layout>
