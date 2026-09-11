<x-app-layout>
    <div class="py-6">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8">
            <a href="{{ route('inventory.replenishment.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to Internal replenishment</a>
            <h1 class="mt-4 text-xl font-semibold text-gray-900">Create replenishment draft</h1>
            <p class="mt-1 text-sm text-gray-600">Request stock from the parent store based on days of coverage.</p>

            @if($errors->any())
                <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('inventory.replenishment.store') }}" class="mt-6 space-y-4 bg-white shadow sm:rounded-lg p-5">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700">Requesting store</label>
                    <select name="child_store_id" required class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <option value="">Select…</option>
                        @foreach($stores as $store)
                            @php
                                $parentLabel = $store->parent?->name;
                                $hasParent = (bool) $store->parent_id;
                            @endphp
                            <option value="{{ $store->id }}"
                                    @selected(old('child_store_id') == $store->id)
                                    @disabled(! $hasParent)>
                                {{ $store->name }}
                                @if ($parentLabel)
                                    (from {{ $parentLabel }})
                                @else
                                    (no parent store)
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Forecast basis</label>
                    <select name="forecast_basis" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <option value="consumption" @selected(old('forecast_basis', 'consumption') === 'consumption')>Consumption</option>
                        <option value="demand" @selected(old('forecast_basis') === 'demand')>Demand</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Consumption uses what left the shelf. Demand uses what was requested.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Coverage days</label>
                    <input type="number" step="0.01" min="1" name="coverage_days" value="{{ old('coverage_days') }}"
                           placeholder="Default: store maximum stock days"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    <p class="mt-1 text-xs text-gray-500">How many days of stock the draft should aim for.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea name="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="Optional">{{ old('notes') }}</textarea>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        Create draft
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
