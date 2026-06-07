<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <a href="{{ route('inventory.returns.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back</a>
        <div class="mt-4 md:flex md:justify-between md:items-start">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">{{ $returnNote->reference }}</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $returnNote->store->selectLabel() }}
                    · {{ \App\Models\GoodsReturnNote::reasonOptions()[$returnNote->reason_code] ?? $returnNote->reason_code ?? 'No reason' }}
                    · {{ ucfirst($returnNote->status) }}
                </p>
            </div>
            @if($returnNote->isDraft())
                <form action="{{ route('inventory.returns.submit', $returnNote) }}" method="POST" class="mt-4"
                      onsubmit="return confirm('Submit this return? System stock will be reduced.');">@csrf
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm rounded-md">Submit return</button>
                </form>
            @endif
        </div>
        @include('inventory.partials.subnav')
        @if(session('success'))
            <div class="mt-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded text-sm">{{ session('success') }}</div>
        @endif
        <div class="mt-6 bg-white shadow sm:rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Item</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Batch</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600">Qty (SUOM)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($returnNote->lines as $line)
                        <tr>
                            <td class="px-4 py-3">{{ $line->item->name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $line->batch_number ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $line->quantity_suom, 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
</x-app-layout>
