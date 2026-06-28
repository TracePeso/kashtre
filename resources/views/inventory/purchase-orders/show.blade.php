<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <a href="{{ route('inventory.orders.show', $po->inventoryOrder) }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to RFQ {{ $po->inventoryOrder?->order_number }}</a>

        <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">{{ $po->po_number }}</h2>
                <p class="text-sm text-gray-500 mt-1">{{ $po->supplier?->name }} · {{ $po->store?->name }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('inventory.purchase-orders.pdf', $po) }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Export PDF
                </a>
                @if($po->status === \App\Models\InventoryPurchaseOrder::STATUS_DRAFT)
                    <form action="{{ route('inventory.purchase-orders.issue', $po) }}" method="POST">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                            Issue LPO & email copies
                        </button>
                    </form>
                @elseif($po->canReceiveGoods())
                    <a href="{{ route('inventory.purchase-orders.receive', $po) }}"
                       class="inline-flex items-center px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                        Receive goods
                    </a>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="mt-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded text-sm">
                <ul class="list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="mt-6 bg-white shadow sm:rounded-lg overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 flex justify-between items-center">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">{{ $po->statusLabel() }}</span>
                <p class="text-sm font-semibold text-gray-900">Total: UGX {{ number_format((float) $po->total_amount, 2) }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Item</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-500">Qty</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-500">Received</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-500">Unit price</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-500">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($po->lines as $line)
                            <tr>
                                <td class="px-4 py-2">{{ $line->item?->name }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $line->quantity_suom, 0) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $line->received_quantity_suom, 0) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $line->unit_price, 2) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $line->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</x-app-layout>
