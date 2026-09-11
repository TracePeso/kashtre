<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('inventory-module-configs.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to Inventory Module Configurations</a>
        </div>

        <div class="md:flex md:items-start md:justify-between gap-4 mb-6">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-2xl font-bold text-gray-900 truncate">{{ $config->business->name ?? 'Inventory module' }}</h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $config->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ $config->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500">Read-only view of this organisation’s inventory configuration.</p>
            </div>

            <div class="mt-4 md:mt-0 flex flex-wrap items-center gap-2 shrink-0">
                @if($config->is_active && in_array('View Inventory Module', auth()->user()->permissions ?? []))
                    <form action="{{ route('inventory-module-configs.enter-inventory', $config) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md">
                            Browse inventory
                        </button>
                    </form>
                @endif

                @if(in_array('Edit Inventory Module', auth()->user()->permissions ?? []))
                    <a href="{{ route('inventory-module-configs.edit', $config) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md">
                        Edit
                    </a>
                @endif

                @if(in_array('Manage Inventory Module', auth()->user()->permissions ?? []))
                    <form action="{{ route('inventory-module-configs.toggle-status', $config) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md border transition-colors
                                    {{ $config->is_active ? 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100' : 'border-green-200 bg-green-50 text-green-700 hover:bg-green-100' }}">
                            {{ $config->is_active ? 'Deactivate' : 'Activate' }}
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
        @endif

        <div class="bg-white shadow sm:rounded-lg px-6 py-6">
            @include('settings.inventory-module._config-details', ['config' => $config])
        </div>

        @if(in_array('Delete Inventory Module', auth()->user()->permissions ?? []))
            <div class="mt-6 flex justify-end">
                <form action="{{ route('inventory-module-configs.destroy', $config) }}" method="POST"
                      onsubmit="return confirm('Remove inventory module for {{ $config->business->name ?? 'this business' }}?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 border border-red-200">
                        Remove module
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>
</x-app-layout>
