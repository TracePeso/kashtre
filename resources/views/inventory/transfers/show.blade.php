<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <a href="{{ route('inventory.transfers.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to transfers</a>
        <div class="mt-4 md:flex md:items-start md:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">{{ $transfer->reference }}</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $transfer->fromStore->selectLabel() }} → {{ $transfer->toStore->selectLabel() }}
                    · {{ str_replace('_', ' ', ucfirst($transfer->status)) }}
                </p>
            </div>
            <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
                @if($transfer->isDraft())
                    <form action="{{ route('inventory.transfers.submit', $transfer) }}" method="POST">@csrf
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md">Submit request</button>
                    </form>
                @endif
                @if($transfer->isPending())
                    <form action="{{ route('inventory.transfers.approve', $transfer) }}" method="POST">@csrf
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm rounded-md">Approve dispatch</button>
                    </form>
                    <form action="{{ route('inventory.transfers.reject', $transfer) }}" method="POST" class="flex gap-2 items-center">
                        @csrf
                        <input type="text" name="reason" required placeholder="Rejection reason" class="rounded-md border-gray-300 text-sm">
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white text-sm rounded-md">Reject</button>
                    </form>
                @endif
                @if($transfer->isApproved())
                    <form action="{{ route('inventory.transfers.receive', $transfer) }}" method="POST">@csrf
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm rounded-md">Confirm received</button>
                    </form>
                @endif
            </div>
        </div>

        @include('inventory.partials.subnav')

        @if(session('success'))
            <div class="mt-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded text-sm">{{ session('success') }}</div>
        @endif

        @if($transfer->rejection_reason)
            <div class="mt-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded text-sm">{{ $transfer->rejection_reason }}</div>
        @endif

        @if($transfer->notes)
            <div class="mt-4 bg-white shadow sm:rounded-lg p-4 text-sm text-gray-600"><strong>Notes:</strong> {{ $transfer->notes }}</div>
        @endif

        <div class="mt-6 bg-white shadow sm:rounded-lg p-6">
            @livewire('inventory.edit-stock-transfer-lines', ['transfer' => $transfer], key('transfer-'.$transfer->id))
        </div>
    </div>
</div>
</x-app-layout>
