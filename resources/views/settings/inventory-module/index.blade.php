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
                            <li class="px-6 py-4 flex items-center justify-between gap-4">
                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    <span class="inline-flex shrink-0 items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $config->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $config->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                    <div class="min-w-0">
                                        <a href="{{ route('inventory-module-configs.show', $config) }}"
                                           class="text-sm font-medium text-blue-700 hover:text-blue-900 truncate block">
                                            {{ $config->business->name ?? '—' }}
                                        </a>
                                        @if($config->description)
                                            <p class="text-sm text-gray-400 mt-0.5 truncate">{{ $config->description }}</p>
                                        @endif
                                        <p class="text-xs text-gray-500 mt-0.5">
                                            Safety {{ number_format((float) $config->safety_stock_days, 0) }}d ·
                                            Buffer {{ number_format((float) $config->buffer_stock_days, 0) }}d ·
                                            {{ $config->approvers->count() }} approver{{ $config->approvers->count() === 1 ? '' : 's' }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex shrink-0 items-center gap-2">
                                    <a href="{{ route('inventory-module-configs.show', $config) }}"
                                       class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                        View
                                    </a>
                                    @if(in_array('Edit Inventory Module', auth()->user()->permissions ?? []))
                                        <a href="{{ route('inventory-module-configs.edit', $config) }}"
                                           class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-md">
                                            Edit
                                        </a>
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
