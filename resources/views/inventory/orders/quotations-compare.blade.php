<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <a href="{{ route('inventory.orders.show', $order) }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to {{ $order->order_number }}</a>
                <h2 class="mt-2 text-2xl font-bold text-gray-900">Quotation analysis</h2>
                <p class="mt-1 text-sm text-gray-500">Comparative computation sheet for {{ $order->order_number }}. Accept one or more suppliers, then generate LPOs.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($order->purchaseOrders->isNotEmpty())
                    <a href="{{ route('inventory.purchase-orders.index') }}"
                       class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 hover:bg-indigo-100">
                        View all LPOs ({{ $order->purchaseOrders->count() }})
                    </a>
                @endif
                <form action="{{ route('inventory.orders.purchase-orders.generate-accepted', $order) }}" method="POST">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                        Generate LPOs for all accepted
                    </button>
                </form>
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
                        <p class="text-xs text-gray-500 mt-0.5">Open an LPO to review, issue (email supplier), or receive goods.</p>
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
                    @if($order->hasRfqDocument())
                        <a href="{{ $order->rfqDocumentUrl() }}"
                           target="_blank"
                           class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 hover:bg-blue-100">
                            Download RFQ document
                        </a>
                    @endif
                    <a href="{{ route('inventory.orders.pdf', $order) }}"
                       class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium text-slate-700 bg-white border border-gray-300 hover:bg-gray-50">
                        Download RFQ PDF
                    </a>
                </div>
            </div>
            <div class="px-5 py-4">
                <form action="{{ route('inventory.orders.rfq-suppliers.invite', $order) }}" method="POST" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-48 overflow-y-auto">
                        @foreach($availableSuppliers as $supplier)
                            @php
                                $already = $rfqSuppliers->contains(fn ($row) => (int) $row['supplier_id'] === (int) $supplier->id);
                            @endphp
                            <label class="flex items-start gap-2 text-sm text-gray-800">
                                <input type="checkbox" name="supplier_ids[]" value="{{ $supplier->id }}"
                                       @checked($already)
                                       class="mt-1 rounded border-gray-300 text-blue-600">
                                <span>
                                    {{ $supplier->name }}
                                    <span class="block text-xs text-gray-500">{{ $supplier->email ?: 'No email — download RFQ to share' }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="submit" class="px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">Save invited suppliers</button>
                        <a href="{{ route('inventory.orders.pdf', $order) }}"
                           class="px-3 py-1.5 text-sm font-medium text-slate-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                            Download RFQ PDF
                        </a>
                    </div>
                </form>
            </div>
        </section>

        <section class="bg-white shadow sm:rounded-lg overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-gray-900">Computation sheet</h3>
                <p class="text-xs text-gray-500">Lowest purchase price per line is highlighted.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Item</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">RFQ qty</th>
                            @foreach($sheet['suppliers'] as $sup)
                                <th class="px-3 py-2 text-right font-medium text-gray-600">
                                    {{ $sup['supplier_name'] }}
                                    <span class="block text-[10px] font-normal text-gray-400">{{ $sup['status_label'] }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($sheet['lines'] as $row)
                            <tr>
                                <td class="px-3 py-2">
                                    <div class="font-medium text-gray-900">{{ $row['item_name'] }}</div>
                                    @if($row['item_code'])
                                        <div class="text-xs text-gray-500">{{ $row['item_code'] }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row['rfq_qty'], 0) }}</td>
                                @foreach($sheet['suppliers'] as $sup)
                                    @php($q = $row['quotes'][$sup['supplier_id']] ?? null)
                                    <td @class([
                                        'px-3 py-2 text-right tabular-nums',
                                        'bg-emerald-50 font-semibold text-emerald-900' => $q && $sup['supplier_id'] === $row['best_supplier_id'],
                                    ])>
                                        @if($q && $q['unit_price'] !== null)
                                            {{ number_format($q['unit_price'], 2) }}
                                            <span class="block text-[10px] text-gray-500 font-normal">qty {{ number_format($q['quoted_qty'] ?? 0, 0) }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 2 + count($sheet['suppliers']) }}" class="px-3 py-6 text-center text-gray-500">No RFQ lines.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if(count($sheet['suppliers']) > 0)
                        <tfoot class="border-t border-gray-200 bg-slate-50">
                            <tr>
                                <td class="px-3 py-2 font-medium" colspan="2">Quoted total</td>
                                @foreach($sheet['suppliers'] as $sup)
                                    <td class="px-3 py-2 text-right font-mono font-semibold tabular-nums">
                                        UGX {{ number_format($sup['total_amount'], 2) }}
                                    </td>
                                @endforeach
                            </tr>
                            <tr>
                                <td class="px-3 py-2 font-medium" colspan="2">Actions</td>
                                @foreach($sheet['suppliers'] as $sup)
                                    <td class="px-3 py-2 text-right space-y-1">
                                        @if($sup['has_lpo'])
                                            <span class="text-xs text-gray-500">LPO exists</span>
                                        @elseif($sup['is_accepted'])
                                            <form action="{{ route('inventory.quotations.purchase-order', $sup['quotation_id']) }}" method="POST" class="inline">
                                                @csrf
                                                <button class="text-xs font-medium text-indigo-700 hover:underline">Generate LPO</button>
                                            </form>
                                        @elseif($sup['can_accept'])
                                            <form action="{{ route('inventory.quotations.accept', $sup['quotation_id']) }}" method="POST" class="inline">
                                                @csrf
                                                <button class="text-xs font-medium text-green-700 hover:underline">Accept</button>
                                            </form>
                                            <form action="{{ route('inventory.quotations.reject', $sup['quotation_id']) }}" method="POST" class="inline">
                                                @csrf
                                                <button class="text-xs font-medium text-red-600 hover:underline">Reject</button>
                                            </form>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
            @if(count($sheet['suppliers']) < 1)
                <p class="px-5 py-4 text-sm text-gray-500">No quotations recorded yet. Invite suppliers, then enter each quote below.</p>
            @endif
        </section>

        <section class="bg-white shadow sm:rounded-lg overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900">Record a supplier quotation</h3>
            </div>
            <div class="px-5 py-4 space-y-4">
                @forelse($rfqSuppliers as $row)
                    @php($quotation = $row['quotation'] ?? null)
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $row['supplier_name'] }}</p>
                                <p class="text-xs text-gray-500">{{ $row['email'] ?: 'No email on file' }}</p>
                            </div>
                            @if($quotation)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-blue-50 text-blue-800">{{ $quotation->statusLabel() }}</span>
                            @endif
                        </div>
                        @if($quotation && $quotation->isAccepted())
                            <p class="text-sm text-gray-600">Accepted — use Generate LPO above.</p>
                        @else
                            <form action="{{ route('inventory.orders.quotations.store', $order) }}" method="POST" class="space-y-3">
                                @csrf
                                <input type="hidden" name="supplier_id" value="{{ $row['supplier_id'] }}">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700">Supplier reference</label>
                                        <input type="text" name="reference_number" value="{{ $quotation?->reference_number }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700">Notes</label>
                                        <input type="text" name="notes" value="{{ $quotation?->notes }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    </div>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-xs">
                                        <thead>
                                            <tr class="text-left text-gray-500">
                                                <th class="py-1 pr-2">Item</th>
                                                <th class="py-1 pr-2 text-right">RFQ qty</th>
                                                <th class="py-1 pr-2 text-right">Quoted qty</th>
                                                <th class="py-1 text-right">Purchase price</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($order->lines as $index => $line)
                                                @php($existing = $quotation?->lines?->firstWhere('inventory_order_line_id', $line->id))
                                                <tr>
                                                    <td class="py-1 pr-2">{{ $line->item?->name }}</td>
                                                    <td class="py-1 pr-2 text-right tabular-nums">{{ number_format((float) $line->order_quantity_suom, 0) }}</td>
                                                    <td class="py-1 pr-2">
                                                        <input type="hidden" name="lines[{{ $index }}][inventory_order_line_id]" value="{{ $line->id }}">
                                                        <input type="number" step="1" min="0" name="lines[{{ $index }}][quoted_quantity_suom]"
                                                               value="{{ number_format((float) ($existing->quoted_quantity_suom ?? $line->order_quantity_suom), 0, '.', '') }}"
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
    </div>
</div>
</x-app-layout>
