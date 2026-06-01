<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6" x-data="grnCreateForm(
    @js($duoms->pluck('name')->values()),
    @js($suoms->pluck('name')->values()),
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

        @if ($duoms->isEmpty())
            <div class="mt-4 bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded text-sm">
                Add at least one dispensing unit of measure before creating a GRN.
                <a href="{{ route('inventory.duoms.index') }}" class="font-medium underline hover:text-amber-950">Manage DUOM</a>
            </div>
        @endif

        @if ($suoms->isEmpty())
            <div class="mt-4 bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded text-sm">
                Add sale units of measure (SUOM) before creating a GRN.
                <a href="{{ route('inventory.suoms.index') }}" class="font-medium underline hover:text-amber-950">Manage SUOM</a>
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
                                <option value="{{ $store->id }}" @selected(old('store_id') == $store->id)>{{ $store->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="date_of_order" class="block text-sm font-medium text-gray-700">Date of order <span class="text-red-500">*</span></label>
                        <input type="date" name="date_of_order" id="date_of_order" x-model="dateOfOrder" required
                               value="{{ old('date_of_order', now()->toDateString()) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                    </div>
                    <div>
                        <label for="date_of_delivery" class="block text-sm font-medium text-gray-700">Date of delivery <span class="text-red-500">*</span></label>
                        <input type="date" name="date_of_delivery" id="date_of_delivery" x-model="dateOfDelivery" required
                               value="{{ old('date_of_delivery', now()->toDateString()) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Entry by</label>
                        <p class="mt-2 text-sm text-gray-900">{{ auth()->user()->name }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <label for="delivery_note" class="block text-sm font-medium text-gray-700">Supplier delivery note (attachment)</label>
                        <input type="file" name="delivery_note" id="delivery_note" accept=".pdf,.jpg,.jpeg,.png"
                               class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
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

                <div class="mb-6 rounded-lg bg-slate-50 border border-slate-200 px-4 py-3 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium uppercase tracking-wide text-slate-500">Lead time (days)</label>
                        <p class="mt-1 text-lg font-semibold text-slate-900 tabular-nums" x-text="leadTimeDays"></p>
                        <p class="mt-0.5 text-xs text-slate-500">Date of delivery − date of order</p>
                    </div>
                    <div class="text-sm text-slate-600 sm:flex sm:items-center">
                        <span>Updates when you change the order or delivery date in the header above.</span>
                    </div>
                </div>

                <template x-if="lines.length === 0">
                    <p class="text-sm text-gray-500 py-6 text-center">Click “Add line” to add received items.</p>
                </template>

                <div class="space-y-6">
                    <template x-for="(line, index) in lines" :key="index">
                        <div class="border border-gray-200 rounded-lg p-4 relative">
                            <button type="button" @click="removeLine(index)" x-show="lines.length > 1"
                                    class="absolute top-3 right-3 text-xs text-red-600 hover:text-red-800">Remove</button>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700">Item <span class="text-red-500">*</span></label>
                                    <select :name="'lines[' + index + '][item_id]'" x-model="line.item_id" @change="onItemChange(line)" required
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                        <option value="">— Select item —</option>
                                        <template x-for="item in items" :key="item.id">
                                            <option :value="item.id" x-text="item.name + (item.code ? ' (' + item.code + ')' : '')"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Quantity <span class="text-red-500">*</span></label>
                                    <input type="number" step="0.0001" min="0.0001" :name="'lines[' + index + '][quantity]'" x-model.number="line.quantity" required
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Batch no.</label>
                                    <input type="text" :name="'lines[' + index + '][batch_number]'" x-model="line.batch_number"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Expiry date</label>
                                    <input type="date" :name="'lines[' + index + '][expiry_date]'" x-model="line.expiry_date"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">DUOM <span class="text-red-500">*</span></label>
                                    <select :name="'lines[' + index + '][duom]'" x-model="line.duom" required
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                                            :disabled="duoms.length === 0">
                                        <option value="">Select DUOM</option>
                                        <template x-for="name in duoms" :key="name">
                                            <option :value="name" x-text="name"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Purchase price</label>
                                    <input type="number" step="0.01" min="0" :name="'lines[' + index + '][purchase_price]'" x-model.number="line.purchase_price" required
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                </div>
                                <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4 rounded-lg bg-orange-50/80 border border-orange-200 p-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">SUOM <span class="text-red-500">*</span></label>
                                        <select :name="'lines[' + index + '][suom]'" x-model="line.suom" required
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm bg-white"
                                                :disabled="suoms.length === 0">
                                            <option value="">Select SUOM</option>
                                            <template x-for="name in suoms" :key="name">
                                                <option :value="name" x-text="name"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">No of sale units per purchase <span class="text-red-500">*</span></label>
                                        <input type="number" step="0.0001" min="0.0001"
                                               :name="'lines[' + index + '][sale_units_per_purchase_unit]'" x-model.number="line.conversion" required
                                               placeholder="e.g. 30"
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm bg-white">
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 max-w-md">
                                    <label class="block text-sm font-medium text-emerald-900">Qty of sale units purchased</label>
                                    <p class="mt-1 text-xl font-bold text-emerald-950 tabular-nums"
                                       x-text="saleUnits(line).toLocaleString(undefined, {maximumFractionDigits: 4})"></p>
                                    <p class="mt-1 text-xs text-emerald-800">Quantity × no of sale units per purchase</p>
                                    <p class="mt-1 text-xs font-medium text-emerald-900">Added to stock when this GRN is fully approved.</p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('inventory.receive') }}" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</a>
                <button type="submit" name="action" value="draft" @disabled($duoms->isEmpty() || $suoms->isEmpty())
                        class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                    Save draft
                </button>
                <button type="submit" name="action" value="submit" @disabled($duoms->isEmpty() || $suoms->isEmpty())
                        class="px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    Submit for approval
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function grnCreateForm(duoms, suoms, items) {
    return {
        duoms,
        suoms,
        items,
        dateOfOrder: @json(old('date_of_order', now()->toDateString())),
        dateOfDelivery: @json(old('date_of_delivery', now()->toDateString())),
        lines: [{ item_id: '', suom: '', duom: '', quantity: 1, batch_number: '', expiry_date: '', purchase_price: 0, conversion: 1 }],
        get leadTimeDays() {
            if (!this.dateOfOrder || !this.dateOfDelivery) return 0;
            const order = new Date(this.dateOfOrder + 'T00:00:00');
            const delivery = new Date(this.dateOfDelivery + 'T00:00:00');
            const diff = Math.round((delivery - order) / (1000 * 60 * 60 * 24));
            return Math.max(0, diff);
        },
        addLine() {
            this.lines.push({ item_id: '', suom: '', duom: '', quantity: 1, batch_number: '', expiry_date: '', purchase_price: 0, conversion: 1 });
        },
        removeLine(index) {
            this.lines.splice(index, 1);
        },
        onItemChange(line) {
            const item = this.items.find(i => String(i.id) === String(line.item_id));
            if (!item) return;
            if (item.suom && this.suoms.includes(item.suom)) {
                line.suom = item.suom;
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
