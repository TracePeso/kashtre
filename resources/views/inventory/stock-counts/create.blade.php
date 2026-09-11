<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">New stock count</h2>
            <p class="mt-1 text-sm text-gray-500">Creates a draft count sheet for all items with system stock in the selected store.</p>
        </div>

        @include('inventory.partials.subnav')

        <form method="POST" action="{{ route('inventory.stock-counts.store') }}" class="mt-6 bg-white shadow sm:rounded-lg p-6 space-y-6">
            @csrf

            <div>
                <label for="store_id" class="block text-sm font-medium text-gray-700">Store</label>
                <select name="store_id" id="store_id" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    <option value="">Select store</option>
                    @foreach($stores as $id => $label)
                        <option value="{{ $id }}" @selected(old('store_id') == $id)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('store_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                <textarea name="notes" id="notes" rows="3"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">{{ old('notes') }}</textarea>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('inventory.stock-counts.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</a>
                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">Create count sheet</button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
