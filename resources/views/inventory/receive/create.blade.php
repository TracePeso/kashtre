<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6" x-data="grnCreateForm(
    @js($itemUnits->pluck('name')->values()),
    @js($items->map(fn ($i) => [
        'id' => $i->id,
        'name' => $i->name,
        'code' => $i->code,
        'suom' => $i->itemUnit?->name,
        'order_unit' => $i->orderUnit?->name,
        'suom_per_ouom' => (float) ($i->suom_per_ouom ?? 0),
        'default_price' => (float) ($i->default_price ?? 0),
    ])->values()),
    @js($supplierItemIds),
    @js($prefillLines ?? []),
    @js($prefillStoreId),
    @js($prefillSupplierId),
    @js(old('inventory_order_id', $inventoryOrder?->id))
)">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('inventory.receive') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to Receive Goods</a>
        </div>

        <div class="md:flex md:items-center md:justify-between mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">New Goods Received Note</h2>
                <p class="mt-1 text-sm text-gray-500">
                    @if(!empty($inventoryOrder))
                        Receiving against order <strong>{{ $inventoryOrder->order_number }}</strong>.
                    @else
                        Record incoming goods from a supplier.
                    @endif
                </p>
            </div>
            @if(empty($inventoryOrder))
                <a href="{{ route('inventory.receive.bulk-upload') }}" class="mt-4 md:mt-0 text-sm text-blue-600 hover:text-blue-800">
                    Bulk upload instead
                </a>
            @endif
        </div>

        @include('inventory.partials.subnav')

        @if ($itemUnits->isEmpty())
            <div class="mt-4 bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded text-sm">
                Add item units for your organisation before creating a GRN (delivery unit and sale unit on each line).
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

        <form method="POST" action="{{ route('inventory.receive.store') }}" enctype="multipart/form-data" class="mt-6 space-y-6" novalidate @submit="handleFormSubmit($event)">
            @csrf
            <input type="hidden" name="inventory_order_id" :value="inventoryOrderId ?? ''">

            <div class="bg-white shadow sm:rounded-lg p-6 space-y-5">
                <h3 class="text-lg font-medium text-gray-900 border-b border-gray-200 pb-3">Delivery note header</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label for="supplier_id" class="block text-sm font-medium text-gray-700">Supplier</label>
                        <select name="supplier_id" id="supplier_id" x-model="supplierId" @change="onSupplierChange()"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
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
                                <option value="{{ $store->id }}" @selected(old('store_id', $prefillStoreId ?? null) == $store->id)>{{ $store->selectLabel() }}</option>
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
                </div>

                <div x-show="lines.length > 0" x-cloak
                     class="mb-6 rounded-lg border border-blue-200 bg-blue-50/60 overflow-hidden">
                    <div class="px-4 py-3 border-b border-blue-200/80 flex flex-wrap items-center justify-between gap-2">
                        <h4 class="text-sm font-semibold text-blue-900">
                            Summary
                            <span class="font-normal text-blue-700" x-text="'(' + lines.length + ' item' + (lines.length === 1 ? '' : 's') + ')'"></span>
                        </h4>
                        <dl class="flex flex-wrap gap-x-5 gap-y-1 text-sm">
                            <div class="flex gap-1.5">
                                <dt class="text-blue-700">Sale units:</dt>
                                <dd class="font-semibold text-blue-900 tabular-nums" x-text="formatNumber(totals().saleUnits)"></dd>
                            </div>
                            <div class="flex gap-1.5">
                                <dt class="text-blue-700">Purchase value:</dt>
                                <dd class="font-semibold text-blue-900 tabular-nums" x-text="'UGX ' + formatMoney(totals().purchaseValue)"></dd>
                            </div>
                        </dl>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-blue-100/50 text-xs tracking-wide text-blue-800">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium">#</th>
                                    <th class="px-4 py-2 text-left font-medium">Item</th>
                                    <th class="px-4 py-2 text-right font-medium">Delivery qty</th>
                                    <th class="px-4 py-2 text-left font-medium">Delivery unit</th>
                                    <th class="px-4 py-2 text-left font-medium">Sale unit</th>
                                    <th class="px-4 py-2 text-right font-medium">Sale units</th>
                                    <th class="px-4 py-2 text-right font-medium">Unit price</th>
                                    <th class="px-4 py-2 text-right font-medium">Line total</th>
                                    <th class="px-4 py-2 text-left font-medium">Batch</th>
                                    <th class="px-4 py-2 text-left font-medium">Expiry</th>
                                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-blue-100 bg-white/80">
                                <template x-for="(line, index) in lines" :key="'summary-' + index">
                                    <tr class="hover:bg-blue-50/50" :class="editingIndex === index ? 'ring-2 ring-inset ring-blue-400' : ''">
                                        <td class="px-4 py-2 text-gray-500 tabular-nums" x-text="index + 1"></td>
                                        <td class="px-4 py-2">
                                            <div class="font-medium text-gray-900" x-text="itemForLine(line)?.name || '—'"></div>
                                            <div class="text-xs text-gray-500" x-text="itemForLine(line)?.code || ''"></div>
                                        </td>
                                        <td class="px-4 py-2 text-right tabular-nums text-gray-900" x-text="formatNumber(line.quantity)"></td>
                                        <td class="px-4 py-2 text-gray-700" x-text="line.duom || '—'"></td>
                                        <td class="px-4 py-2 text-gray-700" x-text="line.suom || '—'"></td>
                                        <td class="px-4 py-2 text-right tabular-nums font-medium text-emerald-800" x-text="formatNumber(saleUnits(line))"></td>
                                        <td class="px-4 py-2 text-right tabular-nums text-gray-700" x-text="'UGX ' + formatMoney(line.purchase_price)"></td>
                                        <td class="px-4 py-2 text-right tabular-nums font-medium text-gray-900" x-text="'UGX ' + formatMoney(linePurchaseTotal(line))"></td>
                                        <td class="px-4 py-2 text-gray-600" x-text="line.batch_number || '—'"></td>
                                        <td class="px-4 py-2 text-gray-600 whitespace-nowrap" x-text="line.expiry_date ? formatDate(line.expiry_date) : '—'"></td>
                                        <td class="px-4 py-2 text-right whitespace-nowrap">
                                            <button type="button" @click="editLine(index)"
                                                    class="text-xs font-medium text-blue-600 hover:text-blue-800">Edit</button>
                                            <span class="text-gray-300 mx-1">|</span>
                                            <button type="button" @click="deleteLine(index)"
                                                    class="text-xs font-medium text-red-600 hover:text-red-800">Delete</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot class="bg-blue-100/40 text-sm font-semibold text-blue-900">
                                <tr>
                                    <td colspan="5" class="px-4 py-2 text-right">Totals</td>
                                    <td class="px-4 py-2 text-right tabular-nums" x-text="formatNumber(totals().saleUnits)"></td>
                                    <td class="px-4 py-2"></td>
                                    <td class="px-4 py-2 text-right tabular-nums" x-text="'UGX ' + formatMoney(totals().purchaseValue)"></td>
                                    <td colspan="3"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="px-4 py-3 border-t border-blue-200/80 bg-white/80 flex flex-wrap justify-end gap-3">
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
                </div>

                <p x-show="lines.length === 0" class="mb-4 text-sm text-gray-500 text-center py-2">No items yet. Add an item below.</p>

                <div class="border border-gray-200 rounded-lg bg-gray-50/50 overflow-hidden" x-ref="draftForm">
                    <div class="px-4 py-3 border-b border-gray-200 bg-white flex flex-wrap items-center justify-between gap-2">
                        <h4 class="text-sm font-semibold text-gray-900" x-text="editingIndex !== null ? 'Edit item' : 'Add item'"></h4>
                        <button type="button" x-show="editingIndex !== null" @click="cancelEdit()" x-cloak
                                class="text-xs font-medium text-gray-600 hover:text-gray-800">Cancel edit</button>
                    </div>
                    <div class="px-4 py-4 space-y-3 bg-white">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
                            <div class="sm:col-span-2 lg:col-span-4">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Item <span class="text-red-500">*</span></label>
                                <select x-model="draftLine.item_id" @change="onDraftItemChange()" required
                                        class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Select item</option>
                                    <template x-for="item in filteredItems()" :key="item.id">
                                        <option :value="item.id"
                                                :disabled="isItemTaken(item.id)"
                                                x-text="itemLabel(item)"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="lg:col-span-1">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Qty <span class="text-red-500">*</span></label>
                                <input type="number" step="1" min="1" x-model.number="draftLine.quantity" required
                                       class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div class="lg:col-span-2">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Batch no.</label>
                                <input type="text" x-model="draftLine.batch_number"
                                       class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div class="lg:col-span-2">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Expiry date</label>
                                <input type="date" x-model="draftLine.expiry_date"
                                       class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div class="lg:col-span-2">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Purchase price <span class="text-red-500">*</span></label>
                                <input type="number" step="0.01" min="0" x-model.number="draftLine.purchase_price" required
                                       class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end pt-3 border-t border-gray-100">
                            <div class="lg:col-span-3">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Delivery unit <span class="text-red-500">*</span></label>
                                <select x-model="draftLine.duom" required
                                        class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500"
                                        :disabled="itemUnits.length === 0">
                                    <option value="">Select delivery unit</option>
                                    <template x-for="name in itemUnits" :key="'draft-duom-' + name">
                                        <option :value="name" x-text="name"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="lg:col-span-3">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Sale unit</label>
                                <div class="flex h-[38px] items-center rounded-md border border-gray-200 bg-gray-50 px-3 text-sm text-gray-800">
                                    <span x-text="draftLine.suom || 'Select an item first'"></span>
                                </div>
                            </div>
                            <div class="lg:col-span-3">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Sale units per delivery <span class="text-red-500">*</span></label>
                                <input type="number" step="1" min="1" x-model.number="draftLine.conversion" required
                                       placeholder="e.g. 100"
                                       class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div class="lg:col-span-3">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Sale units</label>
                                <div class="flex h-[38px] items-center rounded-md border border-emerald-200 bg-emerald-50 px-3">
                                    <span class="text-lg font-semibold text-emerald-900 tabular-nums"
                                          x-text="saleUnits(draftLine).toLocaleString(undefined, {maximumFractionDigits: 0})"></span>
                                </div>
                            </div>
                        </div>

                        <div x-show="draftError" x-cloak class="rounded-md bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-800" x-text="draftError"></div>

                        <div class="flex justify-end pt-1">
                            <button type="button" @click="saveDraftLine()"
                                    class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                                <span x-text="editingIndex !== null ? 'Update item' : 'Add item'"></span>
                            </button>
                        </div>
                    </div>
                </div>

                <template x-for="(line, index) in lines" :key="'post-' + index">
                    <div class="hidden" aria-hidden="true">
                        <input type="hidden" :name="'lines[' + index + '][item_id]'" :value="line.item_id">
                        <input type="hidden" :name="'lines[' + index + '][inventory_order_line_id]'" :value="line.inventory_order_line_id || ''">
                        <input type="hidden" :name="'lines[' + index + '][suom]'" :value="line.suom">
                        <input type="hidden" :name="'lines[' + index + '][quantity]'" :value="line.quantity">
                        <input type="hidden" :name="'lines[' + index + '][batch_number]'" :value="line.batch_number || ''">
                        <input type="hidden" :name="'lines[' + index + '][expiry_date]'" :value="line.expiry_date || ''">
                        <input type="hidden" :name="'lines[' + index + '][duom]'" :value="line.duom">
                        <input type="hidden" :name="'lines[' + index + '][purchase_price]'" :value="line.purchase_price">
                        <input type="hidden" :name="'lines[' + index + '][sale_units_per_purchase_unit]'" :value="line.conversion">
                    </div>
                </template>

                <div x-show="lines.length === 0" x-cloak class="mt-4 flex flex-wrap justify-end gap-3">
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
            </div>
        </form>
    </div>
</div>

<script>
function grnCreateForm(itemUnits, items, supplierItemIds, prefillLines, prefillStoreId, prefillSupplierId, inventoryOrderId) {
    const blankLine = () => ({
        item_id: '', inventory_order_line_id: '', suom: '', duom: '', item_suom: '', quantity: 1, batch_number: '', expiry_date: '',
        purchase_price: 0, conversion: 1,
    });

    const mapPrefill = (row) => ({
        item_id: String(row.item_id || ''),
        inventory_order_line_id: row.inventory_order_line_id || '',
        suom: row.suom || '',
        duom: row.duom || row.suom || '',
        item_suom: row.suom || '',
        quantity: row.quantity || 1,
        batch_number: row.batch_number || '',
        expiry_date: row.expiry_date || '',
        purchase_price: row.purchase_price || 0,
        conversion: row.conversion || 1,
    });

    const initialLines = (prefillLines && prefillLines.length)
        ? prefillLines.map(mapPrefill)
        : [];

    return {
        itemUnits,
        items,
        supplierItemIds: supplierItemIds || {},
        supplierId: prefillSupplierId ? String(prefillSupplierId) : '',
        inventoryOrderId: inventoryOrderId || null,
        deliveryNoteName: '',
        lines: initialLines,
        draftLine: blankLine(),
        editingIndex: null,
        draftError: '',
        cloneLine(line) {
            return { ...line };
        },
        resetDraft() {
            this.draftLine = blankLine();
            this.editingIndex = null;
            this.draftError = '';
        },
        filteredItems() {
            if (!this.supplierId) {
                return this.items;
            }
            const allowed = this.supplierItemIds[this.supplierId] || [];
            if (!allowed.length) {
                return this.items;
            }
            const allowedSet = new Set(allowed.map(String));
            return this.items.filter(item => allowedSet.has(String(item.id)));
        },
        onSupplierChange() {
            const allowed = this.filteredItems().map(item => String(item.id));
            this.lines = this.lines.filter(line => !line.item_id || allowed.includes(String(line.item_id)));
            if (this.draftLine.item_id && !allowed.includes(String(this.draftLine.item_id))) {
                this.resetDraft();
            }
        },
        isItemTaken(itemId) {
            const id = String(itemId);
            if (!id) {
                return false;
            }
            return this.lines.some((line, index) => {
                if (this.editingIndex !== null && index === this.editingIndex) {
                    return false;
                }
                return String(line.item_id) === id;
            });
        },
        itemLabel(item) {
            const label = item.name + (item.code ? ' (' + item.code + ')' : '');
            return this.isItemTaken(item.id) ? label + ' — already added' : label;
        },
        onDraftItemChange() {
            const line = this.draftLine;
            const item = this.items.find(i => String(i.id) === String(line.item_id));
            if (!item) {
                line.item_suom = '';
                return;
            }
            if (this.isItemTaken(item.id)) {
                line.item_id = '';
                line.item_suom = '';
                this.draftError = 'That item is already in the summary.';
                return;
            }

            const conversion = (parseFloat(item.suom_per_ouom) || 0) > 0 ? parseFloat(item.suom_per_ouom) : 1;
            const suom = item.suom || '';
            let duom = item.order_unit || suom;
            if (duom && !this.itemUnits.includes(duom)) {
                duom = suom && this.itemUnits.includes(suom) ? suom : '';
            }

            line.item_suom = suom;
            line.suom = suom;
            if (!line.duom && duom) {
                line.duom = duom;
            }
            line.conversion = conversion;
            line.purchase_price = Math.round((parseFloat(item.default_price) || 0) * conversion * 100) / 100;
            this.draftError = '';
        },
        validateDraft() {
            const line = this.draftLine;

            if (!line.item_id) {
                return 'Select an item.';
            }
            if (this.isItemTaken(line.item_id)) {
                return 'That item is already in the summary.';
            }
            if (!line.quantity || parseFloat(line.quantity) <= 0) {
                return 'Enter a quantity greater than zero.';
            }
            if (!line.duom) {
                return 'Select a delivery unit.';
            }
            if (line.purchase_price === '' || line.purchase_price === null || parseFloat(line.purchase_price) < 0) {
                return 'Enter a valid purchase price.';
            }
            if (!line.conversion || parseFloat(line.conversion) <= 0) {
                return 'Sale units per delivery must be greater than zero.';
            }

            return '';
        },
        saveDraftLine() {
            const error = this.validateDraft();
            if (error) {
                this.draftError = error;
                return;
            }

            const saved = this.cloneLine(this.draftLine);

            if (this.editingIndex !== null) {
                this.lines.splice(this.editingIndex, 1, saved);
            } else {
                this.lines.push(saved);
            }

            this.resetDraft();
            this.$nextTick(() => this.$refs.draftForm?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
        },
        editLine(index) {
            this.draftLine = this.cloneLine(this.lines[index]);
            this.editingIndex = index;
            this.draftError = '';
            this.$nextTick(() => this.$refs.draftForm?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
        },
        deleteLine(index) {
            if (! confirm('Remove this item from the GRN?')) {
                return;
            }

            if (this.editingIndex === index) {
                this.resetDraft();
            } else if (this.editingIndex !== null && this.editingIndex > index) {
                this.editingIndex -= 1;
            }

            this.lines.splice(index, 1);
        },
        cancelEdit() {
            this.resetDraft();
        },
        handleFormSubmit(event) {
            if (this.lines.length === 0) {
                event.preventDefault();
                this.draftError = 'Add at least one item before submitting.';
                this.$refs.draftForm?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        },
        saleUnits(line) {
            const q = parseFloat(line.quantity) || 0;
            const c = parseFloat(line.conversion) || 0;
            return Math.round(q * c * 10000) / 10000;
        },
        itemForLine(line) {
            if (! line.item_id) {
                return null;
            }
            return this.items.find(i => String(i.id) === String(line.item_id)) || null;
        },
        linePurchaseTotal(line) {
            const q = parseFloat(line.quantity) || 0;
            const p = parseFloat(line.purchase_price) || 0;
            return Math.round(q * p * 100) / 100;
        },
        totals() {
            return {
                saleUnits: this.lines.reduce((sum, line) => sum + this.saleUnits(line), 0),
                purchaseValue: this.lines.reduce((sum, line) => sum + this.linePurchaseTotal(line), 0),
            };
        },
        formatNumber(value) {
            const n = parseFloat(value) || 0;
            return n.toLocaleString(undefined, { maximumFractionDigits: 0 });
        },
        formatMoney(value) {
            const n = parseFloat(value) || 0;
            return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        formatDate(value) {
            if (! value) {
                return '—';
            }
            const date = new Date(value + 'T00:00:00');
            if (Number.isNaN(date.getTime())) {
                return value;
            }
            return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
        },
    };
}
</script>
</x-app-layout>
