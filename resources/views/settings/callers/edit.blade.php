<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('service-point-callers.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to Callers</a>
        </div>

        {{-- ── EDIT / VIEW FORM ──────────────────────────────────────────────── --}}
        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-6 py-5 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">
                    {{ $canEdit ? 'Edit Caller' : 'Caller Details' }}
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $canEdit ? "Update the caller's name and the service points it covers." : "Caller information (read-only)." }}
                </p>
            </div>

            @if(session('error'))
                <div class="mx-6 mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">{{ session('error') }}</div>
            @endif

            @if($canEdit)
                <form action="{{ route('service-point-callers.update', $caller) }}" method="POST" class="px-6 py-5 space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">
                            Caller Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               name="name"
                               id="name"
                               value="{{ old('name', $caller->name) }}"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm
                                      @error('name') border-red-300 @enderror">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

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
                                               {{ in_array($sp->id, old('service_point_ids', $attachedIds)) ? 'checked' : '' }}
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
                                class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            Save Changes
                        </button>
                    </div>
                </form>
            @else
                {{-- Read-only view for View Callers --}}
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Caller Name</p>
                        <p class="mt-1 text-sm font-medium text-gray-900">{{ $caller->name }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Status</p>
                        <span class="mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            {{ $caller->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ ucfirst($caller->status) }}
                        </span>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Service Points</p>
                        @if($caller->servicePoints->isNotEmpty())
                            <div class="mt-1 flex flex-wrap gap-1">
                                @foreach($caller->servicePoints as $sp)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700">
                                        {{ $sp->name }}
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <p class="mt-1 text-sm text-gray-400 italic">None assigned</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        {{-- ── DISPLAY TOKEN ──────────────────────────────────────────────── --}}
        <div class="mt-6 bg-white shadow sm:rounded-lg">
            <div class="px-6 py-5 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Display Token (Passcode)</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Use this token to connect a queue display screen to this caller station.
                    Open the display app and pass this token in the URL: <code class="text-xs bg-gray-100 px-1 py-0.5 rounded">?token=…</code>
                </p>
            </div>

            @if(session('success'))
                <div class="mx-6 mt-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded text-sm">{{ session('success') }}</div>
            @endif

            <div class="px-6 py-5">
                @if($caller->display_token)
                    <div class="flex items-center gap-3">
                        <input type="text"
                               id="display-token"
                               value="{{ $caller->display_token }}"
                               readonly
                               class="flex-1 border-gray-300 rounded-md shadow-sm text-sm font-mono bg-gray-50 focus:ring-0 focus:border-gray-300">
                        <button type="button"
                                onclick="copyToken()"
                                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            <svg id="copy-icon" class="w-4 h-4 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <span id="copy-label">Copy</span>
                        </button>
                    </div>
                    @if($canManage)
                    <div class="mt-3">
                        <form action="{{ route('service-point-callers.generate-token', $caller) }}" method="POST" class="inline"
                              onsubmit="return confirm('Regenerate token? The old token will stop working immediately.')">
                            @csrf
                            <button type="submit" class="text-sm text-red-600 hover:text-red-800 font-medium">
                                Regenerate Token
                            </button>
                        </form>
                    </div>
                    @endif
                @else
                    <p class="text-sm text-gray-500 mb-3">No display token generated yet.</p>
                    @if($canManage)
                    <form action="{{ route('service-point-callers.generate-token', $caller) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                            </svg>
                            Generate Display Token
                        </button>
                    </form>
                    @else
                        <p class="text-sm text-gray-400 italic">Token not yet generated. Contact an admin to generate one.</p>
                    @endif
                @endif
            </div>
        </div>

        {{-- ── DANGER ZONE — Manage Callers only ──────────────────────────── --}}
        @if($canManage)
        <div class="mt-6 bg-white shadow sm:rounded-lg">
            <div class="px-6 py-5 flex items-center justify-end">
                <form action="{{ route('service-point-callers.destroy', $caller) }}" method="POST" class="inline"
                      onsubmit="return confirm('Delete caller \'{{ addslashes($caller->name) }}\'? This cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700">
                        Delete Caller
                    </button>
                </form>
            </div>
        </div>
        @endif

    </div>
</div>

<script>
function copyToken() {
    const input = document.getElementById('display-token');
    navigator.clipboard.writeText(input.value).then(() => {
        document.getElementById('copy-label').textContent = 'Copied!';
        setTimeout(() => document.getElementById('copy-label').textContent = 'Copy', 2000);
    });
}
</script>
</x-app-layout>
