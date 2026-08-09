<x-app-layout>
    <div class="py-6">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-xl font-semibold text-gray-900">Internal replenishment</h1>
            <p class="mt-1 text-sm text-gray-600">Draft a child→parent order using days-of-stock coverage (SRD §7).</p>

            @if($errors->any())
                <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('inventory.replenishment.store') }}" class="mt-6 space-y-4 bg-white shadow sm:rounded-lg p-5">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700">Requesting store (child)</label>
                    <select name="child_store_id" required class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <option value="">Select…</option>
                        @foreach($stores as $store)
                            <option value="{{ $store->id }}" @selected(old('child_store_id') == $store->id)>
                                {{ $store->name }} ({{ $store->distribution_type }})
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
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Coverage days (max stock)</label>
                    <input type="number" step="0.01" min="1" name="coverage_days" value="{{ old('coverage_days') }}"
                           placeholder="Uses store max_stock_days when blank"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea name="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ old('notes') }}</textarea>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        Create draft order
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
