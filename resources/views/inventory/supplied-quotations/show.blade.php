<x-app-layout>
@php
    $buyer = $quotation->inventoryOrder?->business;
    $order = $quotation->inventoryOrder;
    $statusColors = match ($outcomeLabel) {
        'RFQ open' => 'bg-amber-100 text-amber-800',
        'Quotation submitted' => 'bg-blue-100 text-blue-800',
        'Quote accepted', 'LPO issued' => 'bg-green-100 text-green-800',
        'Quote not selected', 'Buyer cancelled' => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-800',
    };
@endphp
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <a href="{{ route('inventory.supplied-quotations.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to supplied quotations</a>
                <h2 class="mt-2 text-2xl font-bold text-gray-900">{{ $quotation->reference_number ?: 'Quotation' }}</h2>
                <p class="mt-1 text-sm text-gray-500">
                    RFQ {{ $order?->order_number }} · {{ $buyer?->name }}
                    @if($buyer?->entity_code)
                        · {{ $buyer->entity_code }}
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors }}">
                    {{ $outcomeLabel }}
                </span>
                @if($canUpdate && $invitation)
                    <a href="{{ route('inventory.incoming-rfqs.show', $invitation) }}"
                       class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                        Update quotation
                    </a>
                @endif
            </div>
        </div>

        @include('inventory.partials.subnav')

        <section class="bg-white shadow sm:rounded-lg p-5">
            <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                <div>
                    <dt class="text-gray-500">Your reference</dt>
                    <dd class="font-medium text-gray-900">{{ $quotation->reference_number ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Submitted</dt>
                    <dd class="font-medium text-gray-900">{{ $quotation->received_at?->format('M d, Y g:i A') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Total</dt>
                    <dd class="font-medium text-gray-900 tabular-nums">UGX {{ number_format((float) $quotation->total_amount, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">LPO</dt>
                    <dd class="font-medium text-gray-900">{{ $quotation->purchaseOrder?->po_number ?: '—' }}</dd>
                </div>
            </dl>
            @if($quotation->notes)
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Your notes</p>
                    <p class="mt-1 text-sm text-gray-800 whitespace-pre-wrap">{{ $quotation->notes }}</p>
                </div>
            @endif
        </section>

        <section class="bg-white shadow sm:rounded-lg overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900">Quoted line items</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-600">Item</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-600">RFQ qty</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-600">Quoted qty</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-600">Unit price</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-600">Line total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($quotation->lines as $line)
                            @php($orderLine = $line->inventoryOrderLine)
                            <tr>
                                <td class="px-4 py-2">
                                    <div class="font-medium text-gray-900">{{ $orderLine?->item?->name }}</div>
                                    @if($orderLine?->item?->code)
                                        <div class="text-xs text-gray-500">{{ $orderLine->item->code }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $orderLine ? number_format((float) $orderLine->order_quantity_suom, 0) : '—' }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $line->quoted_quantity_suom, 0) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $line->unit_price, 2) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $line->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-gray-200 bg-slate-50">
                        <tr>
                            <td colspan="4" class="px-4 py-2 text-right font-medium text-gray-700">Quotation total</td>
                            <td class="px-4 py-2 text-right font-semibold tabular-nums">UGX {{ number_format((float) $quotation->total_amount, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        @if($invitation)
            <div class="text-sm text-gray-600">
                <a href="{{ route('inventory.incoming-rfqs.show', $invitation) }}" class="text-blue-600 hover:text-blue-800">View full RFQ details →</a>
            </div>
        @endif
    </div>
</div>
</x-app-layout>
