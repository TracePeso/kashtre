<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div>
            <a href="{{ route('inventory.orders.quotations.compare', $order) }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to quotation analysis</a>
            <h2 class="mt-3 text-2xl font-bold text-gray-900">Review LPOs before generating</h2>
            <p class="mt-1 text-sm text-gray-500">
                RFQ {{ $order->order_number }} · Deliver to <strong>{{ $preview['store_name'] }}</strong>
            </p>
        </div>

        @if($errors->any())
            <div class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="rounded-lg border border-indigo-100 bg-indigo-50/60 px-5 py-4 flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-indigo-950">
                    {{ $preview['lpo_count'] }} draft {{ Str::plural('LPO', $preview['lpo_count']) }} will be created
                </p>
                <p class="text-xs text-indigo-800 mt-0.5">
                    One purchase order per supplier. Review each supplier’s lines below, then confirm to create draft LPOs.
                </p>
            </div>
            <p class="text-lg font-semibold text-indigo-950 tabular-nums">
                Total UGX {{ number_format($preview['grand_total'], 2) }}
            </p>
        </div>

        <div class="space-y-5">
            @foreach($preview['suppliers'] as $supplierPreview)
                <section @class([
                    'bg-white shadow sm:rounded-lg overflow-hidden border',
                    'border-slate-200' => $supplierPreview['will_create'],
                    'border-amber-200' => ! $supplierPreview['will_create'],
                ])>
                    <div @class([
                        'px-5 py-4 border-b flex flex-wrap items-start justify-between gap-3',
                        'border-slate-200 bg-slate-50/80' => $supplierPreview['will_create'],
                        'border-amber-100 bg-amber-50/80' => ! $supplierPreview['will_create'],
                    ])>
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-gray-900">{{ $supplierPreview['supplier_name'] }}</h3>
                            <p class="text-xs text-gray-500 mt-1">
                                @if($supplierPreview['supplier_email'])
                                    LPO will be sent to <span class="font-medium text-gray-700">{{ $supplierPreview['supplier_email'] }}</span> when issued
                                @else
                                    <span class="text-amber-700">No supplier email — share the LPO PDF manually after issuing</span>
                                @endif
                                @if($supplierPreview['quotation_reference'])
                                    · Quote ref {{ $supplierPreview['quotation_reference'] }}
                                @endif
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            @if($supplierPreview['will_create'])
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                    New draft LPO
                                </span>
                                <p class="mt-1 text-sm font-semibold text-gray-900 tabular-nums">
                                    UGX {{ number_format($supplierPreview['total_amount'], 2) }}
                                </p>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-900">
                                    Already exists — will skip
                                </span>
                                @if($supplierPreview['existing_po_id'])
                                    <p class="mt-1 text-sm">
                                        <a href="{{ route('inventory.purchase-orders.show', $supplierPreview['existing_po_id']) }}"
                                           class="font-medium text-indigo-700 hover:text-indigo-900">
                                            {{ $supplierPreview['existing_po_number'] }}
                                        </a>
                                    </p>
                                @endif
                            @endif
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 border-b border-gray-100 text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-2.5 text-left font-medium">Item</th>
                                    <th class="px-4 py-2.5 text-right font-medium">Units</th>
                                    <th class="px-4 py-2.5 text-right font-medium">Price</th>
                                    <th class="px-4 py-2.5 text-right font-medium">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($supplierPreview['lines'] as $line)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900">{{ $line['item_name'] }}</div>
                                            @if(! empty($line['item_code']))
                                                <div class="text-xs text-gray-500">{{ $line['item_code'] }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right tabular-nums">
                                            {{ number_format($line['quantity'], $line['qty_decimals']) }}
                                        </td>
                                        <td class="px-4 py-3 text-right tabular-nums">
                                            {{ number_format($line['unit_price'], 2) }}
                                        </td>
                                        <td class="px-4 py-3 text-right tabular-nums font-medium">
                                            {{ number_format($line['line_total'], 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 border-t border-gray-100">
                                <tr>
                                    <td colspan="3" class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        Supplier total
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-semibold tabular-nums text-gray-900">
                                        UGX {{ number_format($supplierPreview['total_amount'], 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4 pt-2">
            <a href="{{ route('inventory.orders.quotations.compare', $order) }}"
               class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                Back to allocation
            </a>
            @if($preview['lpo_count'] > 0)
                <form action="{{ route('inventory.orders.purchase-orders.generate-awards', $order) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center px-5 py-2.5 rounded-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                        Confirm &amp; create {{ $preview['lpo_count'] }} draft {{ Str::plural('LPO', $preview['lpo_count']) }}
                    </button>
                </form>
            @else
                <p class="text-sm text-amber-800">All supplier LPOs already exist — nothing new to create.</p>
            @endif
        </div>
    </div>
</div>
</x-app-layout>
