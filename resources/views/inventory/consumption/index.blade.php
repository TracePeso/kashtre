<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">Consumption</h2>
            <p class="mt-1 text-sm text-gray-500">
                View the last 10 days of consumption for your store. Drill into a day for hourly detail.
            </p>
        </div>

        @include('inventory.partials.subnav')

        <div class="mt-6 bg-white shadow sm:rounded-lg p-6">
            @livewire('inventory.list-daily-consumptions')
        </div>
    </div>
</div>
</x-app-layout>
