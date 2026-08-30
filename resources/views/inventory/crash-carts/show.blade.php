<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <a href="{{ route('inventory.crash-carts.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to Crash Carts</a>

        <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 tracking-tight">{{ $cart->name }}</h1>
                <p class="mt-1 text-sm text-gray-500">
                    End {{ strtolower(inventory_label('store')) }}:
                    <span class="font-medium text-gray-700">{{ $cart->parent?->name ?? '—' }}</span>
                    @if ($cart->branch?->name)
                        <span class="text-gray-400">&middot;</span>
                        {{ $cart->branch->name }}
                    @endif
                </p>
            </div>
        </div>

        @include('inventory.partials.subnav')

        <div class="mt-6">
            @livewire('inventory.show-crash-cart', ['cart' => $cart], key('crash-cart-'.$cart->id))
        </div>
    </div>
</div>
</x-app-layout>
