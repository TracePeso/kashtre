<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('calling-module-configs.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to Calling Module Configurations</a>
        </div>

        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-6 py-5 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Enable Calling Module for a Business</h3>
                <p class="mt-1 text-sm text-gray-500">Once enabled, the business admin can assign callers to service points and staff will see the Calling section.</p>
            </div>

            @if(session('error'))
                <div class="mx-6 mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                    {{ session('error') }}
                </div>
            @endif

            <form action="{{ route('calling-module-configs.store') }}" method="POST" class="px-6 py-5 space-y-5">
                @csrf

                <!-- Business -->
                <div>
                    <label for="business_id" class="block text-sm font-medium text-gray-700">Business <span class="text-red-500">*</span></label>
                    @if($businesses->isEmpty())
                        <p class="mt-1 text-sm text-gray-500 italic">All businesses already have the calling module configured.</p>
                    @else
                        <select name="business_id" id="business_id"
                                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md @error('business_id') border-red-300 @enderror">
                            <option value="">— Select a business —</option>
                            @foreach($businesses as $biz)
                                <option value="{{ $biz->id }}" {{ old('business_id') == $biz->id ? 'selected' : '' }}>
                                    {{ $biz->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('business_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    @endif
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description <span class="text-gray-400">(optional)</span></label>
                    <textarea name="description" id="description" rows="3"
                              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('description') border-red-300 @enderror"
                              placeholder="Any notes about this configuration">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Module toggles -->
                <div class="border border-gray-200 rounded-lg p-4 space-y-3">
                    <p class="text-sm font-medium text-gray-700">Module Features</p>
                    <div class="flex items-center">
                        <input type="checkbox" name="is_active" id="is_active" value="1"
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                               {{ old('is_active', '1') ? 'checked' : '' }}>
                        <label for="is_active" class="ml-2 block text-sm text-gray-900">Enable calling module immediately</label>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" name="audio_enabled" id="audio_enabled" value="1"
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                               {{ old('audio_enabled', '1') ? 'checked' : '' }}>
                        <label for="audio_enabled" class="ml-2 block text-sm text-gray-900">
                            Enable Audio <span class="text-gray-400 text-xs">(TTS announcements via ElevenLabs)</span>
                        </label>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" name="video_enabled" id="video_enabled" value="1"
                               class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded"
                               {{ old('video_enabled', '1') ? 'checked' : '' }}>
                        <label for="video_enabled" class="ml-2 block text-sm text-gray-900">
                            Enable Video <span class="text-gray-400 text-xs">(display board / Now Serving screen)</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-2">
                    <a href="{{ route('calling-module-configs.index') }}"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" @if($businesses->isEmpty()) disabled @endif
                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        Enable Calling Module
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</x-app-layout>
