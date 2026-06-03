<div class="overflow-x-auto">
    @if(session('success'))
        <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded text-sm">{{ session('success') }}</div>
    @endif

    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-3 py-2 text-left font-medium text-gray-600">Item</th>
                <th class="px-3 py-2 text-right font-medium text-gray-600">Daily avg</th>
                <th class="px-3 py-2 text-right font-medium text-gray-600">System</th>
                <th class="px-3 py-2 text-right font-medium text-gray-600">Suggested</th>
                <th class="px-3 py-2 text-right font-medium text-gray-600">Order (SUOM)</th>
                <th class="px-3 py-2 text-right font-medium text-gray-600">Order (OUOM)</th>
                <th class="px-3 py-2 text-right font-medium text-gray-600">Unit price</th>
                <th class="px-3 py-2 text-right font-medium text-gray-600">Line total</th>
                @if($order->isDraft())
                    <th class="px-3 py-2 text-right font-medium text-gray-600">Action</th>
                @endif
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 bg-white">
            @foreach($order->lines as $line)
                @php
                    $qty = (float) ($order->isDraft() ? ($quantities[$line->id] ?? $line->order_quantity_suom) : $line->order_quantity_suom);
                    $ouom = $line->item->suom_per_ouom && (float) $line->item->suom_per_ouom > 0
                        ? round($qty / (float) $line->item->suom_per_ouom, 4)
                        : null;
                @endphp
                <tr wire:key="order-line-{{ $line->id }}">
                    <td class="px-3 py-2">
                        <div class="font-medium text-gray-900">{{ $line->item->name }}</div>
                        <div class="text-xs text-gray-500">{{ $line->item->code }}</div>
                    </td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $line->daily_average_suom, 4) }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $line->system_quantity_suom, 0) }}</td>
                    <td class="px-3 py-2 text-right tabular-nums text-gray-500">{{ number_format((float) $line->suggested_quantity_suom, 0) }}</td>
                    <td class="px-3 py-2 text-right">
                        @if($order->isDraft())
                            <input type="number" step="0.0001" min="0"
                                   wire:model.defer="quantities.{{ $line->id }}"
                                   class="w-28 rounded border-gray-300 text-right text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @else
                            <span class="tabular-nums">{{ number_format((float) $line->order_quantity_suom, 0) }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-right tabular-nums text-gray-600">
                        @if($ouom !== null)
                            {{ number_format($ouom, 2) }} {{ $line->item->orderUnit?->name ?? 'OUOM' }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-3 py-2 text-right tabular-nums">UGX {{ number_format((float) ($line->unit_price ?? 0), 2) }}</td>
                    <td class="px-3 py-2 text-right tabular-nums font-medium">UGX {{ number_format($qty * (float) ($line->unit_price ?? 0), 2) }}</td>
                    @if($order->isDraft())
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
        <tfoot class="bg-gray-50">
            <tr>
                <td colspan="{{ $order->isDraft() ? 8 : 7 }}" class="px-3 py-3 text-right font-semibold text-gray-900">
                    Order total: UGX {{ number_format($order->orderTotal(), 2) }}
                </td>
                @if($order->isDraft())
                    <td></td>
                @endif
            </tr>
        </tfoot>
    </table>
</div>
