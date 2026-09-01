<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('inventory.partials.subnav')

        <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 tracking-tight">Crash Carts</h1>
                <p class="mt-1 text-sm text-gray-500">
                    Fixed manifest per cart. Break seal, then record usage on the cart view.
                </p>
            </div>
        </div>

        @if (session('status'))
            <div class="mt-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        <div class="mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            @if ($carts->isEmpty())
                <div class="px-6 py-12 text-center">
                    <p class="text-sm font-medium text-gray-900">No crash carts configured</p>
                    <p class="mt-1 text-sm text-gray-500">
                        Create a Satellite {{ strtolower(inventory_label('store')) }} with role
                        <span class="font-medium">Crash cart</span> under Manage Stores.
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">Cart</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">End {{ strtolower(inventory_label('store')) }}</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">Branch</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-600">Seal</th>
                                <th scope="col" class="px-4 py-3 text-right font-medium text-gray-600">Manifest items</th>
                                <th scope="col" class="px-4 py-3 text-right font-medium text-gray-600"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @foreach ($carts as $cart)
                                @php
                                    $badgeColor = match ($cart->crashCartStatusBadgeColor()) {
                                        'success' => 'bg-green-100 text-green-800 ring-green-200',
                                        'danger' => 'bg-red-100 text-red-800 ring-red-200',
                                        default => 'bg-gray-100 text-gray-700 ring-gray-200',
                                    };
                                @endphp
                                <tr class="hover:bg-gray-50/80">
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $cart->name }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $cart->parent?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $cart->branch?->name ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $badgeColor }}">
                                            {{ $cart->crashCartStatusLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-gray-600">
                                        {{ $cart->crash_cart_seal_number ?: '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-700">
                                        {{ $cart->crash_cart_items_count }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('inventory.crash-carts.show', $cart) }}"
                                           class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
</x-app-layout>
