<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('inventory-module-configs.show', $config) }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to configuration</a>
        </div>

        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-6 py-5 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Edit Inventory Module — {{ $config->business->name ?? '—' }}</h3>
                <p class="mt-1 text-sm text-gray-500">Update the description and GRN approvers. Use the Activate/Deactivate toggle on the list page to change the status.</p>
            </div>

            <form action="{{ route('inventory-module-configs.update', $config) }}" method="POST" class="px-6 py-5 space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description <span class="text-gray-400">(optional)</span></label>
                    <textarea name="description" id="description" rows="3"
                              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('description') border-red-300 @enderror"
                              placeholder="Any notes about this configuration">{{ old('description', $config->description) }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                @include('settings.inventory-module._stock-settings-fields', [
                    'moduleConfig' => $config,
                ])

                @include('settings.inventory-module._approvers-fields', [
                    'moduleConfig' => $config,
                    'businessUsers' => $businessUsers,
                ])

                <div class="flex justify-end space-x-3 pt-2">
                    <a href="{{ route('inventory-module-configs.show', $config) }}"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</x-app-layout>
