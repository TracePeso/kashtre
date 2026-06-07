<div>
    <div class="mb-4 max-w-md">
        <label for="network-store" class="block text-sm font-medium text-gray-700">Roll up from store</label>
        <select id="network-store" wire:model.live="storeId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
            <option value="">Select Main or Branch store…</option>
            @foreach($stores as $id => $label)
                <option value="{{ $id }}">{{ $label }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-gray-500">Totals include the selected store and all stores beneath it (Excel columns P–T).</p>
    </div>

    @if(! $storeId)
        <p class="text-sm text-gray-500 py-8 text-center">Select a store to view network stock totals.</p>
    @elseif(count($rows) === 0)
        <p class="text-sm text-gray-500 py-8 text-center">No stock on hand in this store network.</p>
    @else
        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Item</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600">Stores</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600">System</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600">Physical</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600">Usable</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600">Damaged</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600">Expired</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach($rows as $row)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">{{ $row['item']->name ?? '—' }}</div>
                                <div class="text-xs text-gray-500">{{ $row['item']->code ?? '' }}</div>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $row['store_count'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['system_quantity_suom'], 0) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $row['physical_quantity_suom'] !== null ? number_format($row['physical_quantity_suom'], 0) : '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['usable_quantity_suom'], 0) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-amber-700">{{ number_format($row['damaged_quantity_suom'], 0) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-amber-700">{{ number_format($row['expired_quantity_suom'], 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
