<div class="overflow-x-auto">
    @if(session('success'))
        <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded text-sm">{{ session('success') }}</div>
    @endif

    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-3 py-2 text-left font-medium text-gray-600">Item</th>
                <th class="px-3 py-2 text-left font-medium text-gray-600">SUOM</th>
                <th class="px-3 py-2 text-right font-medium text-gray-600">System</th>
                <th class="px-3 py-2 text-right font-medium text-gray-600">Physical</th>
                <th class="px-3 py-2 text-right font-medium text-gray-600">Damaged</th>
                <th class="px-3 py-2 text-right font-medium text-gray-600">Variance</th>
                <th class="px-3 py-2 text-right font-medium text-gray-600">Shrinkage</th>
                @if($stockCount->isDraft())
                    <th class="px-3 py-2 text-right font-medium text-gray-600">Action</th>
                @endif
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 bg-white">
            @foreach($stockCount->lines as $line)
                @php
                    $lineData = $lines[$line->id] ?? ['physical' => '0', 'damaged' => '0'];
                    $physical = (float) ($stockCount->isDraft() ? $lineData['physical'] : $line->physical_quantity_suom);
                    $system = (float) $line->system_quantity_suom;
                    $variance = round($physical - $system, 4);
                    $shrinkage = $system > 0 ? round((($system - $physical) / $system) * 100, 2) : null;
                @endphp
                <tr wire:key="line-{{ $line->id }}">
                    <td class="px-3 py-2">
                        <div class="font-medium text-gray-900">{{ $line->item->name }}</div>
                        <div class="text-xs text-gray-500">{{ $line->item->code }}</div>
                    </td>
                    <td class="px-3 py-2 text-gray-600">{{ $line->item->itemUnit?->name ?? '—' }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format($system, 0) }}</td>
                    <td class="px-3 py-2 text-right">
                        @if($stockCount->isDraft())
                            <input type="number" step="0.0001" min="0"
                                   wire:model.defer="lines.{{ $line->id }}.physical"
                                   class="w-28 rounded border-gray-300 text-right text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @else
                            <span class="tabular-nums">{{ number_format($physical, 0) }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-right">
                        @if($stockCount->isDraft())
                            <input type="number" step="0.0001" min="0"
                                   wire:model.defer="lines.{{ $line->id }}.damaged"
                                   class="w-24 rounded border-gray-300 text-right text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @else
                            <span class="tabular-nums">{{ number_format((float) $line->damaged_quantity_suom, 0) }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-right tabular-nums {{ $variance < 0 ? 'text-red-600' : ($variance > 0 ? 'text-green-600' : 'text-gray-600') }}">
                        {{ number_format($variance, 0) }}
                    </td>
                    <td class="px-3 py-2 text-right tabular-nums">
                        {{ $shrinkage !== null ? number_format($shrinkage, 2).'%' : '—' }}
                    </td>
                    @if($stockCount->isDraft())
                        <td class="px-3 py-2 text-right">
                            <button type="button" wire:click="saveLine({{ $line->id }})"
                                    class="text-blue-600 hover:text-blue-800 font-medium text-xs uppercase tracking-wide">
                                Save
                            </button>
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
