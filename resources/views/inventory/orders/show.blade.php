<x-app-layout>
@php
    $statusColors = match ($order->status) {
        'draft' => 'bg-gray-100 text-gray-800',
        'pending_approval' => 'bg-amber-100 text-amber-800',
        'approved' => 'bg-blue-100 text-blue-800',
        'partially_received' => 'bg-indigo-100 text-indigo-800',
        'fulfilled' => 'bg-green-100 text-green-800',
        'rejected' => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-800',
    };
@endphp
<div class="min-h-screen bg-gray-50 py-6">
    <div class="w-full max-w-[1920px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="md:flex md:items-start md:justify-between gap-4">
            <div class="flex-1 min-w-0">
                <a href="{{ route('inventory.orders.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to order goods</a>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">{{ $order->order_number }}</h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors }}">
                        {{ $order->statusLabel() }}
                    </span>
                    <span @class([
                        'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                        'bg-blue-100 text-blue-800' => ! $order->budget_mode,
                        'bg-violet-100 text-violet-800' => $order->budget_mode === \App\Models\InventoryOrder::BUDGET_MODE_DAYS,
                        'bg-emerald-100 text-emerald-800' => $order->budget_mode === \App\Models\InventoryOrder::BUDGET_MODE_AMOUNT,
                    ])>
                        {{ $order->orderingTypeLabel() }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $order->store->selectLabel() }} · {{ $order->orderingTypeValueLabel() }}
                </p>
            </div>
            @if($order->isDraft())
                <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
                    <form action="{{ route('inventory.orders.regenerate', $order) }}" method="POST">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Refresh items
                        </button>
                    </form>
                    <form id="inventory-order-submit-form" action="{{ route('inventory.orders.submit', $order) }}" method="POST">
                        @csrf
                        <button type="button"
                                onclick="confirmSubmitInventoryOrder()"
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                            Submit for approval
                        </button>
                    </form>
                </div>
            @elseif($order->canReceiveGoods() && !empty($receiptOptions))
                <div class="mt-4 md:mt-0">
                    <a href="{{ route('inventory.orders.receive', $order) }}"
                       class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                        Receive goods
                    </a>
                </div>
            @endif
        </div>

        @include('inventory.partials.subnav')

        @if(session('success'))
            <div class="mt-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
        @endif

        @if(session('warning'))
            <div class="mt-4 bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded text-sm">{{ session('warning') }}</div>
        @endif

        @if($errors->any())
            <div class="mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded text-sm">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($order->isDraft())
            <div class="mt-4 bg-slate-50 border border-slate-200 text-slate-800 px-4 py-3 rounded text-sm">
                <strong>Draft order.</strong> Review items below, then <strong>Submit for approval</strong>. Receiving goods is only available after approvers sign off.
            </div>
        @endif

        @if($order->isPendingApproval())
            <div class="mt-4 bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded text-sm">
                <strong>Awaiting order approval.</strong> Goods can be received only after all configured approvers have signed off.
            </div>
        @endif

        @if($order->isApproved())
            <div class="mt-4 bg-blue-50 border border-blue-200 text-blue-900 px-4 py-3 rounded text-sm">
                <strong>Order approved.</strong> Record deliveries via Receive goods — stock updates when each GRN is approved.
            </div>
        @endif

        @if($order->isRejected() && $order->rejection_reason)
            <div class="mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded text-sm">
                <strong>Rejection reason:</strong> {{ $order->rejection_reason }}
            </div>
        @endif

        @if(!empty($emptyOrderReason))
            <div class="mt-4 bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded text-sm">
                <strong>No order items were generated.</strong> {{ $emptyOrderReason }}
            </div>
        @endif

        @php
            $importanceLabel = $order->importance_filter
                ? (\App\Models\Item::importanceOptions()[$order->importance_filter] ?? $order->importance_filter)
                : 'All items';
            $scopeParts = array_filter([
                $importanceLabel !== 'All items' ? $importanceLabel : null,
                $order->group?->name,
                $order->subgroup?->name,
                ! empty($order->item_ids) ? count($order->item_ids).' selected items' : null,
            ]);
            $orderTotal = $order->orderTotal();
            $budgetCap = (float) ($order->budget_value ?? 0);
            $budgetUsedPct = $order->budget_mode === \App\Models\InventoryOrder::BUDGET_MODE_AMOUNT && $budgetCap > 0
                ? min(100, round(($orderTotal / $budgetCap) * 100, 1))
                : null;
        @endphp

        <div class="mt-6 bg-white shadow sm:rounded-lg overflow-hidden">
            <div class="px-4 py-3 sm:px-6 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-gray-900">Order summary</h3>
                <p class="text-xs text-gray-500">
                    {{ $order->lines->count() }} item(s) · Order total
                    <span class="font-semibold text-gray-900">UGX {{ number_format($orderTotal, 2) }}</span>
                    @if($budgetUsedPct !== null)
                        <span class="text-gray-400">·</span>
                        <span @class([
                            'font-medium',
                            'text-emerald-700' => $budgetUsedPct < 90,
                            'text-amber-700' => $budgetUsedPct >= 90 && $budgetUsedPct < 100,
                            'text-red-700' => $budgetUsedPct >= 100,
                        ])>{{ $budgetUsedPct }}% of cap</span>
                    @endif
                </p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 divide-y sm:divide-y-0 sm:divide-x divide-gray-100">
                <div class="px-4 py-3 sm:px-5">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Order type</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">{{ $order->orderingTypeLabel() }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $order->orderingTypeValueLabel() }}</p>
                </div>
                <div class="px-4 py-3 sm:px-5">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Order period</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 tabular-nums">{{ number_format((float) ($order->period_of_order_days ?? 0), 0) }} days</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        @if($order->budget_mode === \App\Models\InventoryOrder::BUDGET_MODE_AMOUNT)
                            Before amount cap
                        @elseif($order->budget_mode === \App\Models\InventoryOrder::BUDGET_MODE_DAYS)
                            Reference period
                        @else
                            Drives suggested qty
                        @endif
                    </p>
                </div>
                <div class="px-4 py-3 sm:px-5">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Budget</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 tabular-nums">
                        @if($order->budget_mode === \App\Models\InventoryOrder::BUDGET_MODE_DAYS)
                            {{ number_format($budgetCap, 0) }} days
                        @elseif($order->budget_mode === \App\Models\InventoryOrder::BUDGET_MODE_AMOUNT)
                            UGX {{ number_format($budgetCap, 0) }}
                        @else
                            —
                        @endif
                    </p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        @if($order->budget_mode === \App\Models\InventoryOrder::BUDGET_MODE_AMOUNT)
                            UGX {{ number_format($orderTotal, 0) }} of cap
                        @elseif($order->budget_mode)
                            Applied at generation
                        @else
                            Period-based order
                        @endif
                    </p>
                    @if($budgetUsedPct !== null)
                        <div class="mt-2 h-1.5 w-full rounded-full bg-gray-100 overflow-hidden">
                            <div @class([
                                'h-full rounded-full',
                                'bg-emerald-500' => $budgetUsedPct < 90,
                                'bg-amber-500' => $budgetUsedPct >= 90 && $budgetUsedPct < 100,
                                'bg-red-500' => $budgetUsedPct >= 100,
                            ]) style="width: {{ $budgetUsedPct }}%"></div>
                        </div>
                    @endif
                </div>
                <div class="px-4 py-3 sm:px-5">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Safety / buffer</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 tabular-nums">
                        {{ number_format((float) ($order->safety_stock_days ?? 0), 0) }}
                        <span class="text-gray-400 font-normal">/</span>
                        {{ number_format((float) ($order->buffer_stock_days ?? 0), 0) }} days
                    </p>
                    <p class="text-xs text-gray-500 mt-0.5">Notify {{ number_format((float) ($order->notification_to_order_days ?? 0), 0) }} days ahead</p>
                </div>
                <div class="px-4 py-3 sm:px-5">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Peak period</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 tabular-nums">
                        {{ number_format((float) ($order->peak_period_percent ?? 0), 0) }}%
                    </p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $order->moving_average_days }}-day consumption rate</p>
                </div>
                <div class="px-4 py-3 sm:px-5">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Item scope</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">{{ $importanceLabel }}</p>
                    <p class="text-xs text-gray-500 mt-0.5 truncate" title="{{ implode(' · ', $scopeParts) ?: 'All stocked items at store' }}">
                        {{ $scopeParts !== [] ? implode(' · ', $scopeParts) : 'All stocked items at store' }}
                    </p>
                </div>
            </div>

            @if($order->notes)
                <div class="px-4 py-3 sm:px-6 border-t border-gray-100 bg-gray-50/80">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Notes</p>
                    <p class="mt-1 text-sm text-gray-800 whitespace-pre-wrap">{{ $order->notes }}</p>
                </div>
            @endif
        </div>

        <div class="mt-6 bg-white shadow sm:rounded-lg p-4 sm:p-6 w-full min-w-0">
            <div class="mb-4">
                <h3 class="text-sm font-semibold text-gray-900">Order items</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    @if($order->isDraft())
                        Use search and filters to find items. Paginated for large orders.
                    @else
                        Ordered vs received (SUOM). Received totals update when linked GRNs are approved.
                    @endif
                </p>
            </div>
            @livewire('inventory.edit-inventory-order-lines', ['order' => $order], key('order-'.$order->id))
        </div>

        @php
            $hasSidebar = (! $order->isDraft() && $order->approvals->isNotEmpty())
                || $canApprove
                || $order->goodsReceivedNotes->isNotEmpty()
                || $order->isFulfilled();
        @endphp

        @if($hasSidebar)
            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2 xl:grid-cols-3">
                @if(!$order->isDraft())
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">Order approval</h3>
                    @if($order->approvals->isEmpty())
                        <p class="text-sm text-gray-500">Not submitted yet.</p>
                    @else
                        <ul class="space-y-3">
                            @foreach($order->approvals as $approval)
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
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                @endif

                @if($canApprove)
                    <div class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                        <h3 class="text-sm font-semibold text-gray-900">Your action</h3>
                        <form action="{{ route('inventory.orders.approve', $order) }}" method="POST" class="space-y-3">
                            @csrf
                            <textarea name="comment" rows="2" placeholder="Optional comment"
                                      class="block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                            <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700">Approve order</button>
                        </form>
                        <form action="{{ route('inventory.orders.reject', $order) }}" method="POST" class="space-y-3 border-t border-gray-200 pt-4">
                            @csrf
                            <textarea name="reason" rows="2" placeholder="Rejection reason (required)" required
                                      class="block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                            <button type="submit" class="w-full px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700">Reject order</button>
                        </form>
                    </div>
                @endif

                @if($order->goodsReceivedNotes->isNotEmpty())
                    <div class="bg-white shadow sm:rounded-lg p-6">
                        <h3 class="text-sm font-semibold text-gray-900 mb-3">Goods received notes</h3>
                        <ul class="space-y-2 text-sm">
                            @foreach($order->goodsReceivedNotes as $grn)
                                <li>
                                    <a href="{{ route('inventory.receive.show', $grn) }}" class="text-blue-600 hover:text-blue-800 font-medium">{{ $grn->grn_number }}</a>
                                    <span class="text-gray-500"> · {{ str_replace('_', ' ', $grn->status) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if($order->isFulfilled())
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-sm text-green-800">
                        All order items have been fully received and posted to stock.
                        <a href="{{ route('inventory.monitor') }}" class="underline font-medium block mt-1">View Monitor Stock</a>
                    </div>
                @endif
            </div>
        @endif
    </div>

    @if($order->isDraft())
        <script>
            function confirmSubmitInventoryOrder() {
                Swal.fire({
                    title: 'Submit for approval?',
                    html: 'Order <strong>{{ $order->order_number }}</strong> will be sent to your configured approvers. You will not be able to edit quantities after this.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, submit',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#16a34a',
                    cancelButtonColor: '#6b7280',
                    reverseButtons: true,
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('inventory-order-submit-form').submit();
                    }
                });
            }
        </script>
    @endif
</div>
</x-app-layout>
