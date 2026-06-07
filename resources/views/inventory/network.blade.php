<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl font-bold text-gray-900">Network Stock</h2>
        <p class="mt-1 text-sm text-gray-500">Physical and system stock rolled up across Main → Branch → Unit stores.</p>
        @include('inventory.partials.subnav')
        <div class="mt-6 bg-white shadow sm:rounded-lg p-6">@livewire('inventory.network-stock-table')</div>
    </div>
</div>
</x-app-layout>
