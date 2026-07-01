<div>
    @if(! $embedded)
        <div class="mb-4 max-w-md">
            <label for="network-store" class="block text-sm font-medium text-gray-700">Store</label>
            <select id="network-store" wire:model.live="storeId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                <option value="">Select Main or Branch store…</option>
                @foreach(\App\Models\Store::optionsForSelect((int) auth()->user()->business_id) as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if(! $storeId)
        <p class="text-sm text-gray-500 py-12 text-center border border-dashed border-gray-200 rounded-lg">
            Select a store to view network stock totals.
        </p>
    @else
        {{ $this->table }}
    @endif
</div>
