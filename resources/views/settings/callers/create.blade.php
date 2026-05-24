<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('service-point-callers.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to Callers</a>
        </div>

        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-6 py-5 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Add Caller</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Give the caller a name and select the service points it covers.
                </p>
            </div>

            @if(session('error'))
                <div class="mx-6 mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">{{ session('error') }}</div>
            @endif

            <form action="{{ route('service-point-callers.store') }}" method="POST" class="px-6 py-5 space-y-5">
                @csrf

                <!-- Caller Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">
                        Caller Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           name="name"
                           id="name"
                           value="{{ old('name') }}"
                           placeholder="e.g. Reception Desk, Counter 1"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm
                                  @error('name') border-red-300 @enderror">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Service Points -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Service Points <span class="text-red-500">*</span>
                        <span class="text-gray-400 font-normal">(select one or more)</span>
                    </label>
                    @error('service_point_ids')
                        <p class="mb-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    @if($servicePoints->isEmpty())
                        <p class="text-sm text-gray-500 italic py-4 text-center border border-gray-200 rounded-md">
                            No service points found for your organisation.
                        </p>
                    @else
                        <div class="space-y-2 max-h-72 overflow-y-auto border border-gray-200 rounded-md p-3">
                            @foreach($servicePoints as $sp)
                                <label class="flex items-center gap-2 cursor-pointer hover:bg-gray-50 px-1 py-1 rounded">
                                    <input type="checkbox"
                                           name="service_point_ids[]"
                                           value="{{ $sp->id }}"
                                           {{ in_array($sp->id, old('service_point_ids', [])) ? 'checked' : '' }}
                                           class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <span class="text-sm text-gray-800">{{ $sp->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="flex justify-end space-x-3 pt-2">
                    <a href="{{ route('service-point-callers.index') }}"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700"
                            {{ $servicePoints->isEmpty() ? 'disabled' : '' }}>
                        Create Caller
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</x-app-layout>
