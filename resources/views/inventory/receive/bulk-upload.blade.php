<x-app-layout>
@include('partials.inventory.supplier-category-filter-script')
<div class="min-h-screen bg-gray-50 py-6" x-data="grnBulkUploadForm(
    @js($itemUnits->pluck('name')->values()),
    @js($grnFormItems),
    @js($supplierItemIds),
    @js([
        'bulkTemplate' => route('inventory.receive.bulk-template'),
        'bulkImport' => route('inventory.receive.bulk-import'),
        'csrf' => csrf_token(),
    ]),
    @js($supplierCatalog ?? []),
    @js($supplierIndustries ?? []),
    @js($supplierSubCategoriesByIndustry ?? [])
)">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('inventory.receive') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Receive Goods</a>
        </div>

        <div class="md:flex md:items-center md:justify-between mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Bulk goods receive note upload</h2>
                <p class="mt-1 text-sm text-gray-500">Download the template, fill in quantities, upload, then review and submit.</p>
            </div>
            <a href="{{ route('inventory.receive.create') }}" class="mt-4 md:mt-0 text-sm text-blue-600 hover:text-blue-800">
                Enter manually instead
            </a>
        </div>

        @include('inventory.partials.subnav')

        @if ($itemUnits->isEmpty())
            <div class="mt-4 bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded text-sm">
                Add item units before uploading a goods receive note.
                <a href="{{ route('item-units.index') }}" class="font-medium underline">Item Units</a>
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

        <div class="mt-4 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-600">
            <p>Complete all fields on this form. Compulsory fields are marked with <span class="text-red-500 font-medium">*</span>.</p>
        </div>

        <form method="POST" action="{{ route('inventory.receive.store') }}" enctype="multipart/form-data" class="mt-6 space-y-6" novalidate @submit="handleSubmit($event)">
            @csrf
            <input type="hidden" name="action" value="submit">

            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="mb-4">
                    @include('partials.inventory.supplier-category-filter-fields')
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label for="supplier_id" class="block text-sm font-medium text-gray-700">Supplier <span class="text-red-500">*</span></label>
                        <select name="supplier_id" id="supplier_id" required x-model="supplierId" @change="onSupplierChange()"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            <option value="">— Select —</option>
                            <template x-for="supplier in filteredSupplierCatalog()" :key="supplier.id">
                                <option :value="supplier.id" x-text="supplier.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label for="store_id" class="block text-sm font-medium text-gray-700">Store <span class="text-red-500">*</span></label>
                        <select name="store_id" id="store_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            <option value="">— Select —</option>
                            @foreach($stores as $store)
                                <option value="{{ $store->id }}">{{ $store->selectLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="date_of_order" class="block text-sm font-medium text-gray-700">Order date <span class="text-red-500">*</span></label>
                        <input type="date" name="date_of_order" id="date_of_order" required value="{{ now()->toDateString() }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                    </div>
                    <div>
                        <label for="date_of_delivery" class="block text-sm font-medium text-gray-700">Delivery date <span class="text-red-500">*</span></label>
                        <input type="date" name="date_of_delivery" id="date_of_delivery" required value="{{ now()->toDateString() }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Delivery note</label>
                        <input type="file" name="delivery_note" accept=".pdf,.jpg,.jpeg,.png"
                               class="mt-1 block w-full text-sm text-gray-600">
                    </div>
                    <div class="md:col-span-2">
                        <label for="technical_supervisor_user_id" class="block text-sm font-medium text-gray-700">
                            Technical supervisor
                            @if($grnTechnicalSupervisorRequired)
                                <span class="text-red-500">*</span>
                            @else
                                <span class="text-gray-400 font-normal">(optional, per GRN)</span>
                            @endif
                        </label>
                        <select name="technical_supervisor_user_id" id="technical_supervisor_user_id"
                                @if($grnTechnicalSupervisorRequired) required @endif
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            @unless($grnTechnicalSupervisorRequired)
                                <option value="">— None —</option>
                            @else
                                <option value="" disabled @selected(! old('technical_supervisor_user_id'))>Select technical supervisor</option>
                            @endunless
                            @foreach($businessUsers as $user)
                                <option value="{{ $user->id }}" @selected(old('technical_supervisor_user_id') == $user->id)>
                                    {{ $user->name }} ({{ $user->email }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Import from CSV</h3>
                    <p class="mt-1 text-sm text-gray-500">Download the template, fill in quantities, then upload the completed file.</p>
                </div>

                <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {{-- Step 1: Download --}}
                    <div class="rounded-lg border border-blue-100 bg-blue-50/60 p-5 flex flex-col">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold text-white">1</span>
                            <h4 class="text-sm font-semibold text-gray-900">Download template</h4>
                        </div>
                        <p class="text-sm text-gray-600">
                            Choose items below, then download a template with only those rows.
                        </p>

                        <div class="mt-4 rounded-lg border border-blue-200 bg-white p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                <label class="text-xs font-semibold text-gray-700">Items for template</label>
                                <span class="text-xs text-gray-500" x-text="selectedItemIds.length + ' of ' + catalogItems().length + ' selected'"></span>
                            </div>
                            <input type="search" x-model="itemSearch" placeholder="Search by name or code…"
                                   class="block w-full rounded-md border-gray-300 py-1.5 px-2 text-xs shadow-sm">
                            <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                <button type="button" @click="selectAllVisible()"
                                        class="text-blue-700 hover:text-blue-900 font-medium">Select all</button>
                                <span class="text-gray-300">|</span>
                                <button type="button" @click="clearSelection()"
                                        class="text-gray-600 hover:text-gray-800 font-medium">Clear</button>
                            </div>
                            <div class="mt-2 max-h-44 overflow-y-auto rounded border border-gray-200 divide-y divide-gray-100">
                                <template x-if="catalogItems().length === 0">
                                    <p class="px-3 py-4 text-xs text-gray-500 text-center">No items match your search.</p>
                                </template>
                                <template x-for="item in catalogItems()" :key="'pick-' + item.id">
                                    <label class="flex items-start gap-2 px-2 py-1.5 hover:bg-gray-50 cursor-pointer">
                                        <input type="checkbox" class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                               :checked="isItemSelected(item.id)" @change="toggleItem(item.id)">
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-xs font-medium text-gray-900 truncate" x-text="item.name"></span>
                                            <span class="block text-[10px] text-gray-400" x-text="item.code"></span>
                                        </span>
                                    </label>
                                </template>
                            </div>
                        </div>

                        <p class="mt-3 text-xs text-gray-500 rounded-md bg-white/80 border border-blue-100 px-3 py-2">
                            <span class="font-medium text-gray-700">Expiry date:</span> optional, use <span class="font-mono text-gray-800">YYYY-MM-DD</span> (e.g. <span class="font-mono text-gray-800">2026-12-31</span>).
                        </p>
                        <a :href="templateDownloadUrl()"
                           @click="if (selectedItemIds.length === 0) { $event.preventDefault(); templateError = 'Select at least one item for the template.'; }"
                           :class="selectedItemIds.length === 0 ? 'opacity-50 pointer-events-none' : ''"
                           class="mt-4 inline-flex items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            Download template
                        </a>
                        <p x-show="templateError" x-cloak class="mt-2 text-xs text-red-600" x-text="templateError"></p>
                    </div>

                    {{-- Step 2: Upload --}}
                    <div class="rounded-lg border border-gray-200 bg-gray-50/50 p-5 flex flex-col">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-600 text-xs font-semibold text-white">2</span>
                            <h4 class="text-sm font-semibold text-gray-900">Upload completed file</h4>
                        </div>

                        <div class="relative flex-1"
                             @dragover.prevent="dragOver = true"
                             @dragenter.prevent="dragOver = true"
                             @dragleave.prevent="dragOver = false"
                             @drop.prevent="handleDrop($event)">
                            <div class="flex min-h-[9.5rem] flex-col items-center justify-center rounded-lg border-2 border-dashed px-4 py-6 text-center transition-colors"
                                 :class="dragOver ? 'border-emerald-400 bg-emerald-50' : (selectedFileName ? 'border-emerald-300 bg-white' : 'border-gray-300 bg-white hover:border-gray-400')">
                                <template x-if="bulkImporting">
                                    <div class="flex flex-col items-center gap-3">
                                        <svg class="h-8 w-8 animate-spin text-emerald-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <p class="text-sm font-medium text-gray-700">Processing file…</p>
                                    </div>
                                </template>

                                <template x-if="!bulkImporting">
                                    <div class="flex flex-col items-center gap-2">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-3L12 3m0 0l4.5 4.5M12 3v13.5" />
                                            </svg>
                                        </div>
                                        <p class="text-sm font-medium text-gray-900">
                                            <span x-show="!selectedFileName">Drag and drop your CSV here</span>
                                            <span x-show="selectedFileName" x-cloak x-text="selectedFileName"></span>
                                        </p>
                                        <p class="text-xs text-gray-500" x-show="!selectedFileName">or</p>
                                        <button type="button"
                                                @click="$refs.csvInput.click()"
                                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                                            Browse files
                                        </button>
                                        <p class="text-xs text-gray-400">CSV only</p>
                                    </div>
                                </template>
                            </div>

                            <input type="file"
                                   x-ref="csvInput"
                                   accept=".csv,text/csv"
                                   class="sr-only"
                                   @change="handleFileSelect($event)">
                        </div>
                    </div>
                </div>

                <div x-show="bulkImportMessage" x-cloak class="mx-6 mb-6 rounded-md px-4 py-3 text-sm"
                     :class="bulkImportOk ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'">
                    <p x-text="bulkImportMessage"></p>
                    <ul x-show="bulkImportErrors.length" class="mt-2 list-disc list-inside space-y-0.5">
                        <template x-for="(error, i) in bulkImportErrors" :key="'bulk-err-' + i">
                            <li x-text="error"></li>
                        </template>
                    </ul>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="px-4 py-2.5 border-b border-gray-200 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-base font-medium text-gray-900">
                        Lines
                        <span class="text-xs font-normal text-gray-500" x-show="lines.length > 0" x-text="'(' + lines.length + ')'"></span>
                    </h3>
                    <dl class="flex gap-4 text-xs" x-show="lines.length > 0">
                        <div><span class="text-gray-500">Sale units:</span> <span class="font-semibold tabular-nums" x-text="formatNumber(totals().saleUnits)"></span></div>
                        <div><span class="text-gray-500">Total:</span> <span class="font-semibold tabular-nums" x-text="'UGX ' + formatMoney(totals().purchaseValue)"></span></div>
                    </dl>
                </div>

                <template x-if="lines.length === 0">
                    <p class="px-4 py-8 text-center text-sm text-gray-500">No lines yet. Download the template above, add quantities, then upload your CSV.</p>
                </template>

                <div x-show="lines.length > 0" class="overflow-x-auto">
                    <table class="min-w-full text-xs leading-tight">
                        <thead class="bg-gray-50 text-[11px] text-gray-500">
                            <tr>
                                <th class="px-2 py-1.5 text-left font-semibold text-gray-700 w-[11rem] max-w-[11rem]">Item</th>
                                <th class="px-1.5 py-1.5 text-right font-semibold text-gray-700 w-14" title="Quantity received in the delivery unit">Qty</th>
                                <th class="px-1.5 py-1.5 text-left font-semibold text-gray-700 w-24">Batch</th>
                                <th class="px-1.5 py-1.5 text-left font-semibold text-gray-700 w-28">Expiry</th>
                                <th class="px-1.5 py-1.5 text-left font-semibold text-gray-700 w-24" title="Unit on the supplier delivery note">Del. unit</th>
                                <th class="px-1.5 py-1.5 text-right font-semibold text-gray-700 w-20" title="Unit price per delivery unit">Unit price</th>
                                <th class="px-1.5 py-1.5 text-right font-semibold text-gray-700 w-20" title="Line total amount">Total</th>
                                <th class="px-1.5 py-1.5 text-right font-semibold text-gray-700 w-16" title="Sale units in one delivery unit">Per del.</th>
                                <th class="px-1.5 py-1.5 text-right font-semibold text-gray-700 w-16" title="Total sale units">Sale units</th>
                                <th class="px-1 py-1.5 w-14"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="(line, index) in lines" :key="index">
                                <tr class="align-middle">
                                    <td class="px-2 py-1 max-w-[11rem]">
                                        <div class="font-medium text-gray-900 truncate" :title="itemForLine(line)?.name || ''" x-text="itemForLine(line)?.name || '—'"></div>
                                        <div class="text-[10px] text-gray-400 truncate" x-text="itemForLine(line)?.code || ''"></div>
                                    </td>
                                    <td class="px-1.5 py-1">
                                        <input type="number" step="1" min="1" x-model.number="line.quantity"
                                               @input="onLineCostChange(line, 'qty')"
                                               class="w-14 rounded border-gray-300 py-1 px-1.5 text-xs text-right">
                                    </td>
                                    <td class="px-1.5 py-1">
                                        <input type="text" x-model="line.batch_number"
                                               class="w-full min-w-[5.5rem] rounded border-gray-300 py-1 px-1.5 text-xs">
                                    </td>
                                    <td class="px-1.5 py-1">
                                        <input type="text" x-model="line.expiry_date" placeholder="YYYY-MM-DD" pattern="\d{4}-\d{2}-\d{2}"
                                               class="w-[7.25rem] rounded border-gray-300 py-1 px-1.5 text-xs font-mono">
                                    </td>
                                    <td class="px-1.5 py-1">
                                        <select x-model="line.duom"
                                                class="w-full min-w-[5.5rem] rounded border-gray-300 py-1 pl-1 pr-6 text-xs">
                                            <template x-for="name in itemUnits" :key="'duom-' + index + name">
                                                <option :value="name" x-text="name"></option>
                                            </template>
                                        </select>
                                    </td>
                                    <td class="px-1.5 py-1">
                                        <input type="number" step="0.01" min="0" x-model.number="line.purchase_price"
                                               @input="onLineCostChange(line, 'unit')"
                                               class="w-16 rounded border-gray-300 py-1 px-1.5 text-xs text-right">
                                    </td>
                                    <td class="px-1.5 py-1">
                                        <input type="number" step="0.01" min="0" x-model.number="line.total_amount"
                                               @input="onLineCostChange(line, 'total')"
                                               class="w-20 rounded border-gray-300 py-1 px-1.5 text-xs text-right">
                                    </td>
                                    <td class="px-1.5 py-1">
                                        <input type="number" step="1" min="1" x-model.number="line.conversion"
                                               title="Sale units in one delivery unit, e.g. 100 tablets per box"
                                               class="w-14 rounded border-gray-300 py-1 px-1.5 text-xs text-right">
                                    </td>
                                    <td class="px-1.5 py-1 text-right tabular-nums text-emerald-800 font-medium" x-text="formatNumber(saleUnits(line))"></td>
                                    <td class="px-1 py-1 text-right">
                                        <button type="button" @click="removeLine(index)" class="text-red-600 hover:text-red-800 text-[10px] font-medium">Remove</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
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

            <div x-show="submitError" x-cloak class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800" x-text="submitError"></div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('inventory.receive') }}" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</a>
                <button type="submit"
                        :disabled="lines.length === 0 || submitting || @js($itemUnits->isEmpty())"
                        class="px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="!submitting">Submit</span>
                    <span x-show="submitting" x-cloak>Submitting…</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function grnBulkUploadForm(itemUnits, items, supplierItemIds, urls, supplierCatalog, supplierIndustries, supplierSubCategoriesByIndustry) {
    return {
        ...supplierCategoryFilterMixin(supplierCatalog, supplierIndustries, supplierSubCategoriesByIndustry),
        itemUnits,
        items,
        supplierItemIds: supplierItemIds || {},
        supplierId: '',
        itemSearch: '',
        selectedItemIds: [],
        templateError: '',
        urls: urls || {},
        bulkImporting: false,
        bulkImportMessage: '',
        bulkImportOk: false,
        bulkImportErrors: [],
        dragOver: false,
        selectedFileName: '',
        submitError: '',
        submitting: false,
        lines: [],
        init() {
            this.selectAllVisible();
        },
        onSupplierCategoryFilterChange() {
            if (this.supplierId && ! this.filteredSupplierCatalog().some((supplier) => String(supplier.id) === String(this.supplierId))) {
                this.supplierId = '';
                this.onSupplierChange();
            }
        },
        catalogItems() {
            let list = this.items;

            if (this.supplierId) {
                const allowed = this.supplierItemIds[this.supplierId] || this.supplierItemIds[String(this.supplierId)];

                if (Array.isArray(allowed) && allowed.length > 0) {
                    const allowedSet = new Set(allowed.map(id => String(id)));
                    list = list.filter(item => allowedSet.has(String(item.id)));
                }
            }

            const query = this.itemSearch.trim().toLowerCase();

            if (query) {
                list = list.filter(item => {
                    const name = (item.name || '').toLowerCase();
                    const code = (item.code || '').toLowerCase();

                    return name.includes(query) || code.includes(query);
                });
            }

            return list;
        },
        onSupplierChange() {
            this.templateError = '';
            this.itemSearch = '';
            this.selectAllVisible();
        },
        isItemSelected(id) {
            return this.selectedItemIds.includes(String(id));
        },
        toggleItem(id) {
            const key = String(id);
            const index = this.selectedItemIds.indexOf(key);

            if (index >= 0) {
                this.selectedItemIds.splice(index, 1);
            } else {
                this.selectedItemIds.push(key);
            }

            this.templateError = '';
        },
        selectAllVisible() {
            this.selectedItemIds = this.catalogItems().map(item => String(item.id));
            this.templateError = '';
        },
        clearSelection() {
            this.selectedItemIds = [];
            this.templateError = '';
        },
        itemForLine(line) {
            return this.items.find(i => String(i.id) === String(line.item_id)) || null;
        },
        saleUnits(line) {
            return Math.round((parseFloat(line.quantity) || 0) * (parseFloat(line.conversion) || 0) * 10000) / 10000;
        },
        linePurchaseTotal(line) {
            return Math.round((parseFloat(line.quantity) || 0) * (parseFloat(line.purchase_price) || 0) * 100) / 100;
        },
        onLineCostChange(line, source) {
            const qty = parseFloat(line.quantity) || 0;

            if (source === 'total') {
                if (qty > 0) {
                    line.purchase_price = Math.round(((parseFloat(line.total_amount) || 0) / qty) * 100) / 100;
                }

                return;
            }

            line.total_amount = Math.round(qty * (parseFloat(line.purchase_price) || 0) * 100) / 100;
        },
        normalizeImportedLine(line) {
            const normalized = {
                ...line,
                expiry_date: line.expiry_date || '',
            };

            if (normalized.total_amount === undefined || normalized.total_amount === null) {
                normalized.total_amount = this.linePurchaseTotal(normalized);
            }

            return normalized;
        },
        totals() {
            return {
                saleUnits: this.lines.reduce((sum, line) => sum + this.saleUnits(line), 0),
                purchaseValue: this.lines.reduce((sum, line) => sum + this.linePurchaseTotal(line), 0),
            };
        },
        formatNumber(value) {
            return (parseFloat(value) || 0).toLocaleString(undefined, { maximumFractionDigits: 0 });
        },
        formatMoney(value) {
            return (parseFloat(value) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        removeLine(index) {
            this.lines.splice(index, 1);
        },
        handleSubmit(event) {
            this.submitError = '';

            if (this.lines.length === 0) {
                event.preventDefault();
                this.submitError = 'Upload a CSV with at least one line before submitting.';
                return;
            }

            const form = event.target;
            const supplierId = form.elements.namedItem('supplier_id')?.value;
            const storeId = form.elements.namedItem('store_id')?.value;
            const orderDate = form.elements.namedItem('date_of_order')?.value;
            const deliveryDate = form.elements.namedItem('date_of_delivery')?.value;

            if (! supplierId) {
                event.preventDefault();
                this.submitError = 'Select a supplier.';
                return;
            }

            if (! storeId) {
                event.preventDefault();
                this.submitError = 'Select a receiving store.';
                return;
            }

            if (! orderDate || ! deliveryDate) {
                event.preventDefault();
                this.submitError = 'Order date and delivery date are required.';
                return;
            }

            if (deliveryDate < orderDate) {
                event.preventDefault();
                this.submitError = 'Delivery date cannot be before the order date.';
                return;
            }

            for (let i = 0; i < this.lines.length; i++) {
                const line = this.lines[i];
                const label = this.itemForLine(line)?.name || ('Line ' + (i + 1));

                if (! line.item_id) {
                    event.preventDefault();
                    this.submitError = label + ': item is missing.';
                    return;
                }

                if (! line.quantity || parseFloat(line.quantity) <= 0) {
                    event.preventDefault();
                    this.submitError = label + ': enter a delivery quantity greater than zero.';
                    return;
                }

                if (! line.duom) {
                    event.preventDefault();
                    this.submitError = label + ': select a delivery unit.';
                    return;
                }

                if (line.purchase_price === '' || line.purchase_price === null || parseFloat(line.purchase_price) < 0) {
                    event.preventDefault();
                    this.submitError = label + ': enter a valid unit price.';
                    return;
                }

                if (line.total_amount === '' || line.total_amount === null || parseFloat(line.total_amount) < 0) {
                    event.preventDefault();
                    this.submitError = label + ': enter a valid total amount.';
                    return;
                }

                if (! line.conversion || parseFloat(line.conversion) <= 0) {
                    event.preventDefault();
                    this.submitError = label + ': sale units per delivery must be greater than zero.';
                    return;
                }

                if (line.expiry_date && ! /^\d{4}-\d{2}-\d{2}$/.test(String(line.expiry_date).trim())) {
                    event.preventDefault();
                    this.submitError = label + ': expiry date must be YYYY-MM-DD (e.g. 2026-12-31).';
                    return;
                }
            }

            this.submitting = true;
        },
        templateDownloadUrl() {
            const url = new URL(this.urls.bulkTemplate, window.location.origin);

            if (this.supplierId) {
                url.searchParams.set('supplier_id', this.supplierId);
            }

            if (this.selectedItemIds.length > 0) {
                url.searchParams.set('item_ids', this.selectedItemIds.join(','));
            }

            this.templateError = '';

            return url.toString();
        },
        handleDrop(event) {
            this.dragOver = false;
            const file = event.dataTransfer?.files?.[0];
            if (file) {
                this.processFile(file);
            }
        },
        handleFileSelect(event) {
            const file = event.target.files?.[0];
            event.target.value = '';
            if (file) {
                this.processFile(file);
            }
        },
        async processFile(file) {
            if (! file.name.toLowerCase().endsWith('.csv')) {
                this.bulkImportOk = false;
                this.bulkImportMessage = 'Please upload a CSV file.';
                this.bulkImportErrors = [];
                return;
            }

            if (this.lines.length > 0 && ! confirm('Replace current lines with this upload?')) {
                return;
            }

            this.selectedFileName = file.name;
            this.bulkImporting = true;
            this.bulkImportMessage = '';
            this.bulkImportErrors = [];
            this.bulkImportOk = false;

            const formData = new FormData();
            formData.append('file', file);
            if (this.supplierId) {
                formData.append('supplier_id', this.supplierId);
            }

            try {
                const response = await fetch(this.urls.bulkImport, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.urls.csrf,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                const data = await response.json();
                this.bulkImportErrors = data.errors || [];

                if (! response.ok || ! data.ok) {
                    this.bulkImportOk = false;
                    this.bulkImportMessage = 'Could not import file.';
                    return;
                }

                if ((data.lines || []).length === 0) {
                    this.bulkImportOk = false;
                    this.bulkImportMessage = 'No items imported. Add quantities in the file.';
                    return;
                }

                this.lines = (data.lines || []).map(line => this.normalizeImportedLine(line));
                this.bulkImportOk = true;
                const warning = this.bulkImportErrors.length ? ' Some rows were skipped.' : '';
                this.bulkImportMessage = 'Imported ' + data.imported_count + ' item(s).' + warning;
            } catch (error) {
                this.bulkImportOk = false;
                this.bulkImportMessage = 'Upload failed. Please try again.';
            } finally {
                this.bulkImporting = false;
            }
        },
    };
}
</script>
</x-app-layout>
