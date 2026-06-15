<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <a href="{{ route('inventory.orders.show', $order) }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to {{ $order->order_number }}</a>

        <h2 class="mt-4 text-2xl font-bold text-gray-900">Receive goods</h2>
        <p class="mt-1 text-sm text-gray-500">This order has items from multiple suppliers. Choose which delivery you are recording.</p>

        @include('inventory.partials.subnav')

        <div class="mt-6 space-y-3">
            @foreach($receiptOptions as $option)
                <a href="{{ route('inventory.orders.receive', ['order' => $order, 'supplier_id' => $option['supplier_id'] ?? 0]) }}"
                   class="block bg-white shadow sm:rounded-lg p-4 hover:ring-2 hover:ring-blue-500 transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium text-gray-900">{{ $option['supplier_name'] }}</p>
                            <p class="text-sm text-gray-500 mt-0.5">
                                {{ $option['lines_count'] }} item(s) · {{ number_format($option['remaining_suom'], 0) }} SUOM remaining
                            </p>
                        </div>
                        <span class="text-sm text-blue-600 font-medium">Create GRN →</span>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</div>
</x-app-layout>
