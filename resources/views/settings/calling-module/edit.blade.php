<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('calling-module-configs.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to Calling Module Configurations</a>
        </div>

        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-6 py-5 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Edit Calling Module — {{ $callingModuleConfig->business->name ?? '—' }}</h3>
                <p class="mt-1 text-sm text-gray-500">Update the description. Use the Activate/Deactivate toggle on the list page to change the status.</p>
            </div>

            <form action="{{ route('calling-module-configs.update', $callingModuleConfig) }}" method="POST" class="px-6 py-5 space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description <span class="text-gray-400">(optional)</span></label>
                    <textarea name="description" id="description" rows="3"
                              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('description') border-red-300 @enderror"
                              placeholder="Any notes about this configuration">{{ old('description', $callingModuleConfig->description) }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Default Emergency Message -->
                <div>
                    <label for="default_emergency_message" class="block text-sm font-medium text-gray-700">
                        Default Emergency Message <span class="text-gray-400">(optional)</span>
                    </label>
                    <p class="text-xs text-gray-500 mt-0.5 mb-1">Pre-fills the emergency broadcast. Use <code class="bg-gray-100 px-1 rounded">{destination}</code> for the service point name. Staff can still edit it before sending.</p>
                    <input type="text" name="default_emergency_message" id="default_emergency_message"
                           maxlength="500"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 sm:text-sm @error('default_emergency_message') border-red-300 @enderror"
                           placeholder="e.g. Emergency — please remain calm and follow staff instructions"
                           value="{{ old('default_emergency_message', $callingModuleConfig->default_emergency_message) }}">
                    @error('default_emergency_message')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Emergency Key Cooldown -->
                <div>
                    <label for="emergency_key_cooldown" class="block text-sm font-medium text-gray-700">
                        Emergency Key Cooldown <span class="text-gray-400">(seconds)</span>
                    </label>
                    <p class="text-xs text-gray-500 mt-0.5 mb-1">How long (in seconds) before F9/F10/F11 can be triggered again after firing. Default: 60 (1 minute). Range: 10–3600.</p>
                    <input type="number" name="emergency_key_cooldown" id="emergency_key_cooldown"
                           min="10" max="3600"
                           class="mt-1 block w-32 border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 sm:text-sm @error('emergency_key_cooldown') border-red-300 @enderror"
                           value="{{ old('emergency_key_cooldown', $callingModuleConfig->emergency_key_cooldown ?? 60) }}">
                    @error('emergency_key_cooldown')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Audio / Video toggles -->
                <div class="border border-gray-200 rounded-lg p-4 space-y-3">
                    <p class="text-sm font-medium text-gray-700">Module Features</p>
                    <div class="flex items-center">
                        <input type="checkbox" name="audio_enabled" id="audio_enabled" value="1"
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                               {{ old('audio_enabled', $callingModuleConfig->audio_enabled) ? 'checked' : '' }}>
                        <label for="audio_enabled" class="ml-2 block text-sm text-gray-900">
                            Audio enabled <span class="text-gray-400 text-xs">(TTS announcements via ElevenLabs)</span>
                        </label>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" name="video_enabled" id="video_enabled" value="1"
                               class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded"
                               {{ old('video_enabled', $callingModuleConfig->video_enabled) ? 'checked' : '' }}>
                        <label for="video_enabled" class="ml-2 block text-sm text-gray-900">
                            Video enabled <span class="text-gray-400 text-xs">(display board / Now Serving screen)</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-2">
                    <a href="{{ route('calling-module-configs.index') }}"
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
