<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <a href="{{ route('inventory.orders.show', $order) }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to {{ $order->order_number }}</a>
        <h2 class="mt-4 text-xl font-bold text-gray-900">Receive goods — choose LPO</h2>
        <p class="text-sm text-gray-500 mt-1">This RFQ has multiple issued LPOs. Select which delivery you are recording.</p>

        <ul class="mt-6 space-y-3">
            @foreach($issuedPos as $po)
                <li>
                    <a href="{{ route('inventory.purchase-orders.receive', $po) }}"
                       class="block bg-white border border-gray-200 rounded-lg px-4 py-3 hover:border-blue-300 hover:shadow-sm">
                        <p class="font-medium text-gray-900">{{ $po->po_number }}</p>
                        <p class="text-sm text-gray-500">{{ $po->supplier?->name }} · UGX {{ number_format((float) $po->total_amount, 0) }}</p>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</div>
</x-app-layout>
