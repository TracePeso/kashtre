<x-app-layout>
    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h1 class="text-xl font-semibold text-gray-900">Crash Carts</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Specialized satellite {{ strtolower(inventory_label('store')) }} nodes. Deploy during emergencies (no documentation), then reconcile via {{ inventory_label('usage_record') }}, seal, and return to Ready.
                </p>
            </div>

            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="overflow-hidden bg-white shadow sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-600">Cart</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-600">Parent End {{ inventory_label('store') }}</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-600">Status</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-600">Seal</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($carts as $cart)
                            @php $status = $cart->crash_cart_status ?? 'ready'; @endphp
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $cart->name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $cart->parent?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium uppercase text-gray-700">
                                        {{ $status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $cart->crash_cart_seal_number ?: '—' }}</td>
                                <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                                    @if ($status === 'ready')
                                        <form method="POST" action="{{ route('inventory.crash-carts.deploy', $cart) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="rounded-md bg-red-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-red-700">
                                                Deploy
                                            </button>
                                        </form>
                                    @elseif ($status === 'deployed')
                                        <span class="mr-2 text-xs text-amber-700">Emergency — no inventory docs</span>
                                        <form method="POST" action="{{ route('inventory.crash-carts.reconcile', $cart) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="rounded-md bg-amber-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-amber-700">
                                                Start reconcile
                                            </button>
                                        </form>
                                    @elseif ($status === 'reconciling')
                                        <a href="{{ route('inventory.usage.index') }}" class="text-xs text-blue-600 hover:underline">Record usage</a>
                                        <form method="POST" action="{{ route('inventory.crash-carts.ready', $cart) }}" class="inline-flex items-center gap-1">
                                            @csrf
                                            <input type="text" name="seal_number" required placeholder="New seal #"
                                                   class="rounded border-gray-300 text-xs w-28">
                                            <button type="submit" class="rounded-md bg-green-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-green-700">
                                                Seal Ready
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                    No crash carts yet. Create a Satellite {{ strtolower(inventory_label('store')) }} marked as crash cart under Manage Stores.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
