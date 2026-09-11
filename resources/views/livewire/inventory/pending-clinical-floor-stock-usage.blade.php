<div class="bg-white border border-amber-200 shadow-sm rounded-lg p-4 mb-4">
    <div class="flex items-center justify-between mb-2">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">From Clinical Chart — Not Yet Billed</h3>
            <p class="text-xs text-gray-500">Recorded by a nurse from the ward chart. Not reflected below until you record it here yourself.</p>
        </div>
        <label class="flex items-center gap-1.5 text-xs text-gray-500">
            <input type="checkbox" wire:model.live="showReviewed" class="rounded border-gray-300">
            Show reviewed
        </label>
    </div>

    @if ($lines->isEmpty())
        <p class="text-xs text-gray-400 py-2">Nothing pending.</p>
    @else
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-gray-500 uppercase">
                    <th class="pb-1.5">Patient</th>
                    <th class="pb-1.5">Item</th>
                    <th class="pb-1.5">Qty</th>
                    <th class="pb-1.5">Recorded</th>
                    <th class="pb-1.5"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lines as $line)
                    <tr wire:key="pfs-{{ $line['event_id'] }}" class="border-t border-gray-100 {{ $line['reviewed'] ? 'opacity-50' : '' }}">
                        <td class="py-1.5 font-medium text-gray-900">{{ $line['patient_id'] }}</td>
                        <td class="py-1.5 text-gray-700">{{ $line['item_code'] }}</td>
                        <td class="py-1.5 text-gray-700">{{ $line['quantity'] }}</td>
                        <td class="py-1.5 text-gray-500">{{ $line['recorded_at'] }}</td>
                        <td class="py-1.5 text-right">
                            @if ($line['reviewed'])
                                <span class="text-[10px] text-gray-400 uppercase">Reviewed</span>
                            @else
                                <button wire:click="markReviewed('{{ $line['event_id'] }}')"
                                    class="text-xs text-blue-700 hover:underline">Mark Reviewed</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
