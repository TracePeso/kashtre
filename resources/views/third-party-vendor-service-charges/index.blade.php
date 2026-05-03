<x-app-layout>
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Third-party vendor service charges</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Global tiered charges that apply to <strong>all</strong> third-party (insurance) vendors for each clinic business—similar to entity service charges.
                </p>
            </div>
            @if(in_array('Manage Service Charges', Auth::user()->permissions ?? []))
                <a href="{{ route('third-party-vendor-service-charges.create') }}"
                   class="inline-flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg shrink-0">
                    Add / configure tiers
                </a>
            @endif
        </div>

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        @livewire('third-party-vendor-service-charges-table')
    </div>
</x-app-layout>
