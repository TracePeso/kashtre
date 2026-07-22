<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">Stock Transfers</h2>
                <p class="mt-1 text-sm text-gray-500">Request stock moves between stores: submit → configured approvers → receive confirms.</p>
            </div>
            <div class="mt-4 md:mt-0">
                <a href="{{ route('inventory.transfers.create') }}"
                   class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                    Make a transfer request
                </a>
            </div>
        </div>
        @include('inventory.partials.subnav')
        <div class="mt-6 bg-white shadow sm:rounded-lg p-6">
            @livewire('inventory.list-stock-transfers')
        </div>
    </div>
</div>
</x-app-layout>
