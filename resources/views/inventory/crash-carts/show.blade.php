@php
    $status = $cart->crash_cart_status ?? 'ready';
    $isSealed = $status === 'ready';
    $isOpen = $status === 'open';
    $badgeColor = match ($cart->crashCartStatusBadgeColor()) {
        'success' => 'bg-green-100 text-green-800 ring-green-200',
        'danger' => 'bg-red-100 text-red-800 ring-red-200',
        default => 'bg-gray-100 text-gray-700 ring-gray-200',
    };
@endphp
<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
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
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $badgeColor }}">
                {{ $cart->crashCartStatusLabel() }}
            </span>
        </div>

        @include('inventory.partials.subnav')

        @if (session('status'))
            <div class="mt-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        {{-- Summary --}}
        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Seal</p>
                <p class="mt-1 font-mono text-sm text-gray-900">{{ $cart->crash_cart_seal_number ?: '—' }}</p>
                @if ($cart->crash_cart_sealed_at && $isSealed)
                    <p class="mt-0.5 text-xs text-gray-500">Sealed {{ $cart->crash_cart_sealed_at->format('d M Y H:i') }}</p>
                @endif
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Manifest lines</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{{ $cart->crashCartItems->count() }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Seal broken</p>
                <p class="mt-1 text-sm text-gray-900">
                    @if ($isOpen && $cart->crash_cart_deployed_at)
                        {{ $cart->crash_cart_deployed_at->format('d M Y H:i') }}
                    @else
                        —
                    @endif
                </p>
            </div>
        </div>

        {{-- Manifest --}}
        <div class="mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-gray-900">Fixed manifest &amp; balances</h2>
                <p class="mt-0.5 text-sm text-gray-500">Known contents for this cart.</p>
            </div>
            <div class="px-5 py-4">
                @if ($cart->crashCartItems->isEmpty())
                    <p class="text-sm text-amber-700">No items on this cart. Edit under Manage Stores to set the manifest.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Item</th>
                                    <th class="px-3 py-2 text-right font-medium text-gray-600">Par</th>
                                    @if ($isOpen)
                                        <th class="px-3 py-2 text-right font-medium text-gray-600">Used</th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-600">Remaining</th>
                                    @endif
                                    <th class="px-3 py-2 text-right font-medium text-gray-600">On hand</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($balances as $row)
                                    <tr>
                                        <td class="px-3 py-2 text-gray-900">{{ $row['item_name'] }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums text-gray-700">{{ rtrim(rtrim(number_format($row['par'], 2), '0'), '.') }}</td>
                                        @if ($isOpen)
                                            <td class="px-3 py-2 text-right tabular-nums text-gray-700">{{ rtrim(rtrim(number_format($row['used'], 2), '0'), '.') }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums font-medium {{ $row['remaining'] <= 0 ? 'text-red-700' : 'text-gray-900' }}">
                                                {{ rtrim(rtrim(number_format($row['remaining'], 2), '0'), '.') }}
                                            </td>
                                        @endif
                                        <td class="px-3 py-2 text-right tabular-nums text-gray-600">{{ rtrim(rtrim(number_format($row['on_hand'], 2), '0'), '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Actions --}}
        <div class="mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 bg-gray-50/70 px-5 py-4">
                @if ($isSealed)
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-gray-600">Cart is sealed. Break the seal when opened for emergency use.</p>
                        <form method="POST" action="{{ route('inventory.crash-carts.break-seal', $cart) }}"
                              onsubmit="return confirm('Break the seal on this crash cart? Usage can be recorded after this.');">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700">
                                Break seal
                            </button>
                        </form>
                    </div>
                @elseif ($isOpen)
                    <p class="text-sm font-medium text-gray-900">Record usage</p>
                    <p class="mt-0.5 text-sm text-gray-500">Log each item used from the manifest.</p>

                    <form method="POST" action="{{ route('inventory.crash-carts.usage', $cart) }}"
                          class="mt-4 grid gap-4 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
                        @csrf
                        <div>
                            <label for="client_id" class="block text-sm font-medium text-gray-700">{{ inventory_label('client') }}</label>
                            <select id="client_id" name="client_id" required
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Select…</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>
                                        {{ $client->name }} ({{ $client->client_id }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="item_id" class="block text-sm font-medium text-gray-700">Item</label>
                            <select id="item_id" name="item_id" required
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Select…</option>
                                @foreach ($cart->crashCartItems as $line)
                                    @php
                                        $balance = $balances->firstWhere('item_id', $line->item_id);
                                        $remaining = $balance['remaining'] ?? $line->par_quantity;
                                    @endphp
                                    @if ($remaining > 0)
                                        <option value="{{ $line->item_id }}" @selected(old('item_id') == $line->item_id)>
                                            {{ $line->item?->name }} ({{ rtrim(rtrim(number_format($remaining, 2), '0'), '.') }} left)
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="quantity" class="block text-sm font-medium text-gray-700">Quantity</label>
                            <input type="number" id="quantity" name="quantity" min="0.0001" step="any"
                                   value="{{ old('quantity', 1) }}" required
                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div class="flex items-end">
                            <button type="submit"
                                    class="inline-flex w-full items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700">
                                Record usage
                            </button>
                        </div>
                        <div class="sm:col-span-2 lg:col-span-4">
                            <label for="notes" class="block text-sm font-medium text-gray-700">Notes <span class="font-normal text-gray-400">(optional)</span></label>
                            <input type="text" id="notes" name="notes" maxlength="1000" value="{{ old('notes') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </form>
                @endif
            </div>
        </div>

        {{-- Recent usage --}}
        @if ($recentUsage->isNotEmpty())
            <div class="mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">Recent usage</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">When</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">{{ inventory_label('client') }}</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Item</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">Qty</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($recentUsage as $event)
                                <tr>
                                    <td class="px-4 py-2 text-gray-700">{{ $event->occurred_at?->format('d M Y H:i') ?? '—' }}</td>
                                    <td class="px-4 py-2 text-gray-900">
                                        {{ $event->client?->name ?? '—' }}
                                        @if ($event->client?->client_id)
                                            <span class="text-gray-500 text-xs">({{ $event->client->client_id }})</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-gray-900">{{ $event->item?->name ?? '—' }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums text-gray-700">{{ rtrim(rtrim(number_format((float) $event->quantity, 2), '0'), '.') }}</td>
                                    <td class="px-4 py-2 text-gray-600">{{ $event->recordedBy?->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
</x-app-layout>
