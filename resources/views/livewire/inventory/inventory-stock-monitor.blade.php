<div>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-2xl font-bold text-gray-900 sm:truncate">Monitor Stock</h2>

        <div class="flex items-center gap-2 min-w-0">
            <div class="inline-flex shrink-0 rounded-md border border-gray-200 bg-white p-0.5 shadow-sm" role="group" aria-label="Stock mode">
                <button type="button"
                        wire:click="setStockView('local')"
                        wire:loading.attr="disabled"
                        wire:target="setStockView"
                        class="px-2.5 py-1 text-xs font-medium rounded transition disabled:opacity-60 {{ $stockView === 'local' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-gray-900' }}">
                    Local
                </button>
                @if(\App\Support\InventoryBusinessContext::multiStoreNetworkEnabled())
                    <button type="button"
                            wire:click="setStockView('network')"
                            wire:loading.attr="disabled"
                            wire:target="setStockView"
                            class="px-2.5 py-1 text-xs font-medium rounded transition disabled:opacity-60 {{ $stockView === 'network' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-gray-900' }}">
                        Network
                    </button>
                @endif
            </div>

            <select id="stock-monitor-store" wire:model.live="storeId" aria-label="Store"
                    class="h-8 min-w-0 w-full sm:w-48 rounded-md border-gray-300 py-0 pl-2 pr-7 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Store…</option>
                @foreach($stores as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="mt-4 bg-white shadow sm:rounded-lg overflow-hidden relative" wire:loading.class="opacity-60" wire:target="setStockView,storeId">
        @if(! $storeId)
            <p class="text-sm text-gray-500 py-8 text-center">Select a store.</p>
        @else
            {{ $this->table }}
        @endif
    </div>
</div>
