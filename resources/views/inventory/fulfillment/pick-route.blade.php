<x-app-layout>
    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="flex items-start justify-between gap-3 print:hidden">
                <div>
                    <a href="{{ route('inventory.fulfillment.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; End Store queue</a>
                    <h1 class="mt-2 text-xl font-semibold text-gray-900">Sequential picking route</h1>
                    <p class="text-sm text-gray-600 mt-1">
                        {{ $route['store']?->name ?? 'End Store' }}
                        @if(!empty($route['client_space']))
                            · {{ $route['client_space']->name }}
                        @elseif(($route['scope'] ?? 'basket') === 'ward')
                            · All wards at this store
                        @else
                            · {{ $route['envelopes'][0]['client_name'] ?? 'One patient' }}
                        @endif
                        @if(!empty($route['visit_id']))
                            · Visit {{ $route['visit_id'] }}
                        @endif
                    </p>
                </div>
                <button type="button" onclick="window.print()"
                        class="rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                    Print
                </button>
            </div>

            @if(session('success'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 print:hidden">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 print:hidden">
                    {{ $errors->first() }}
                </div>
            @endif

            @php $labels = $route['labels'] ?? ['layer_3' => 'Wall', 'layer_2' => 'Cabinet', 'layer_1' => 'Bin']; @endphp

            <section class="overflow-hidden bg-white shadow sm:rounded-lg">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">1. Walk the shelves once</h2>
                    <p class="mt-0.5 text-xs text-gray-500">Quantities are the total of every patient basket in this run, sorted {{ $labels['layer_3'] }} → {{ $labels['layer_2'] }} → {{ $labels['layer_1'] }}. Items with no place are last.</p>
                </div>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Stop</th>
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
                                <td colspan="6" class="px-3 py-6 text-center text-gray-500">Nothing left to pick. Open inpatient lines for this run are already staged or completed.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            <section class="overflow-hidden bg-white shadow sm:rounded-lg">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">2. Split into patient envelopes</h2>
                    <p class="mt-0.5 text-xs text-gray-500">After the walk, put each patient's quantity in its own envelope. The envelopes go into one ward tote.</p>
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse($route['envelopes'] ?? [] as $envelope)
                        <div class="px-4 py-3">
                            <p class="text-sm font-semibold text-gray-900">{{ $envelope['client_name'] }}</p>
                            <p class="text-xs text-gray-500">
                                @if(!empty($envelope['client_space_name'])){{ $envelope['client_space_name'] }} · @endif
                                @if(!empty($envelope['visit_id']))Visit {{ $envelope['visit_id'] }}@endif
                            </p>
                            <table class="mt-2 min-w-full text-sm">
                                <tbody>
                                    @foreach($envelope['lines'] as $item)
                                        <tr>
                                            <td class="py-1 text-gray-800">{{ $item['item_name'] }}</td>
                                            <td class="py-1 text-right font-mono text-gray-900">{{ rtrim(rtrim(number_format($item['quantity'], 2), '0'), '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @empty
                        <p class="px-4 py-6 text-center text-sm text-gray-500">No patient envelopes on this run.</p>
                    @endforelse
                </div>
            </section>

            @if(($route['scope'] ?? '') === 'ward' && count($route['lines'] ?? []) > 0)
                <section class="bg-white shadow sm:rounded-lg p-4 print:hidden">
                    <h2 class="text-sm font-semibold text-gray-900">3. Scan the ward tote</h2>
                    <p class="mt-0.5 text-xs text-gray-500">This marks the queue rows staged. Stock stays on the End Store until the ward releases the tote.</p>
                    <form method="POST"
                          action="{{ isset($clientSpace) && $clientSpace ? route('inventory.fulfillment.ward-pick-route.stage', ['store' => $store, 'client_space' => $clientSpace]) : route('inventory.fulfillment.ward-pick.stage', $store) }}"
                          class="mt-3 flex flex-wrap items-end gap-3">
                        @csrf
                        @if(!empty($route['visit_id']))
                            <input type="hidden" name="visit_id" value="{{ $route['visit_id'] }}">
                        @endif
                        <div class="flex-1 min-w-[12rem]">
                            <label for="tote_barcode" class="block text-sm font-medium text-gray-700">Tote barcode</label>
                            <input type="text" name="tote_barcode" id="tote_barcode" required maxlength="100"
                                   value="{{ old('tote_barcode') }}"
                                   placeholder="Scan or type the tote label"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                        <button type="submit" class="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">
                            Stage into tote
                        </button>
                    </form>
                </section>
            @endif
        </div>
    </div>
    <style>
        @media print {
            .print\:hidden { display: none !important; }
        }
    </style>
</x-app-layout>
