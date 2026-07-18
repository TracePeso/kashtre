@php
    $statusLabel = match ($goodsReceivedNote->status) {
        'draft' => ['Draft', 'bg-gray-100 text-gray-800'],
        'pending_approval' => ['Pending approval', 'bg-amber-100 text-amber-800'],
        'approved' => ['Approved', 'bg-green-100 text-green-800'],
        'rejected' => ['Rejected', 'bg-red-100 text-red-800'],
        default => [ucfirst($goodsReceivedNote->status), 'bg-gray-100 text-gray-800'],
    };
    $itemCount = $goodsReceivedNote->lines->count();
    $totalSaleUnits = $goodsReceivedNote->lines->sum(fn ($line) => (float) $line->sale_units_purchased);
    $totalValue = $goodsReceivedNote->lines->sum(fn ($line) => (float) $line->quantity * (float) $line->purchase_price);
    $hasSidebar = $goodsReceivedNote->approvals->isNotEmpty()
        || $canApprove
        || ($goodsReceivedNote->isDraft() && $goodsReceivedNote->approvals->isEmpty());
@endphp
<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="w-full max-w-[1920px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="md:flex md:items-start md:justify-between gap-4">
            <div class="flex-1 min-w-0">
                <a href="{{ route('inventory.receive') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to Receive Goods</a>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">{{ $goodsReceivedNote->grn_number }}</h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusLabel[1] }}">{{ $statusLabel[0] }}</span>
                </div>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $goodsReceivedNote->supplier->name ?? '—' }} · {{ $goodsReceivedNote->store->name ?? '—' }}
                </p>
            </div>
        </div>

        @include('inventory.partials.subnav')

        @if(session('success'))
            <div class="mt-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
        @endif

        @if($goodsReceivedNote->isDraft())
            <div class="mt-4 bg-slate-50 border border-slate-200 text-slate-800 px-4 py-3 rounded text-sm">
                <strong>Draft goods receive note.</strong> Submit for approval when ready. Store stock is <strong>not</strong> updated until every approver has signed off.
            </div>
        @endif

        @if($goodsReceivedNote->isPending())
            <div class="mt-4 bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded text-sm">
                <strong>Waiting for approvers.</strong> Stock at {{ $goodsReceivedNote->store->name ?? 'this store' }} stays unchanged until all approvers have approved.
                @php
                    $pendingApprovals = $goodsReceivedNote->approvals->where('status', 'pending');
                @endphp
                @if($pendingApprovals->isNotEmpty())
                    Still waiting on:
                    {{ $pendingApprovals->map(fn ($a) => ($a->approver->name ?? 'Approver').' (step '.$a->approval_order.')')->join(', ') }}.
                @endif
            </div>
        @endif

        @if($goodsReceivedNote->isApproved())
            <div class="mt-4 bg-green-50 border border-green-200 text-green-900 px-4 py-3 rounded text-sm">
                @if($goodsReceivedNote->stock_applied_at)
                    <strong>Stock updated.</strong>
                    {{ number_format($totalSaleUnits, 0) }} sale units were added to
                    <strong>{{ $goodsReceivedNote->store->name ?? 'the store' }}</strong>
                    on {{ $goodsReceivedNote->stock_applied_at->format('M d, Y H:i') }} after all approvers signed off.
                @else
                    <strong>Approved.</strong> All approvers have signed off — stock is being posted to
                    <strong>{{ $goodsReceivedNote->store->name ?? 'the store' }}</strong>.
                @endif
                <a href="{{ route('inventory.monitor') }}" class="underline font-medium ml-1">View Monitor Stock</a>
            </div>
        @endif

        @if($goodsReceivedNote->isRejected() && $goodsReceivedNote->rejection_reason)
            <div class="mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded text-sm">
                <strong>Rejection reason:</strong> {{ $goodsReceivedNote->rejection_reason }}
            </div>
        @endif

        <div class="mt-6 bg-white shadow sm:rounded-lg overflow-hidden">
            <div class="px-4 py-3 sm:px-6 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-gray-900">Delivery summary</h3>
                <p class="text-xs text-gray-500">
                    {{ $itemCount }} item(s)
                    <span class="text-gray-400">·</span>
                    {{ number_format($totalSaleUnits, 0) }} sale units
                    <span class="text-gray-400">·</span>
                    <span class="font-semibold text-gray-900">UGX {{ number_format($totalValue, 2) }}</span>
                </p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 divide-y sm:divide-y-0 sm:divide-x divide-gray-100">
                <div class="px-4 py-3 sm:px-5">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Supplier</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">{{ $goodsReceivedNote->supplier->name ?? '—' }}</p>
                </div>
                <div class="px-4 py-3 sm:px-5">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Store</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">{{ $goodsReceivedNote->store->name ?? '—' }}</p>
                </div>
                <div class="px-4 py-3 sm:px-5">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Order date</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">{{ $goodsReceivedNote->date_of_order->format('M d, Y') }}</p>
                </div>
                <div class="px-4 py-3 sm:px-5">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Delivery date</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">{{ $goodsReceivedNote->date_of_delivery->format('M d, Y') }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">Lead time {{ $goodsReceivedNote->lead_time_days }} days</p>
                </div>
                <div class="px-4 py-3 sm:px-5">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Entry by</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">{{ $goodsReceivedNote->entryBy->name ?? '—' }}</p>
                    @if($goodsReceivedNote->inventoryOrder)
                        <p class="text-xs text-gray-500 mt-0.5">
                            Order
                            <a href="{{ route('inventory.orders.show', $goodsReceivedNote->inventoryOrder) }}" class="text-blue-600 hover:text-blue-800 font-medium">
                                {{ $goodsReceivedNote->inventoryOrder->order_number }}
                            </a>
                        </p>
                    @endif
                </div>
                <div class="px-4 py-3 sm:px-5">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Delivery note</p>
                    @if($goodsReceivedNote->delivery_note_path)
                        @php
                            $deliveryNoteUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($goodsReceivedNote->delivery_note_path);
                        @endphp
                        <a href="{{ $deliveryNoteUrl }}" target="_blank" class="mt-1 inline-block text-sm font-semibold text-blue-600 hover:text-blue-800 truncate max-w-full">
                            {{ $goodsReceivedNote->delivery_note_original_name ?? 'View attachment' }}
                        </a>
                    @else
                        <p class="mt-1 text-sm font-semibold text-gray-900">—</p>
                    @endif
                </div>
            </div>

            @if($goodsReceivedNote->delivery_note_path && preg_match('/\.(jpe?g|png|gif|webp)$/i', $goodsReceivedNote->delivery_note_path))
                @php
                    $deliveryNoteUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($goodsReceivedNote->delivery_note_path);
                @endphp
                <div class="px-4 py-3 sm:px-6 border-t border-gray-100 bg-gray-50/80">
                    <img src="{{ $deliveryNoteUrl }}" alt="Delivery note" class="max-h-40 rounded border border-gray-200">
                </div>
            @endif
        </div>

        <div class="mt-6 bg-white shadow sm:rounded-lg p-4 sm:p-6 w-full min-w-0">
            <div class="mb-4">
                <h3 class="text-sm font-semibold text-gray-900">Received items</h3>
                <p class="text-xs text-gray-500 mt-0.5">Quantities, units, and purchase prices for this delivery.</p>
            </div>
            @livewire('inventory.show-goods-received-note-lines', ['goodsReceivedNote' => $goodsReceivedNote], key('grn-items-'.$goodsReceivedNote->id))
        </div>

        @if($goodsReceivedNote->isDraft() || $goodsReceivedNote->isPending())
            <div class="mt-6 bg-white shadow sm:rounded-lg p-4 sm:p-6">
                <h3 class="text-sm font-semibold text-gray-900">QC / inspection</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    Mandatory before final approval. Compare received qty to ordered qty and confirm condition.
                    Current status:
                    <strong>{{ $goodsReceivedNote->inspection_status ? ucfirst($goodsReceivedNote->inspection_status) : 'Not recorded' }}</strong>
                    @if($goodsReceivedNote->inspectedBy)
                        · by {{ $goodsReceivedNote->inspectedBy->name }}
                        @if($goodsReceivedNote->inspected_at) on {{ $goodsReceivedNote->inspected_at->format('d M Y H:i') }}@endif
                    @endif
                </p>

                <div class="mt-4 overflow-x-auto rounded border border-gray-200">
                    <table class="min-w-full text-xs">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left">Item</th>
                                <th class="px-3 py-2 text-right">Ordered</th>
                                <th class="px-3 py-2 text-right">Received</th>
                                <th class="px-3 py-2 text-right">Variance</th>
                                <th class="px-3 py-2 text-left">Condition</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($goodsReceivedNote->lines as $line)
                                @php
                                    $ordered = $line->ordered_quantity !== null ? (float) $line->ordered_quantity : null;
                                    $received = (float) $line->quantity;
                                    $variance = $ordered !== null ? round($received - $ordered, 4) : (float) ($line->variance_quantity ?? 0);
                                @endphp
                                <tr @class(['bg-amber-50' => abs($variance) > 0.0001])>
                                    <td class="px-3 py-2">{{ $line->item_name ?? $line->item?->name }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $ordered !== null ? number_format($ordered, 2) : '—' }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format($received, 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums font-medium {{ abs($variance) > 0.0001 ? 'text-amber-800' : 'text-gray-600' }}">
                                        {{ $ordered !== null ? number_format($variance, 2) : '—' }}
                                    </td>
                                    <td class="px-3 py-2">{{ $line->condition_status ? ucfirst($line->condition_status) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <form action="{{ route('inventory.receive.inspect', $goodsReceivedNote) }}" method="POST" class="mt-4 space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700">Inspection result</label>
                            <select name="inspection_status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="passed" @selected($goodsReceivedNote->inspection_status === 'passed')>Passed</option>
                                <option value="failed" @selected($goodsReceivedNote->inspection_status === 'failed')>Failed</option>
                                <option value="pending" @selected($goodsReceivedNote->inspection_status === 'pending' || ! $goodsReceivedNote->inspection_status)>Pending</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700">Notes / why</label>
                            <input type="text" name="inspection_notes" value="{{ $goodsReceivedNote->inspection_notes }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"
                                   placeholder="Variance explanation, damage notes…">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach($goodsReceivedNote->lines as $line)
                            <div>
                                <label class="block text-xs text-gray-600">{{ \Illuminate\Support\Str::limit($line->item_name ?? $line->item?->name, 40) }} condition</label>
                                <select name="line_conditions[{{ $line->id }}]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    @foreach(['good','damaged','expired','short'] as $cond)
                                        <option value="{{ $cond }}" @selected(($line->condition_status ?? 'good') === $cond)>{{ ucfirst($cond) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                    <button type="submit" class="px-4 py-2 bg-slate-800 text-white text-sm font-medium rounded-md hover:bg-slate-900">
                        Save QC inspection
                    </button>
                </form>
            </div>
        @endif

        @if($hasSidebar)
            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2 xl:grid-cols-3">
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-900">Approvers</h3>
                    <p class="text-xs text-gray-500 mt-1 mb-4">
                        Each person below must approve in order. Stock is updated only after the last approver signs off.
                    </p>
                    @if($goodsReceivedNote->approvals->isEmpty())
                        <p class="text-sm text-gray-500">Not submitted yet.</p>
                        @if($goodsReceivedNote->isDraft())
                            <form action="{{ route('inventory.receive.submit', $goodsReceivedNote) }}" method="POST" class="mt-4">
                                @csrf
                                <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                                    Submit for approval
                                </button>
                            </form>
                            <p class="text-xs text-gray-500 mt-2">Approvers are set under Inventory → Goods receive note approvers.</p>
                        @endif
                    @else
                        <ul class="space-y-3">
                            @foreach($goodsReceivedNote->approvals as $approval)
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
                                    <p class="text-xs text-gray-500 mt-1">
                                        @if((int) $approval->approval_order === 0)
                                            Technical supervisor
                                        @else
                                            Approver {{ $approval->approval_order }}
                                        @endif
                                        · Step {{ $loop->iteration }} of {{ $goodsReceivedNote->approvals->count() }}
                                    </p>
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
                            @if(! $goodsReceivedNote->inspectionPassed())
                                <span class="text-amber-700 font-medium">QC inspection must be marked Passed before the final approver can post stock.</span>
                            @elseif($goodsReceivedNote->approvals->where('status', 'pending')->count() === 1)
                                You are the final approver — stock will update at {{ $goodsReceivedNote->store->name ?? 'the store' }} when you approve.
                            @else
                                More approvers are still required after you — stock will not change yet.
                            @endif
                        </p>
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

            </div>
        @endif
    </div>
</div>
</x-app-layout>
