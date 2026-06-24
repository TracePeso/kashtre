<div>
    @if($this->selectedStoreLabel())
        <div class="mb-4 rounded-lg border border-blue-100 bg-blue-50/70 px-4 py-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-blue-700">
                    {{ $stockView === 'network' ? 'Roll up from' : 'Your store' }}
                </p>
                <p class="text-sm font-semibold text-blue-950">{{ $this->selectedStoreLabel() }}</p>
            </div>
            <p class="text-sm text-blue-800">
                @if($stockView === 'network' && $networkScope)
                    {{ $networkScope }}
                @elseif($stockView === 'local' && $store)
                    {{ $store->hierarchyLabel() }} · stock for this store only
                @endif
            </p>
        </div>
    @endif

    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <span class="text-sm font-medium text-gray-700">Stock view</span>
            <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1" role="group" aria-label="Stock view mode">
                <button type="button"
                        wire:click="$set('stockView', 'local')"
                        class="px-4 py-2 text-sm font-medium rounded-md transition {{ $stockView === 'local' ? 'bg-white text-blue-700 shadow-sm ring-1 ring-gray-200' : 'text-gray-600 hover:text-gray-900' }}">
                    Local store
                </button>
                <button type="button"
                        wire:click="$set('stockView', 'network')"
                        class="px-4 py-2 text-sm font-medium rounded-md transition {{ $stockView === 'network' ? 'bg-white text-blue-700 shadow-sm ring-1 ring-gray-200' : 'text-gray-600 hover:text-gray-900' }}">
                    Network rollup
                </button>
            </div>
        </div>

        <div class="w-full lg:max-w-md">
            <label for="stock-monitor-store" class="block text-sm font-medium text-gray-700">
                {{ $stockView === 'network' ? 'Roll up from store' : 'Store' }}
            </label>
            <select id="stock-monitor-store" wire:model.live="storeId"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Select a store…</option>
                @foreach($stores as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if(! $storeId)
        <p class="text-sm text-gray-500 py-12 text-center border border-dashed border-gray-200 rounded-lg">
            Select a store to view {{ $stockView === 'network' ? 'network' : 'local' }} stock.
        </p>
    @elseif($stockView === 'local')
        @livewire('inventory.monitor-stock-table', ['storeId' => $storeId], key('local-stock-'.$storeId))
    @else
        @livewire('inventory.network-stock-table', ['storeId' => $storeId, 'embedded' => true], key('network-stock-'.$storeId))
    @endif
</div>
