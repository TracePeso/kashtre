<x-app-layout>
@php
    $buyer = $order->business;
    $statusColors = match ($statusLabel) {
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
                <a href="{{ route('inventory.incoming-rfqs.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to incoming RFQs</a>
                <h2 class="mt-2 text-2xl font-bold text-gray-900">{{ $order->order_number }}</h2>
                <p class="mt-1 text-sm text-gray-500">
                    From <strong>{{ $buyer?->name }}</strong>
                    @if($buyer?->entity_code)
                        · {{ $buyer->entity_code }}
                    @endif
                    · {{ $order->store?->name ?? '—' }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors }}">
                    {{ $statusLabel }}
                </span>
                @if($order->canManageSupplierQuotations() && $order->canDownloadRfqPdf())
                    <a href="{{ route('inventory.incoming-rfqs.pdf', $invitation) }}"
                       class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 hover:bg-indigo-100">
                        Download RFQ PDF
                    </a>
                @endif
            </div>
        </div>

        @include('inventory.partials.subnav')

        @if(session('success'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        @if($order->isPendingApproval())
            <div class="rounded-md bg-slate-50 border border-slate-200 px-4 py-3 text-sm text-slate-800">
                <strong>Invited.</strong> {{ $buyer?->name }} has added you to this purchase request. You will be able to submit a quotation once their approvers sign off and the RFQ opens.
            </div>
        @elseif($order->canManageSupplierQuotations() && ! $quotation)
            <div class="rounded-md bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
                <strong>RFQ open.</strong> Submit your quoted quantities and prices below.
            </div>
        @endif

        <section class="bg-white shadow sm:rounded-lg overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900">RFQ line items</h3>
                <p class="text-xs text-gray-500 mt-0.5">Quantities requested by {{ $buyer?->name }}.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-600">Item</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-600">RFQ qty</th>
                            @if($quotation)
                                <th class="px-4 py-2 text-right font-medium text-gray-600">Your quote</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">Unit price</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">Line total</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($order->lines as $line)
                            @php
                                $quoted = $quotation?->lines?->firstWhere('inventory_order_line_id', $line->id);
                            @endphp
                            <tr>
                                <td class="px-4 py-2">
                                    <div class="font-medium text-gray-900">{{ $line->item?->name }}</div>
                                    @if($line->item?->code)
                                        <div class="text-xs text-gray-500">{{ $line->item->code }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $line->order_quantity_suom, 0) }}</td>
                                @if($quotation)
                                    <td class="px-4 py-2 text-right tabular-nums">{{ $quoted ? number_format((float) $quoted->quoted_quantity_suom, 0) : '—' }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ $quoted ? number_format((float) $quoted->unit_price, 2) : '—' }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ $quoted ? number_format((float) $quoted->line_total, 2) : '—' }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                    @if($quotation)
                        <tfoot class="border-t border-gray-200 bg-slate-50">
                            <tr>
                                <td colspan="4" class="px-4 py-2 text-right font-medium text-gray-700">Quotation total</td>
                                <td class="px-4 py-2 text-right font-semibold tabular-nums">UGX {{ number_format((float) $quotation->total_amount, 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
            @if($order->notes)
                <div class="px-5 py-4 border-t border-gray-100 bg-gray-50/80">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Buyer notes</p>
                    <p class="mt-1 text-sm text-gray-800 whitespace-pre-wrap">{{ $order->notes }}</p>
                </div>
            @endif
        </section>

        @if($quotation && ! $canSubmitQuotation)
            <section class="bg-white shadow sm:rounded-lg p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">Your quotation</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Track outcome under Inventory → Supplied quotations.</p>
                    </div>
                    <a href="{{ route('inventory.supplied-quotations.show', $quotation) }}"
                       class="text-sm font-medium text-blue-600 hover:text-blue-800">
                        View in supplied quotations →
                    </a>
                </div>
                <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-gray-500">Reference</dt>
                        <dd class="font-medium text-gray-900">{{ $quotation->reference_number ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Status</dt>
                        <dd class="font-medium text-gray-900">{{ $quotation->statusLabel() }}</dd>
                    </div>
                    @if($quotation->purchaseOrder)
                        <div class="sm:col-span-2">
                            <dt class="text-gray-500">LPO</dt>
                            <dd class="font-medium text-indigo-700">{{ $quotation->purchaseOrder->po_number }}</dd>
                        </div>
                    @endif
                </dl>
            </section>
        @endif

        @if($canSubmitQuotation && $quotation)
            <div class="text-sm text-gray-600">
                <a href="{{ route('inventory.supplied-quotations.show', $quotation) }}" class="text-blue-600 hover:text-blue-800">View in supplied quotations →</a>
            </div>
        @endif

        @if($canSubmitQuotation)
            <section class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-900">{{ $quotation ? 'Update quotation' : 'Submit quotation' }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Your response is sent to {{ $buyer?->name }} for evaluation.</p>
                </div>
                <form action="{{ route('inventory.incoming-rfqs.quotation.store', $invitation) }}" method="POST" class="px-5 py-4 space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700">Your reference</label>
                            <input type="text" name="reference_number" value="{{ old('reference_number', $quotation?->reference_number) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700">Notes</label>
                            <input type="text" name="notes" value="{{ old('notes', $quotation?->notes) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1 pr-2">Item</th>
                                    <th class="py-1 pr-2 text-right">RFQ qty</th>
                                    <th class="py-1 pr-2 text-right">Quoted qty</th>
                                    <th class="py-1 pr-2 text-right">Unit price (UGX)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($order->lines as $index => $line)
                                    @php($existing = $quotation?->lines?->firstWhere('inventory_order_line_id', $line->id))
                                    <tr>
                                        <td class="py-2 pr-2">{{ $line->item?->name }}</td>
                                        <td class="py-2 pr-2 text-right tabular-nums">{{ number_format((float) $line->order_quantity_suom, 0) }}</td>
                                        <td class="py-2 pr-2">
                                            <input type="hidden" name="lines[{{ $index }}][inventory_order_line_id]" value="{{ $line->id }}">
                                            <input type="number" step="1" min="0" name="lines[{{ $index }}][quoted_quantity_suom]"
                                                   value="{{ old('lines.'.$index.'.quoted_quantity_suom', number_format((float) ($existing->quoted_quantity_suom ?? $line->order_quantity_suom), 0, '.', '')) }}"
                                                   class="w-24 rounded border-gray-300 text-right text-sm">
                                        </td>
                                        <td class="py-2">
                                            <input type="number" step="0.01" min="0" name="lines[{{ $index }}][unit_price]"
                                                   value="{{ old('lines.'.$index.'.unit_price', number_format((float) ($existing->unit_price ?? 0), 2, '.', '')) }}"
                                                   class="w-28 rounded border-gray-300 text-right text-sm">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                        {{ $quotation ? 'Update quotation' : 'Submit quotation' }}
                    </button>
                </form>
            </section>
        @endif
    </div>
</div>
</x-app-layout>
