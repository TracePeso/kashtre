<x-app-layout>
    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h1 class="text-xl font-semibold text-gray-900">Crash Carts</h1>
                <p class="mt-1 text-sm text-gray-600">Specialized satellite stores. Manage lifecycle on Manage Stores; record usage under Record Usage.</p>
            </div>

            <div class="overflow-hidden bg-white shadow sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-600">Cart</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-600">Parent End Store</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-600">Status</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-600">Seal</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-600"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($carts as $cart)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $cart->name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $cart->parent?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium uppercase text-gray-700">
                                        {{ $cart->crash_cart_status ?? 'ready' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $cart->crash_cart_seal_number ?: '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('stores.index') }}" class="text-blue-600 hover:underline">Manage on Stores</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                    No crash carts yet. Create a Satellite store marked as crash cart under Manage Stores.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
