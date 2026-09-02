@php
    $statusLabel = match ($stockCount->status) {
        'draft' => ['Draft', 'bg-gray-100 text-gray-800'],
        'pending_approval' => ['Pending approval', 'bg-amber-100 text-amber-800'],
        'approved' => ['Approved', 'bg-green-100 text-green-800'],
        'finalized' => ['Approved', 'bg-green-100 text-green-800'],
        'rejected' => ['Rejected', 'bg-red-100 text-red-800'],
        default => [ucfirst($stockCount->status), 'bg-gray-100 text-gray-800'],
    };
@endphp
<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <a href="{{ route('inventory.stock-counts.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to stock counts</a>
                <h2 class="mt-2 text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">{{ $stockCount->reference }}</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $stockCount->store->selectLabel() }}
                    @if($stockCount->counted_at)
                        · Counted {{ $stockCount->counted_at->format('M d, Y H:i') }}
                    @endif
                </p>
            </div>
            <span class="mt-4 md:mt-0 inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $statusLabel[1] }}">{{ $statusLabel[0] }}</span>
        </div>

        @include('inventory.partials.subnav')

        @if(session('success'))
            <div class="mt-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
        @endif

        @if($stockCount->isPending())
            <div class="mt-4 bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded text-sm">
                <strong>Awaiting approval.</strong> System stock is updated only after every configured approver has approved this stock count.
                @php
                    $pendingApprovals = $stockCount->approvals->where('status', 'pending');
                @endphp
                @if($pendingApprovals->isNotEmpty())
                    Still waiting on:
                    {{ $pendingApprovals->map(fn ($a) => ($a->approver->name ?? 'Approver').' (step '.$a->approval_order.')')->join(', ') }}.
                @endif
            </div>
        @endif

        @if($stockCount->isRejected() && $stockCount->rejection_reason)
            <div class="mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded text-sm">
                <strong>Rejection reason:</strong> {{ $stockCount->rejection_reason }}
            </div>
        @endif

        @if($stockCount->notes)
            <div class="mt-4 bg-white shadow sm:rounded-lg p-4 text-sm text-gray-600">
                <strong class="text-gray-900">Notes:</strong> {{ $stockCount->notes }}
            </div>
        @endif

        <div class="mt-6 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6">
                @livewire('inventory.edit-stock-count-lines', ['stockCount' => $stockCount], key('stock-count-'.$stockCount->id))
            </div>

            @if($stockCount->isApproved() && $stockCount->finalizedBy)
                <p class="text-xs text-gray-500">
                    Approved by {{ $stockCount->finalizedBy->name }} on {{ $stockCount->approved_at?->format('M d, Y H:i') }}.
                    @if($stockCount->stock_applied_at)
                        Stock updated on {{ $stockCount->stock_applied_at->format('M d, Y H:i') }}.
                    @endif
                </p>
            @endif

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Approvals</h3>
                @if($stockCount->approvals->isEmpty())
                    <p class="text-sm text-gray-500">Not submitted yet.</p>
                    @if($stockCount->isDraft())
                        <form action="{{ route('inventory.stock-counts.submit', $stockCount) }}" method="POST" class="mt-4 max-w-sm"
                              onsubmit="return confirm('Submit this stock count for approval? Stock levels will be updated only after all approvers sign off.');">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                                Submit for approval
                            </button>
                        </form>
                    @endif
                @else
                    <ul class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($stockCount->approvals as $approval)
                            <li class="border border-gray-200 rounded-md p-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-900">Approver {{ $approval->approval_order }}</span>
                                    <span @class([
                                        'text-xs font-medium px-2 py-0.5 rounded-full',
                                        'bg-amber-100 text-amber-800' => $approval->status === 'pending',
                                        'bg-green-100 text-green-800' => $approval->status === 'approved',
                                        'bg-red-100 text-red-800' => $approval->status === 'rejected',
                                    ])>{{ ucfirst($approval->status) }}</span>
                                </div>
                                <p class="text-sm text-gray-600 mt-1">{{ $approval->approver->name ?? '—' }}</p>
                                @if($approval->comment)<p class="text-xs text-gray-500 mt-1">{{ $approval->comment }}</p>@endif
                                @if($approval->acted_at)<p class="text-xs text-gray-400 mt-1">{{ $approval->acted_at->format('M d, Y H:i') }}</p>@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @if($canApprove)
                <div class="bg-white shadow sm:rounded-lg p-6 space-y-4 max-w-xl">
                    <h3 class="text-lg font-medium text-gray-900">Your action</h3>
                    <form action="{{ route('inventory.stock-counts.approve', $stockCount) }}" method="POST" class="space-y-3">
                        @csrf
                        <textarea name="comment" rows="2" placeholder="Optional comment"
                                  class="block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                        <button type="submit" class="w-full sm:w-auto px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700">Approve</button>
                    </form>
                    <form action="{{ route('inventory.stock-counts.reject', $stockCount) }}" method="POST" class="space-y-3 border-t border-gray-200 pt-4">
                        @csrf
                        <textarea name="reason" rows="2" placeholder="Rejection reason (required)" required
                                  class="block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                        <button type="submit" class="w-full sm:w-auto px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700">Reject</button>
                    </form>
                </div>
            @endif

            @if($stockCount->isApproved())
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-sm text-green-800">
                    @if($stockCount->stock_applied_at)
                        Stock was updated on {{ $stockCount->stock_applied_at->format('M d, Y H:i') }}.
                    @else
                        Approved on {{ $stockCount->approved_at?->format('M d, Y H:i') }} — stock will post shortly.
                    @endif
                    <a href="{{ route('inventory.monitor') }}" class="underline font-medium">View Monitor Stock</a>
                </div>
            @endif
        </div>
    </div>
</div>
</x-app-layout>
