<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('inventory.partials.subnav')

        <div class="mt-4">
            <h1 class="text-xl font-semibold text-gray-900 tracking-tight">EndStore</h1>
            <p class="mt-1 text-sm text-gray-500">
                Outpatient dispense, inpatient stage/release, and STAT alerts for paid goods at End Stores.
            </p>
        </div>


        <div class="mt-4 bg-white border border-gray-200 shadow-sm sm:rounded-lg p-6">
            @if(! $hasEndStores)
                <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    No End Stores found. Create one under
                    <a href="{{ route('stores.index') }}" class="font-medium underline">Manage Stores</a>,
                    then map Client Spaces in
                    <a href="{{ route('inventory.settings.edit', ['tab' => 'space-routing']) }}" class="font-medium underline">Space routing</a>.
                </div>
            @else
                <livewire:inventory.end-store-fulfillment-queue />
            @endif
        </div>
        
    </div>
</div>
</x-app-layout>
