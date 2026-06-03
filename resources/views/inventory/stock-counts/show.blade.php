<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <a href="{{ route('inventory.stock-counts.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to stock counts</a>
                <h2 class="mt-2 text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">{{ $stockCount->reference }}</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $stockCount->store->selectLabel() }} ·
                    {{ $stockCount->isDraft() ? 'Draft' : 'Finalized' }}
                    @if($stockCount->counted_at)
                        · Counted {{ $stockCount->counted_at->format('M d, Y H:i') }}
                    @endif
                </p>
            </div>
            @if($stockCount->isDraft())
                <div class="mt-4 md:mt-0">
                    <form action="{{ route('inventory.stock-counts.finalize', $stockCount) }}" method="POST"
                          onsubmit="return confirm('Finalize this stock count? System stock will be adjusted to match physical counts.');">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                            Finalize count
                        </button>
                    </form>
                </div>
            @endif
        </div>

        @include('inventory.partials.subnav')

        @if(session('success'))
            <div class="mt-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
        @endif

        @if($stockCount->notes)
            <div class="mt-4 bg-white shadow sm:rounded-lg p-4 text-sm text-gray-600">
                <strong class="text-gray-900">Notes:</strong> {{ $stockCount->notes }}
            </div>
        @endif

        <div class="mt-6 bg-white shadow sm:rounded-lg p-6">
            @livewire('inventory.edit-stock-count-lines', ['stockCount' => $stockCount], key('stock-count-'.$stockCount->id))
        </div>

        @if($stockCount->isFinalized() && $stockCount->finalizedBy)
            <p class="mt-4 text-xs text-gray-500">
                Finalized by {{ $stockCount->finalizedBy->name }} on {{ $stockCount->finalized_at?->format('M d, Y H:i') }}.
            </p>
        @endif
    </div>
</div>
</x-app-layout>
