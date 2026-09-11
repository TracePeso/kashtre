<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Prepaid Care Package Balances</h4>

    @if ($balances->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">No prepaid package is on file for this patient.</p>
    @else
        <div class="space-y-4">
            @foreach ($balances as $packageId => $lines)
                <div wire:key="package-{{ $packageId }}">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">{{ $packageId }}</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach ($lines as $line)
                            @php
                                $isExhausted = $line->remainingQty <= 0;
                                $usedFraction = $line->allocatedQty > 0
                                    ? min(1, $line->usedQty / $line->allocatedQty)
                                    : 1;
                            @endphp
                            <div wire:key="line-{{ $line->id }}"
                                class="border rounded p-3 {{ $isExhausted ? 'border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20' : 'border-gray-200 dark:border-gray-700' }}">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-900 dark:text-gray-100">
                                        {{ $itemNames[$line->serviceCode] ?? $line->serviceCode }}
                                    </span>
                                    <span class="text-sm font-semibold {{ $isExhausted ? 'text-amber-700 dark:text-amber-400' : 'text-green-600 dark:text-green-400' }}">
                                        {{ $line->remainingQty }}
                                    </span>
                                </div>
                                <div class="mt-1.5 h-1.5 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                                    <div class="h-full {{ $isExhausted ? 'bg-amber-500' : 'bg-green-500' }}"
                                        style="width: {{ $usedFraction * 100 }}%"></div>
                                </div>
                                <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                    {{ $line->usedQty }} used of {{ $line->allocatedQty }} allocated
                                    @if ($isExhausted)
                                        <span class="text-amber-600 dark:text-amber-400">— exhausted, next order bills</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
