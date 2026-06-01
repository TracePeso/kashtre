<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">SUOM</h2>
                <p class="mt-1 text-sm text-gray-500">Sale units of measure used on GRN lines (e.g. tab, cap, vial, bottle).</p>
            </div>
            <div class="mt-4 md:mt-0">
                <button
                    type="button"
                    onclick="document.getElementById('suom-create-trigger')?.click()"
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                    Add SUOM
                </button>
            </div>
        </div>

        @include('inventory.partials.subnav')

        <div class="mt-6 bg-white shadow sm:rounded-lg p-6">
            @livewire('inventory.list-suoms')
        </div>
    </div>
</div>
</x-app-layout>
