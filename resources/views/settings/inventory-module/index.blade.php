<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                    Inventory Module Configuration
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Enable or disable the inventory module per organisation. Only enabled businesses will see the Inventory section.
                </p>
            </div>
            @if(in_array('Add Inventory Module', auth()->user()->permissions ?? []))
            <div class="mt-4 flex md:mt-0 md:ml-4">
                <a href="{{ route('inventory-module-configs.create') }}"
                   class="ml-3 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Enable for Business
                </a>
            </div>
            @endif
        </div>

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

        <div class="mt-8">
            @if($configs->count() > 0)
                <div class="bg-white shadow overflow-hidden sm:rounded-md">
                    <ul class="divide-y divide-gray-200">
                        @foreach($configs as $config)
                            <li x-data="{ open: false }">
                                <div class="px-6 py-4 flex items-center justify-between">
                                    <div class="flex items-center gap-3 flex-1 min-w-0">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $config->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $config->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">{{ $config->business->name ?? '—' }}</p>
                                            @if($config->description)
                                                <p class="text-sm text-gray-400 mt-0.5">{{ $config->description }}</p>
                                            @endif
                                            <p class="text-xs text-gray-500 mt-0.5">
                                                Fixed daily avg: {{ number_format((float) $config->fixed_daily_average_suom, 4) }} SUOM ·
                                                Safety: {{ number_format((float) $config->safety_stock_days, 1) }} days ·
                                                Buffer: {{ number_format((float) $config->buffer_stock_days, 1) }} days
                                            </p>
                                            @if($config->approvers->count() > 0)
                                                <p class="text-xs text-gray-500 mt-0.5">
                                                    GRN approvers:
                                                    {{ $config->approvers->map(fn ($a) => $a->user->name ?? '—')->join(', ') }}
                                                </p>
                                            @else
                                                <p class="text-xs text-amber-600 mt-0.5">No GRN approvers assigned yet</p>
                                            @endif
                                            <p class="text-xs text-gray-400 mt-0.5">
                                                Added {{ $config->created_at->format('M d, Y') }}
                                                @if($config->createdBy) by {{ $config->createdBy->name }} @endif
                                            </p>
                                        </div>
                                    </div>

                                    <button @click="open = !open"
                                            class="ml-4 inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-600 bg-white hover:bg-gray-50 transition-colors">
                                        <span x-text="open ? 'Close' : 'View'">View</span>
                                        <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>
                                </div>

                                <div x-show="open" x-transition
                                     class="px-6 pb-4 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-3">

                                    @if(in_array('Edit Inventory Module', auth()->user()->permissions ?? []))
                                    <a href="{{ route('inventory-module-configs.edit', $config) }}"
                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 text-sm font-medium rounded-md transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        Edit
                                    </a>
                                    @endif

                                    @if(in_array('Manage Inventory Module', auth()->user()->permissions ?? []))
                                    <form action="{{ route('inventory-module-configs.toggle-status', $config) }}" method="POST" class="inline">
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
                                    @endif

                                    @if(in_array('Delete Inventory Module', auth()->user()->permissions ?? []))
                                    <form action="{{ route('inventory-module-configs.destroy', $config) }}" method="POST" class="inline"
                                          onsubmit="return confirm('Remove inventory module for {{ $config->business->name ?? 'this business' }}?')">
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No inventory module configurations</h3>
                    <p class="mt-1 text-sm text-gray-500">Enable the inventory module for a business to get started.</p>
                    @if(in_array('Add Inventory Module', auth()->user()->permissions ?? []))
                    <div class="mt-6">
                        <a href="{{ route('inventory-module-configs.create') }}"
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
