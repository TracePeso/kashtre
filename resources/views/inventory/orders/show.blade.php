<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <a href="{{ route('inventory.orders.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to order forms</a>
                <h2 class="mt-2 text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">{{ $order->order_number }}</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $order->store->selectLabel() }} · {{ ucfirst($order->status) }} ·
                    {{ $order->moving_average_days }}-day avg · {{ $order->period_of_order_days ?? '—' }} day order period
                    @if($order->importance_filter)
                        · {{ \App\Models\Item::importanceOptions()[$order->importance_filter] ?? $order->importance_filter }} only
                    @endif
                </p>
            </div>
            @if($order->isDraft())
                <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
                    <form action="{{ route('inventory.orders.regenerate', $order) }}" method="POST">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Refresh lines
                        </button>
                    </form>
                    <form action="{{ route('inventory.orders.submit', $order) }}" method="POST"
                          onsubmit="return confirm('Submit this order form?');">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                            Submit order
                        </button>
                    </form>
                </div>
            @endif
        </div>

        @include('inventory.partials.subnav')

        @if(session('success'))
            <div class="mt-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
        @endif

        @if(session('warning'))
            <div class="mt-4 bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded text-sm">{{ session('warning') }}</div>
        @endif

        @if(!empty($emptyOrderReason))
            <div class="mt-4 bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded text-sm">
                <strong>No order lines were generated.</strong> {{ $emptyOrderReason }}
                @if($order->isDraft())
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('inventory.orders.create') }}"
                           class="inline-flex items-center px-3 py-1.5 border border-amber-300 rounded-md text-sm font-medium text-amber-900 bg-white hover:bg-amber-50">
                            Create new order
                        </a>
                    </div>
                @endif
            </div>
        @endif

        @if($order->notes)
            <div class="mt-4 bg-white shadow sm:rounded-lg p-4 text-sm text-gray-600">
                <strong class="text-gray-900">Notes:</strong> {{ $order->notes }}
            </div>
        @endif

        <div class="mt-6 bg-white shadow sm:rounded-lg p-6">
            @livewire('inventory.edit-inventory-order-lines', ['order' => $order], key('order-'.$order->id))
        </div>
    </div>
</div>
</x-app-layout>
