<x-app-layout>
@include('partials.inventory.supplier-category-filter-script')
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <a href="{{ route('inventory.orders.show', $order) }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to {{ $order->order_number }}</a>
                <h2 class="mt-2 text-2xl font-bold text-gray-900">Quotation analysis</h2>
                <p class="mt-1 text-sm text-gray-500">Invite suppliers, record quotations, then compare prices and allocate each item on the computation sheet.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($order->purchaseOrders->isNotEmpty())
                    <a href="{{ route('inventory.purchase-orders.index') }}"
                       class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 hover:bg-indigo-100">
                        View all LPOs ({{ $order->purchaseOrders->count() }})
                    </a>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        @if($order->purchaseOrders->isNotEmpty())
            <section class="bg-white shadow sm:rounded-lg overflow-hidden border border-indigo-100">
                <div class="px-5 py-4 border-b border-indigo-100 bg-indigo-50/60 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">LPOs for this RFQ</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Open an LPO to review and issue (email supplier).</p>
                    </div>
                    <a href="{{ route('inventory.purchase-orders.index') }}" class="text-sm font-medium text-indigo-700 hover:text-indigo-900">All purchase orders →</a>
                </div>
                <ul class="divide-y divide-gray-100">
                    @foreach($order->purchaseOrders as $po)
                        <li class="px-5 py-3 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <a href="{{ route('inventory.purchase-orders.show', $po) }}" class="text-sm font-semibold text-indigo-700 hover:text-indigo-900">{{ $po->po_number }}</a>
                                <p class="text-xs text-gray-500">{{ $po->supplier?->name }} · {{ $po->statusLabel() }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-medium text-gray-900 tabular-nums">UGX {{ number_format((float) $po->total_amount, 2) }}</span>
                                <a href="{{ route('inventory.purchase-orders.show', $po) }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">Open</a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="bg-white shadow sm:rounded-lg overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Invite suppliers to this RFQ</h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Invited suppliers with an email receive the price-hidden RFQ PDF when the order is fully approved.
                        Use <strong>Download RFQ PDF</strong> to share manually with suppliers who have no email.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2 shrink-0">
                    @include('inventory.partials.rfq-download-button', ['order' => $order, 'variant' => 'compact'])
                </div>
            </div>
            @php
                $hasInvitedSuppliers = $rfqSuppliers->isNotEmpty();
                $canEditInvites = $order->canEditRfqSuppliers();
                $showInviteEditor = ! $hasInvitedSuppliers || $errors->has('supplier_ids');
            @endphp
            <div class="px-5 py-4" x-data="{
                editingInvites: @js($showInviteEditor),
                ...supplierCategoryFilterMixin(
                    @js($supplierCatalog),
                    @js($supplierIndustries),
                    @js($supplierSubCategoriesByIndustry)
                ),
            }">
                @if($hasInvitedSuppliers)
                    <div x-show="!editingInvites" x-cloak class="space-y-3">
                        <ul class="divide-y divide-gray-100 rounded-md border border-gray-200">
                            @foreach($rfqSuppliers as $row)
                                <li class="px-4 py-3 flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ $row['supplier_name'] }}</p>
                                        <p class="text-xs text-gray-500">
                                            {{ $row['email'] ?: 'No email — download RFQ to share' }}
                                            @if(! empty($row['is_kashtre_entity']))
                                                · <span class="text-indigo-700">Kashtre entity — responds in Incoming RFQs</span>
                                            @endif
                                        </p>
                                    </div>
                                    <span class="text-xs font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full">Invited</span>
                                </li>
                            @endforeach
                        </ul>
                        @if($canEditInvites)
                            <button type="button" @click="editingInvites = true"
                                    class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                Change invited suppliers
                            </button>
                        @endif
                    </div>
                @endif

                @if($canEditInvites)
                    <div x-show="editingInvites" x-cloak
                         @class(['space-y-3', 'mt-4' => $hasInvitedSuppliers])>
                        @include('partials.inventory.supplier-category-filter-fields', ['class' => 'mb-4'])

                        <form action="{{ route('inventory.orders.rfq-suppliers.invite', $order) }}" method="POST" class="space-y-3">
                            @csrf
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-48 overflow-y-auto">
                                @foreach($availableSuppliers as $supplier)
                                    @php
                                        $already = $rfqSuppliers->contains(
                                            fn ($rfqRow) => (int) $rfqRow['supplier_id'] === (int) $supplier->id
                                        );
                                    @endphp
                                    <label class="flex items-start gap-2 text-sm text-gray-800"
                                           x-show="supplierMatchesCategoryFilter(@js($supplier->supplier_industry_id), @js($supplier->supplier_sub_category_id))"
                                           x-cloak>
                                        <input type="checkbox" name="supplier_ids[]" value="{{ $supplier->id }}"
                                               @checked($already)
                                               class="mt-1 rounded border-gray-300 text-blue-600">
                                        <span>
                                            {{ $supplier->name }}
                                            <span class="block text-xs text-gray-500">
                                                {{ $supplier->email ?: 'No email — download RFQ to share' }}
                                                @if($supplier->isKashtreEntitySupplier())
                                                    · <span class="text-indigo-700">Kashtre entity — responds in Incoming RFQs</span>
                                                @endif
                                                @if($supplier->industry && ($supplier->industry->name || ($supplier->subCategory && $supplier->subCategory->name)))
                                                    · {{ $supplier->industry->name ?? '—' }} / {{ $supplier->subCategory->name ?? '—' }}
                                                @endif
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="submit" class="px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                                    {{ $hasInvitedSuppliers ? 'Update invited suppliers' : 'Save invited suppliers' }}
                                </button>
                                @if($hasInvitedSuppliers)
                                    <button type="button" @click="editingInvites = false"
                                            class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                        Cancel
                                    </button>
                                @endif
                            </div>
                        </form>
                    </div>
                @elseif(! $hasInvitedSuppliers)
                    <p class="text-sm text-gray-500">No suppliers invited yet. Invites cannot be changed at this stage.</p>
                @endif
            </div>
        </section>

        <section class="bg-white shadow sm:rounded-lg overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900">Record a supplier quotation</h3>
                <p class="text-xs text-gray-500 mt-0.5">Enter quoted quantities and purchase prices from each supplier.</p>
            </div>
            <div class="px-5 py-4 space-y-4">
                @forelse($rfqSuppliers as $row)
                    @php
                        $quotation = $row['quotation'] ?? null;
                    @endphp
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $row['supplier_name'] }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $row['email'] ?: 'No email on file' }}
                                    @if(! empty($row['is_kashtre_entity']))
                                        · <span class="text-indigo-700">Kashtre entity — can submit via Incoming RFQs</span>
                                    @endif
                                </p>
                            </div>
                            @if($quotation)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-blue-50 text-blue-800">{{ $quotation->statusLabel() }}</span>
                            @endif
                        </div>
                        @if($quotation && $quotation->isAccepted())
                            <p class="text-sm text-gray-600">Quotation accepted — generate an LPO in the next section below.</p>
                        @else
                            <form action="{{ route('inventory.orders.quotations.store', $order) }}" method="POST" class="space-y-3">
                                @csrf
                                <input type="hidden" name="supplier_id" value="{{ $row['supplier_id'] }}">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700">Supplier reference</label>
                                        <input type="text" name="reference_number" value="{{ $quotation ? $quotation->reference_number : '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700">Notes</label>
                                        <input type="text" name="notes" value="{{ $quotation ? $quotation->notes : '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    </div>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-xs">
                                        <thead>
                                            <tr class="text-left text-gray-500">
                                                <th class="py-1 pr-2">Item</th>
                                                <th class="py-1 pr-2 text-right">RFQ qty</th>
                                                <th class="py-1 pr-2 text-right">Quoted qty</th>
                                                <th class="py-1 pr-2 text-right">Purchase price</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($order->lines as $index => $line)
                                                @php
                                                    $existing = $quotation && $quotation->lines
                                                        ? $quotation->lines->firstWhere('inventory_order_line_id', $line->id)
                                                        : null;
                                                    $rfqQty = (float) $line->rfqQuantity();
                                                    $existingQty = $existing ? (float) $existing->quoted_quantity_suom : null;
                                                    if ($existingQty !== null && $existingQty > 0) {
                                                        $defaultQuotedQty = $existingQty;
                                                    } elseif ($rfqQty > 0) {
                                                        $defaultQuotedQty = $rfqQty;
                                                    } else {
                                                        $defaultQuotedQty = null;
                                                    }
                                                    $qtyDecimals = $line->item?->usesPackagingUnits() ? 2 : 0;
                                                @endphp
                                                <tr>
                                                    <td class="py-1 pr-2">{{ $line->item ? $line->item->name : '' }}</td>
                                                    <td class="py-1 pr-2 text-right tabular-nums">{{ number_format($rfqQty, $qtyDecimals) }}</td>
                                                    <td class="py-1 pr-2">
                                                        <input type="hidden" name="lines[{{ $index }}][inventory_order_line_id]" value="{{ $line->id }}">
                                                        <input type="number" step="{{ $qtyDecimals > 0 ? '0.01' : '1' }}" min="0" name="lines[{{ $index }}][quoted_quantity_suom]"
                                                               value="{{ $defaultQuotedQty !== null ? number_format($defaultQuotedQty, $qtyDecimals, '.', '') : '' }}"
                                                               class="w-24 rounded border-gray-300 text-right text-sm">
                                                    </td>
                                                    <td class="py-1">
                                                        <input type="number" step="0.01" min="0" name="lines[{{ $index }}][unit_price]"
                                                               value="{{ number_format((float) ($existing->unit_price ?? 0), 2, '.', '') }}"
                                                               class="w-28 rounded border-gray-300 text-right text-sm">
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <button type="submit" class="px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                                    {{ $quotation ? 'Update quotation' : 'Save quotation' }}
                                </button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Invite at least one supplier first.</p>
                @endforelse
            </div>
        </section>

        @include('inventory.partials.rfq-computation-sheet', [
            'order' => $order,
            'sheet' => $sheet,
            'computationForm' => $computationForm ?? null,
            'showSupplierSelection' => $showSupplierSelection ?? false,
            'hasSavedAllocations' => $hasSavedAllocations ?? false,
            'showAllocationForm' => $showAllocationForm ?? ($showSupplierSelection ?? false),
        ])

        @if(($showLpoWorkflow ?? false) && ! ($showSupplierSelection ?? false))
            @include('inventory.partials.rfq-lpo-workflow', [
                'order' => $order,
                'sheet' => $sheet,
                'showEvaluationCommittee' => $showEvaluationCommittee ?? false,
                'acceptedWithoutLpoCount' => $acceptedWithoutLpoCount ?? 0,
            ])
        @endif
    </div>
</div>
</x-app-layout>
