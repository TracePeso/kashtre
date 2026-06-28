@php
    $supplierId = $order->supplier_id;
    $quotation = $supplierId ? $order->supplierQuotations->firstWhere('supplier_id', $supplierId) : null;
@endphp

<div class="border border-gray-200 rounded-lg p-4 space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h4 class="text-sm font-semibold text-gray-900">{{ $order->supplier?->name ?? 'RFQ supplier' }}</h4>
            <p class="text-xs text-gray-500">{{ $order->lines->count() }} RFQ line(s)</p>
        </div>
        @if($quotation)
            <span @class([
                'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
                'bg-gray-100 text-gray-700' => $quotation->status === 'draft',
                'bg-blue-100 text-blue-800' => $quotation->status === 'received',
                'bg-green-100 text-green-800' => $quotation->status === 'accepted',
                'bg-red-100 text-red-800' => $quotation->status === 'rejected',
            ])>{{ $quotation->statusLabel() }}</span>
        @endif
    </div>

    @if($quotation && $quotation->purchaseOrder)
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('inventory.purchase-orders.show', $quotation->purchaseOrder) }}"
               class="text-sm font-medium text-blue-600 hover:text-blue-800">
                Open {{ $quotation->purchaseOrder->po_number }}
            </a>
            <span class="text-gray-300">·</span>
            <span class="text-sm text-gray-600">{{ $quotation->purchaseOrder->statusLabel() }}</span>
        </div>
    @elseif($quotation && $quotation->isAccepted())
        <form action="{{ route('inventory.quotations.purchase-order', $quotation) }}" method="POST">
            @csrf
            <button type="submit" class="inline-flex items-center px-3 py-1.5 border border-transparent rounded-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                Generate LPO
            </button>
        </form>
    @elseif($quotation && ! $quotation->isAccepted())
        <div class="flex flex-wrap gap-2">
            @if($quotation->canAccept())
                <form action="{{ route('inventory.quotations.accept', $quotation) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700">Accept quotation</button>
                </form>
            @endif
            @if($quotation->status !== 'rejected')
                <form action="{{ route('inventory.quotations.reject', $quotation) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-md hover:bg-red-100">Reject</button>
                </form>
            @endif
            <p class="text-xs text-gray-500 w-full">Total quoted: UGX {{ number_format((float) $quotation->total_amount, 2) }}</p>
        </div>
    @elseif($supplierId)
        <form action="{{ route('inventory.orders.quotations.store', $order) }}" method="POST" class="space-y-3">
            @csrf
            <input type="hidden" name="supplier_id" value="{{ $supplierId }}">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700">Supplier reference</label>
                    <input type="text" name="reference_number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Notes</label>
                    <input type="text" name="notes" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="py-1 pr-2">Item</th>
                            <th class="py-1 pr-2 text-right">RFQ qty</th>
                            <th class="py-1 pr-2 text-right">Quoted qty</th>
                            <th class="py-1 text-right">Unit price</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->lines as $index => $line)
                            <tr>
                                <td class="py-1 pr-2">{{ $line->item?->name }}</td>
                                <td class="py-1 pr-2 text-right tabular-nums">{{ number_format((float) $line->order_quantity_suom, 0) }}</td>
                                <td class="py-1 pr-2">
                                    <input type="hidden" name="lines[{{ $index }}][inventory_order_line_id]" value="{{ $line->id }}">
                                    <input type="number" step="1" min="0" name="lines[{{ $index }}][quoted_quantity_suom]"
                                           value="{{ number_format((float) $line->order_quantity_suom, 0, '.', '') }}"
                                           class="w-24 rounded border-gray-300 text-right text-sm">
                                </td>
                                <td class="py-1">
                                    <input type="number" step="0.01" min="0" name="lines[{{ $index }}][unit_price]"
                                           value="{{ number_format((float) $line->unit_price, 2, '.', '') }}"
                                           class="w-28 rounded border-gray-300 text-right text-sm">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="submit" class="inline-flex items-center px-3 py-1.5 border border-transparent rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                Save supplier quotation
            </button>
        </form>
    @else
        <p class="text-xs text-amber-700">This RFQ has no supplier assigned.</p>
    @endif
</div>
