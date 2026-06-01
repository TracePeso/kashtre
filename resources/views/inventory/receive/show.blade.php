@php
    $statusLabel = match ($goodsReceivedNote->status) {
        'draft' => ['Draft', 'bg-gray-100 text-gray-800'],
        'pending_approval' => ['Pending approval', 'bg-amber-100 text-amber-800'],
        'approved' => ['Approved', 'bg-green-100 text-green-800'],
        'rejected' => ['Rejected', 'bg-red-100 text-red-800'],
        default => [ucfirst($goodsReceivedNote->status), 'bg-gray-100 text-gray-800'],
    };
@endphp
<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('inventory.receive') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to Receive Goods</a>
        </div>

        <div class="md:flex md:items-start md:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">{{ $goodsReceivedNote->grn_number }}</h2>
                <p class="mt-1 text-sm text-gray-500">Goods Received Note</p>
            </div>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $statusLabel[1] }}">{{ $statusLabel[0] }}</span>
        </div>

        @include('inventory.partials.subnav')

        @if(session('success'))
            <div class="mt-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
        @endif

        @if($goodsReceivedNote->isPending())
            <div class="mt-4 bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded text-sm">
                <strong>Awaiting approval.</strong> Stock is updated only after every configured approver has approved this GRN.
                @php
                    $pendingApprovals = $goodsReceivedNote->approvals->where('status', 'pending');
                @endphp
                @if($pendingApprovals->isNotEmpty())
                    Still waiting on:
                    {{ $pendingApprovals->map(fn ($a) => ($a->approver->name ?? 'Approver').' (step '.$a->approval_order.')')->join(', ') }}.
                @endif
            </div>
        @endif

        @if($goodsReceivedNote->isRejected() && $goodsReceivedNote->rejection_reason)
            <div class="mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded text-sm">
                <strong>Rejection reason:</strong> {{ $goodsReceivedNote->rejection_reason }}
            </div>
        @endif

        <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Delivery note</h3>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                        <div><dt class="text-gray-500">Supplier</dt><dd class="font-medium text-gray-900">{{ $goodsReceivedNote->supplier->name ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500">Store</dt><dd class="font-medium text-gray-900">{{ $goodsReceivedNote->store->name ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500">Date of order</dt><dd class="font-medium text-gray-900">{{ $goodsReceivedNote->date_of_order->format('M d, Y') }}</dd></div>
                        <div><dt class="text-gray-500">Date of delivery</dt><dd class="font-medium text-gray-900">{{ $goodsReceivedNote->date_of_delivery->format('M d, Y') }}</dd></div>
                        <div><dt class="text-gray-500">Lead time (days)</dt><dd class="font-medium text-gray-900">{{ $goodsReceivedNote->lead_time_days }}</dd></div>
                        <div><dt class="text-gray-500">Entry by</dt><dd class="font-medium text-gray-900">{{ $goodsReceivedNote->entryBy->name ?? '—' }}</dd></div>
                        <div class="sm:col-span-2">
                            <dt class="text-gray-500">Delivery note file</dt>
                            <dd class="font-medium text-gray-900 mt-1">
                                @if($goodsReceivedNote->delivery_note_path)
                                    @php
                                        $deliveryNoteUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($goodsReceivedNote->delivery_note_path);
                                        $isImage = preg_match('/\.(jpe?g|png|gif|webp)$/i', $goodsReceivedNote->delivery_note_path);
                                    @endphp
                                    <a href="{{ $deliveryNoteUrl }}" target="_blank" class="text-blue-600 hover:text-blue-800">
                                        {{ $goodsReceivedNote->delivery_note_original_name ?? 'View attachment' }}
                                    </a>
                                    @if($isImage)
                                        <img src="{{ $deliveryNoteUrl }}" alt="Delivery note" class="mt-2 max-h-48 rounded border border-gray-200">
                                    @endif
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap items-end justify-between gap-4">
                        <h3 class="text-lg font-medium text-gray-900">Line items</h3>
                        <div class="text-sm">
                            <span class="text-gray-500">Lead time (days):</span>
                            <span class="ml-1 font-semibold text-gray-900 tabular-nums">{{ $goodsReceivedNote->lead_time_days }}</span>
                            <span class="ml-1 text-xs text-gray-400">(delivery − order)</span>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Batch</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expiry</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">SUOM</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">No. sale units / purchase</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Quantity</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">DUOM</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Price</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sale units purchased</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($goodsReceivedNote->lines as $line)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900">{{ $line->item_name }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-gray-600">{{ $line->batch_number ?? '—' }}</td>
                                        <td class="px-4 py-3 text-gray-600">{{ $line->expiry_date?->format('M d, Y') ?? '—' }}</td>
                                        <td class="px-4 py-3 text-gray-600">{{ $line->suom ?? '—' }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums text-gray-600">{{ number_format($line->sale_units_per_purchase_unit, 4) }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($line->quantity, 4) }}</td>
                                        <td class="px-4 py-3 text-gray-600">{{ $line->duom ?? '—' }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($line->purchase_price, 2) }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums font-semibold text-emerald-900">{{ number_format($line->sale_units_purchased, 4) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Approval matrix</h3>
                    @if($goodsReceivedNote->approvals->isEmpty())
                        <p class="text-sm text-gray-500">Not submitted yet.</p>
                        @if($goodsReceivedNote->isDraft())
                            <form action="{{ route('inventory.receive.submit', $goodsReceivedNote) }}" method="POST" class="mt-4">
                                @csrf
                                <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                                    Submit for approval
                                </button>
                            </form>
                        @endif
                    @else
                        <ul class="space-y-3">
                            @foreach($goodsReceivedNote->approvals as $approval)
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
                    <div class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                        <h3 class="text-lg font-medium text-gray-900">Your action</h3>
                        <form action="{{ route('inventory.receive.approve', $goodsReceivedNote) }}" method="POST" class="space-y-3">
                            @csrf
                            <textarea name="comment" rows="2" placeholder="Optional comment"
                                      class="block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                            <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700">Approve</button>
                        </form>
                        <form action="{{ route('inventory.receive.reject', $goodsReceivedNote) }}" method="POST" class="space-y-3 border-t border-gray-200 pt-4">
                            @csrf
                            <textarea name="reason" rows="2" placeholder="Rejection reason (required)" required
                                      class="block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                            <button type="submit" class="w-full px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700">Reject</button>
                        </form>
                    </div>
                @endif

                @if($goodsReceivedNote->isApproved())
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-sm text-green-800">
                        @if($goodsReceivedNote->stock_applied_at)
                            Stock was updated on {{ $goodsReceivedNote->stock_applied_at->format('M d, Y H:i') }}
                            (qty of sale units purchased per line).
                        @else
                            Approved on {{ $goodsReceivedNote->approved_at?->format('M d, Y H:i') }} — stock will post shortly.
                        @endif
                        <a href="{{ route('inventory.monitor') }}" class="underline font-medium">View Monitor Stock</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
</x-app-layout>
