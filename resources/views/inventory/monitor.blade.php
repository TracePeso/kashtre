<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('inventory.partials.subnav')

        <div class="mt-6">
            @livewire('inventory.inventory-stock-monitor')
        </div>
    </div>
</div>
</x-app-layout>
