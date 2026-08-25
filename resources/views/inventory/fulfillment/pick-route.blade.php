<x-app-layout>
    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Pick route sheet</h1>
                    <p class="text-sm text-gray-600 mt-1">
                        {{ $route['store']?->name ?? 'End Store' }}
                        @if(($route['scope'] ?? 'basket') === 'ward')
                            · Legacy ward run
                        @else
                            · Basket {{ $route['basket_key'] }}
                        @endif
                    </p>
                </div>
                <button type="button" onclick="window.print()"
                        class="rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                    Print
                </button>
            </div>

            @php $labels = $route['labels'] ?? ['layer_3' => 'Wall', 'layer_2' => 'Cabinet', 'layer_1' => 'Bin']; @endphp

            <div class="overflow-hidden bg-white shadow sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">#</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">{{ $labels['layer_3'] }}</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">{{ $labels['layer_2'] }}</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">{{ $labels['layer_1'] }}</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Item</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Qty</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($route['lines'] as $i => $row)
                            <tr>
                                <td class="px-3 py-2 text-gray-500">{{ $i + 1 }}</td>
                                <td class="px-3 py-2">{{ $row['location_layer_3'] ?: '—' }}</td>
                                <td class="px-3 py-2">{{ $row['location_layer_2'] ?: '—' }}</td>
                                <td class="px-3 py-2">{{ $row['location_layer_1'] ?: '—' }}</td>
                                <td class="px-3 py-2 font-medium text-gray-900">{{ $row['item_name'] }}</td>
                                <td class="px-3 py-2 text-right font-mono">{{ rtrim(rtrim(number_format($row['quantity'], 2), '0'), '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-6 text-center text-gray-500">No open lines for this basket.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
