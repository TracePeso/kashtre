<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('inventory.partials.subnav')

        <div class="mt-4">
            <h1 class="text-xl font-semibold text-gray-900 tracking-tight">Units</h1>
            <p class="mt-1 text-sm text-gray-500">
                Catalog, mappings, packaging rules, composites, governance, module policies, and audit.
            </p>
        </div>

        <div class="mt-4">
            @livewire('inventory.unit-engine-console')
        </div>
    </div>
</div>
</x-app-layout>
