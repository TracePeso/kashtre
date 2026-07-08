@php
    $hasSidebar = $transfer->approvals->isNotEmpty()
        || $canApprove
        || ($transfer->isDraft() && $transfer->approvals->isEmpty());
@endphp
<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <a href="{{ route('inventory.transfers.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to transfers</a>
        <div class="mt-4 md:flex md:items-start md:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">{{ $transfer->reference }}</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $transfer->fromStore->selectLabel() }} → {{ $transfer->toStore->selectLabel() }}
                    @if($transfer->inventoryOrder)
                        · <a href="{{ route('inventory.orders.show', $transfer->inventoryOrder) }}" class="text-blue-600 hover:text-blue-800">Internal order {{ $transfer->inventoryOrder->order_number }}</a>
                    @endif
                </p>
                <span class="mt-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                    @if($transfer->isDraft()) bg-gray-100 text-gray-800
                    @elseif($transfer->isPending()) bg-amber-100 text-amber-900
                    @elseif($transfer->isApproved()) bg-blue-100 text-blue-800
                    @elseif($transfer->isReceived()) bg-green-100 text-green-800
                    @else bg-red-100 text-red-800 @endif">
                    {{ $transfer->statusLabel() }}
                </span>
            </div>
            @if(empty($inventoryAdminContextBusiness))
                <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
                    @if($transfer->isDraft())
                        <form action="{{ route('inventory.transfers.submit', $transfer) }}" method="POST">@csrf
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md">Submit request</button>
                        </form>
                    @endif
                    @if($transfer->isApproved())
                        <form action="{{ route('inventory.transfers.receive', $transfer) }}" method="POST">@csrf
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm rounded-md">Confirm received</button>
                        </form>
                    @endif
                </div>
            @endif
        </div>

        @include('inventory.partials.subnav')

        @if(session('success'))
            <div class="mt-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded text-sm">{{ session('success') }}</div>
        @endif

        @if($transfer->isDraft())
            <div class="mt-4 bg-slate-50 border border-slate-200 text-slate-800 px-4 py-3 rounded text-sm">
                <strong>Draft request.</strong> Submit when quantities are correct. Configured approvers must sign off before stock is deducted from the dispatch store.
            </div>
        @endif

        @if($transfer->isPending())
            <div class="mt-4 bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded text-sm">
                <strong>Awaiting approvers.</strong> Stock at {{ $transfer->fromStore->selectLabel() }} stays unchanged until every configured approver has signed off.
                @if($transfer->requested_at)
                    Requested {{ $transfer->requested_at->format('M d, Y H:i') }}
                    @if($transfer->requestedBy) by {{ $transfer->requestedBy->name }} @endif.
                @endif
            </div>
        @endif

        @if($transfer->isApproved())
            <div class="mt-4 bg-blue-50 border border-blue-200 text-blue-900 px-4 py-3 rounded text-sm">
                <strong>Approved.</strong> Stock has been deducted from {{ $transfer->fromStore->selectLabel() }}.
                {{ $transfer->toStore->selectLabel() }} must confirm receipt to add stock.
            </div>
        @endif

        @if($transfer->isReceived())
            <div class="mt-4 bg-green-50 border border-green-200 text-green-900 px-4 py-3 rounded text-sm">
                <strong>Complete.</strong> Stock has been received at {{ $transfer->toStore->selectLabel() }}.
            </div>
        @endif

        @if($transfer->rejection_reason)
            <div class="mt-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded text-sm">
                <strong>Rejected.</strong> {{ $transfer->rejection_reason }}
            </div>
        @endif

        @if($transfer->notes)
            <div class="mt-4 bg-white shadow sm:rounded-lg p-4 text-sm text-gray-600"><strong>Notes:</strong> {{ $transfer->notes }}</div>
        @endif

        <div class="mt-6 bg-white shadow sm:rounded-lg p-6">
            @livewire('inventory.edit-stock-transfer-lines', ['transfer' => $transfer], key('transfer-'.$transfer->id))
        </div>

        @if($hasSidebar && empty($inventoryAdminContextBusiness))
            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2 xl:grid-cols-3">
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-900">Approvers</h3>
                    <p class="text-xs text-gray-500 mt-1 mb-4">
                        Same approver matrix as goods receive notes. Each person must approve in order. Stock is deducted only after the last approver signs off.
                    </p>
                    @if($transfer->approvals->isEmpty())
                        <p class="text-sm text-gray-500">Not submitted yet.</p>
                        @if($transfer->isDraft())
                            <form action="{{ route('inventory.transfers.submit', $transfer) }}" method="POST" class="mt-4">
                                @csrf
                                <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                                    Submit for approval
                                </button>
                            </form>
                            <p class="text-xs text-gray-500 mt-2">Approvers are set under Inventory → Goods receive note approvers.</p>
                        @endif
                    @else
                        <ul class="space-y-3">
                            @foreach($transfer->approvals as $approval)
                                <li class="border border-gray-200 rounded-md p-3">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-sm font-medium text-gray-900">{{ $approval->approver->name ?? '—' }}</span>
                                        <span @class([
                                            'text-xs font-medium px-2 py-0.5 rounded-full shrink-0',
                                            'bg-amber-100 text-amber-800' => $approval->status === 'pending',
                                            'bg-green-100 text-green-800' => $approval->status === 'approved',
                                            'bg-red-100 text-red-800' => $approval->status === 'rejected',
                                        ])>{{ ucfirst($approval->status) }}</span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">Step {{ $approval->approval_order }} of {{ $transfer->approvals->count() }}</p>
                                    @if($approval->comment)<p class="text-xs text-gray-500 mt-1">{{ $approval->comment }}</p>@endif
                                    @if($approval->acted_at)<p class="text-xs text-gray-400 mt-1">{{ $approval->acted_at->format('M d, Y H:i') }}</p>@endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                @if($canApprove)
                    <div class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                        <h3 class="text-sm font-semibold text-gray-900">Your decision</h3>
                        <p class="text-xs text-gray-500 -mt-2">
                            @if($transfer->approvals->where('status', 'pending')->count() === 1)
                                You are the final approver — stock will be deducted from {{ $transfer->fromStore->selectLabel() }} when you approve.
                            @else
                                More approvers are still required after you — stock will not be deducted yet.
                            @endif
                        </p>
                        <form action="{{ route('inventory.transfers.approve', $transfer) }}" method="POST" class="space-y-3">
                            @csrf
                            <textarea name="comment" rows="2" placeholder="Optional comment"
                                      class="block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                            <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700">Approve</button>
                        </form>
                        <form action="{{ route('inventory.transfers.reject', $transfer) }}" method="POST" class="space-y-3 border-t border-gray-200 pt-4">
                            @csrf
                            <textarea name="reason" rows="2" placeholder="Rejection reason (required)" required
                                      class="block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                            <button type="submit" class="w-full px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700">Reject</button>
                        </form>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
</x-app-layout>
