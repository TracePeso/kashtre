<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                    Calling Module Configuration
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Enable or disable the calling module per organisation. Only enabled businesses will see the Calling section.
                </p>
            </div>
            @if(in_array('Add Calling Module', auth()->user()->permissions ?? []))
            <div class="mt-4 flex md:mt-0 md:ml-4">
                <a href="{{ route('calling-module-configs.create') }}"
                   class="ml-3 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Enable for Business
                </a>
            </div>
            @endif
        </div>

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="mt-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded relative">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative">
                {{ session('error') }}
            </div>
        @endif

        <!-- List -->
        <div class="mt-8">
            @if($configs->count() > 0)
                <div class="bg-white shadow overflow-hidden sm:rounded-md">
                    <ul class="divide-y divide-gray-200">
                        @foreach($configs as $config)
                            <li x-data="{ open: false }">
                                <div class="px-6 py-4 flex items-center justify-between">
                                    <div class="flex items-center gap-3 flex-1 min-w-0">
                                        <div class="flex flex-col gap-1">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $config->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ $config->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium {{ $config->audio_enabled ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-500' }}">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072M12 6v12m0 0l-3-3m3 3l3-3M9 10a3 3 0 000 4"/></svg>
                                                Audio {{ $config->audio_enabled ? 'On' : 'Off' }}
                                            </span>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium {{ $config->video_enabled ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-500' }}">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.724v6.552a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
                                                Video {{ $config->video_enabled ? 'On' : 'Off' }}
                                            </span>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">{{ $config->business->name ?? '—' }}</p>
                                            @if($config->description)
                                                <p class="text-sm text-gray-400 mt-0.5">{{ $config->description }}</p>
                                            @endif
                                            <p class="text-xs text-gray-400 mt-0.5">
                                                Added {{ $config->created_at->format('M d, Y') }}
                                                @if($config->createdBy) by {{ $config->createdBy->name }} @endif
                                            </p>
                                        </div>
                                    </div>

                                    <!-- View toggle button -->
                                    <button @click="open = !open"
                                            class="ml-4 inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-600 bg-white hover:bg-gray-50 transition-colors">
                                        <span x-text="open ? 'Close' : 'View'">View</span>
                                        <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>
                                </div>

                                <!-- Expandable actions row -->
                                <div x-show="open" x-transition
                                     class="px-6 pb-4 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-3">

                                    @if(in_array('Edit Calling Module', auth()->user()->permissions ?? []))
                                    <a href="{{ route('calling-module-configs.edit', $config) }}"
                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 text-sm font-medium rounded-md transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        Edit
                                    </a>
                                    @endif

                                    @if(in_array('Manage Calling Module', auth()->user()->permissions ?? []))
                                    <form action="{{ route('calling-module-configs.toggle-status', $config) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-md transition-colors
                                                    {{ $config->is_active ? 'bg-red-50 hover:bg-red-100 text-red-700' : 'bg-green-50 hover:bg-green-100 text-green-700' }}">
                                            @if($config->is_active)
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                            @else
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            @endif
                                            {{ $config->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>

                                    <form action="{{ route('calling-module-configs.toggle-audio', $config) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-md transition-colors
                                                    {{ $config->audio_enabled ? 'bg-red-50 hover:bg-red-100 text-red-700' : 'bg-blue-50 hover:bg-blue-100 text-blue-700' }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072M12 6v12m-3-9a3 3 0 000 6"/></svg>
                                            {{ $config->audio_enabled ? 'Disable Audio' : 'Enable Audio' }}
                                        </button>
                                    </form>

                                    <form action="{{ route('calling-module-configs.toggle-video', $config) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-md transition-colors
                                                    {{ $config->video_enabled ? 'bg-red-50 hover:bg-red-100 text-red-700' : 'bg-purple-50 hover:bg-purple-100 text-purple-700' }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.724v6.552a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
                                            {{ $config->video_enabled ? 'Disable Video' : 'Enable Video' }}
                                        </button>
                                    </form>
                                    @endif

                                    @if(in_array('Delete Calling Module', auth()->user()->permissions ?? []))
                                    <form action="{{ route('calling-module-configs.destroy', $config) }}" method="POST" class="inline"
                                          onsubmit="return confirm('Remove calling module for {{ $config->business->name ?? 'this business' }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-red-50 text-gray-500 hover:text-red-700 text-sm font-medium rounded-md transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            Remove
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No calling module configurations</h3>
                    <p class="mt-1 text-sm text-gray-500">Enable the calling module for a business to get started.</p>
                    @if(in_array('Add Calling Module', auth()->user()->permissions ?? []))
                    <div class="mt-6">
                        <a href="{{ route('calling-module-configs.create') }}"
                           class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            Enable for Business
                        </a>
                    </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
</x-app-layout>
