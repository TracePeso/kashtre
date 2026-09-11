<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="md:flex md:items-center md:justify-between gap-4">
            <div class="min-w-0 flex-1">
                <h2 class="text-2xl font-bold text-gray-900">Internal replenishment</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Stock requests from child stores to their parent. Open a draft to review and edit quantities.
                </p>
            </div>
            <div class="mt-4 md:mt-0 shrink-0">
                <a href="{{ route('inventory.replenishment.create') }}"
                   class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700">
                    Create draft
                </a>
            </div>
        </div>

        @include('inventory.partials.subnav')

        @if (session('success'))
            <div class="mt-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        <div class="mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm p-4 sm:p-6">
            @livewire('inventory.list-internal-replenishment-orders')
        </div>
    </div>
</div>
</x-app-layout>
