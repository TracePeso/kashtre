<div>
    <div class="mb-4 flex flex-col gap-3">
        <div class="flex flex-wrap items-end gap-2 sm:gap-3">
            <div class="min-w-[10rem] flex-1 sm:flex-none sm:w-44">
                <label for="consumption-store" class="block text-xs font-medium text-gray-600 mb-1">Store</label>
                <select id="consumption-store" wire:model.live="storeId"
                        class="h-8 w-full rounded-md border-gray-300 py-0 pl-2 pr-7 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Select store…</option>
                    @foreach($storeOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-[10rem] flex-1 sm:flex-none sm:w-52">
                <label for="consumption-item" class="block text-xs font-medium text-gray-600 mb-1">Item</label>
                <select id="consumption-item" wire:model.live="itemId" @disabled(! $storeId)
                        class="h-8 w-full rounded-md border-gray-300 py-0 pl-2 pr-7 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                    <option value="">All items</option>
                    @foreach($itemOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-[10rem] flex-1 sm:flex-none sm:w-40">
                <label for="consumption-period" class="block text-xs font-medium text-gray-600 mb-1">Period</label>
                <select id="consumption-period" wire:model.live="periodPreset"
                        class="h-8 w-full rounded-md border-gray-300 py-0 pl-2 pr-7 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @foreach($periodPresets as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if($periodPreset === 'custom')
            <div class="flex flex-wrap items-end gap-2 sm:gap-3">
                <div>
                    <label for="consumption-from" class="block text-xs font-medium text-gray-600 mb-1">From</label>
                    <input type="date" id="consumption-from" wire:model.live="dateFrom"
                           class="h-8 rounded-md border-gray-300 py-0 px-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="consumption-until" class="block text-xs font-medium text-gray-600 mb-1">Until</label>
                    <input type="date" id="consumption-until" wire:model.live="dateUntil"
                           class="h-8 rounded-md border-gray-300 py-0 px-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>
        @endif
    </div>

    @if($generateMessage)
        <div class="mb-4 rounded-md px-4 py-3 text-sm {{ $generateMessageType === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : ($generateMessageType === 'error' ? 'bg-red-50 border border-red-200 text-red-800' : 'bg-amber-50 border border-amber-200 text-amber-800') }}">
            {{ $generateMessage }}
        </div>
    @endif

    @if($storeId)
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 flex-1">
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                    <p class="text-xs font-medium text-gray-500">Period</p>
                    <p class="mt-0.5 text-sm font-semibold text-gray-900">{{ $this->periodLabel() }}</p>
                    <p class="text-xs text-gray-500">{{ $this->periodPresetLabel() }}</p>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                    <p class="text-xs font-medium text-gray-500">Total consumed</p>
                    <p class="mt-0.5 text-lg font-bold text-gray-900 tabular-nums">{{ number_format($summary['total_quantity_suom'], 0) }}</p>
                    <p class="text-xs text-gray-500">SUOM</p>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                    <p class="text-xs font-medium text-gray-500">Items with usage</p>
                    <p class="mt-0.5 text-lg font-bold text-gray-900 tabular-nums">{{ number_format($summary['distinct_items']) }}</p>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                    <p class="text-xs font-medium text-gray-500">Activity rows</p>
                    <p class="mt-0.5 text-lg font-bold text-gray-900 tabular-nums">{{ number_format($summary['item_day_rows']) }}</p>
                    <p class="text-xs text-gray-500">Item × day</p>
                </div>
            </div>

            @if($this->showTestDataButton())
                <div class="shrink-0 sm:pl-3">
                    <button type="button"
                            wire:click="generateTestData"
                            wire:loading.attr="disabled"
                            wire:target="generateTestData"
                            wire:confirm="Generate test consumption data from the day after your last record through today?"
                            class="inline-flex items-center px-3 py-2 border border-amber-300 rounded-md text-xs font-medium text-amber-900 bg-amber-50 hover:bg-amber-100 disabled:opacity-60">
                        <span wire:loading.remove wire:target="generateTestData">Generate test data</span>
                        <span wire:loading wire:target="generateTestData">Generating…</span>
                    </button>
                    @if($this->backfillRangeLabel())
                        <p class="mt-1 text-xs text-gray-500 max-w-[12rem]">{{ $this->backfillRangeLabel() }}</p>
                    @endif
                </div>
            @endif
        </div>
    @endif

    <div wire:loading.class="opacity-60" wire:target="storeId,itemId,periodPreset,dateFrom,dateUntil,generateTestData">
        @if($storeId)
            {{ $this->table }}
        @else
            <p class="text-sm text-gray-500 py-8 text-center border border-dashed border-gray-200 rounded-lg">Select a store to view consumption.</p>
        @endif
    </div>
</div>
