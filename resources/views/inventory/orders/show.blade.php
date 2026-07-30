<x-app-layout>
@php
    $statusColors = match ($order->status) {
        'draft' => 'bg-gray-100 text-gray-800',
        'pending_approval' => 'bg-amber-100 text-amber-800',
        'approved' => 'bg-blue-100 text-blue-800',
        'po_issued' => 'bg-indigo-100 text-indigo-800',
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
                <a href="{{ route('inventory.orders.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to orders</a>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">{{ $order->order_number }}</h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700">{{ $order->documentLabel() }}</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $order->isInternal() ? 'bg-cyan-100 text-cyan-800' : 'bg-gray-100 text-gray-800' }}">
                        {{ $order->orderTypeLabel() }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors }}">
                        {{ $order->statusLabel() }}
                    </span>
                    <span @class([
                        'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                        'bg-blue-100 text-blue-800' => ! $order->budget_mode,
                        'bg-violet-100 text-violet-800' => (bool) $order->budget_mode,
                    ])>
                        {{ $order->orderingTypeLabel() }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500">
                    @if($order->isInternal())
                        {{ $order->sourceStore?->selectLabel() ?? '—' }} → {{ $order->store?->selectLabel() ?? '—' }}
                    @else
                        {{ $order->store->selectLabel() }}
                        @if($order->isExternal() && $order->invitedSuppliers->isNotEmpty())
                            · {{ $order->invitedSuppliers->count() }} supplier{{ $order->invitedSuppliers->count() === 1 ? '' : 's' }} invited
                        @endif
                    @endif
                    · {{ $order->orderingTypeValueLabel() }}
                </p>
            </div>
            @if($order->isDraft())
                <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
                    <a href="{{ route('inventory.orders.calculations', $order) }}"
                       class="inline-flex items-center px-4 py-2 border border-indigo-300 rounded-md shadow-sm text-sm font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100">
                        View calculation
                    </a>
                    @if($order->isExternal())
                        @include('inventory.partials.rfq-download-button', ['order' => $order, 'variant' => 'primary'])
                    @endif
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
            @else
                <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
                    <a href="{{ route('inventory.orders.calculations', $order) }}"
                       class="inline-flex items-center px-4 py-2 border border-indigo-300 rounded-md shadow-sm text-sm font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100">
                        View calculation
                    </a>
                    @if($order->isExternal())
                        @include('inventory.partials.rfq-download-button', ['order' => $order])
                    @endif
                    @if($order->canReceiveGoods())
                        <a href="{{ route('inventory.orders.receive', $order) }}"
                           class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                            Receive goods
                        </a>
                    @endif
                </div>
            @endif
        </div>

        @include('inventory.partials.subnav')

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3">
            <div class="min-w-0">
                <p class="text-sm font-medium text-indigo-950">Order calculation</p>
                <p class="text-xs text-indigo-800 mt-0.5">
                    See the full formula and how each line quantity was calculated (M, V/AA, N, AF, totals).
                </p>
            </div>
            <a href="{{ route('inventory.orders.calculations', $order) }}"
               class="shrink-0 inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                View calculation
            </a>
        </div>

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
                @if($order->isInternal())
                    <strong>Draft internal order.</strong> Review quantities for stock needed at <strong>{{ $order->store?->name }}</strong> from <strong>{{ $order->sourceStore?->name }}</strong>, then <strong>Submit for approval</strong>. After approval, fulfill via a stock transfer — no supplier quotation is required.
                @else
                    <strong>Purchase request.</strong>
                    Review quantities{{ ($evaluationCommitteeRequired ?? false) ? ', appoint the evaluation committee' : '' }}, then <strong>Submit for approval</strong>. After approval it becomes an RFQ and suppliers can be invited.
                @endif
            </div>
        @endif

        @if($order->isPendingApproval())
            <div class="mt-4 bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded text-sm">
                @if($order->isInternal())
                    <strong>Awaiting approval.</strong> After approvers sign off, a stock transfer draft is prepared for the supplying store to review and issue.
                @else
                    <strong>Awaiting approval.</strong> After approvers sign off, this purchase request becomes an RFQ that can be distributed to suppliers for quotations.
                @endif
            </div>
        @endif

        @if($order->isInternal() && $order->activeStockTransfer())
            @php $activeTransfer = $order->activeStockTransfer(); @endphp
            <div class="mt-4 bg-indigo-50 border border-indigo-200 text-indigo-900 px-4 py-3 rounded text-sm">
                <strong>Stock transfer in progress.</strong>
                <a href="{{ route('inventory.transfers.show', $activeTransfer) }}" class="underline font-medium">{{ $activeTransfer->reference }}</a>
                — {{ $activeTransfer->statusLabel() }}.
            </div>
        @elseif($order->isInternal() && $order->canCreateStockTransfer())
            <div class="mt-4 bg-blue-50 border border-blue-200 text-blue-900 px-4 py-3 rounded text-sm flex flex-wrap items-center justify-between gap-3">
                <div>
                    @if($order->isPartiallyReceived())
                        <strong>Partially fulfilled.</strong>
                        Create another stock transfer for the remaining quantities from {{ $order->sourceStore?->name }} to {{ $order->store?->name }}.
                    @else
                        <strong>Internal order approved.</strong>
                        Create a stock transfer for {{ $order->sourceStore?->name }} to review, issue, and confirm receipt at {{ $order->store?->name }}.
                    @endif
                </div>
                @if(empty($inventoryAdminContextBusiness))
                    <form action="{{ route('inventory.orders.create-transfer', $order) }}" method="POST">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                            {{ $order->isPartiallyReceived() ? 'Create next transfer' : 'Create stock transfer' }}
                        </button>
                    </form>
                @endif
            </div>
        @elseif($order->isInternal() && $order->isFulfilled())
            <div class="mt-4 bg-green-50 border border-green-200 text-green-900 px-4 py-3 rounded text-sm">
                <strong>Internal order fulfilled.</strong> Stock has been received at {{ $order->store?->name }}.
            </div>
        @elseif($order->isRfqApproved())
            <div class="mt-4 bg-blue-50 border border-blue-200 text-blue-900 px-4 py-3 rounded text-sm">
                <strong>RFQ approved.</strong> Next: invite suppliers, open quotation analysis, select suppliers per item (partial quantities allowed), then generate and issue LPOs. Goods are received only against issued LPOs.
            </div>
        @endif

        @if($order->isExternal() && $order->isPoIssued() && ! $order->isFulfilled())
            <div class="mt-4 bg-indigo-50 border border-indigo-200 text-indigo-900 px-4 py-3 rounded text-sm">
                <strong>LPO issued.</strong> Receive goods against the LPO — stock updates when each goods receive note is approved.
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
                ? (ItemImportanceCategory::labelForSlug((int) $order->business_id, $order->importance_filter) ?? $order->importance_filter)
                : 'All items';
            $scopeParts = array_filter([
                $importanceLabel !== 'All items' ? $importanceLabel : null,
                $order->group?->name,
                $order->subgroup?->name,
                ! empty($order->item_ids) ? count($order->item_ids).' selected items' : null,
            ]);
            $orderTotal = $order->orderTotal();
            $amountCap = $order->effectiveAmountCap();
            $budgetCapEnforced = (bool) ($order->budget_cap_enforced ?? true);
            $budgetUsedPct = $amountCap !== null && $amountCap > 0
                ? round(($orderTotal / $amountCap) * 100, 1)
                : null;
            $budgetUsedDisplayPct = $budgetUsedPct !== null && $budgetCapEnforced
                ? min(100, $budgetUsedPct)
                : $budgetUsedPct;

            $orderTabs = [
                'overview' => 'Overview',
                'items' => 'Line items',
            ];
            $hasProcurementTab = false;
            if ($order->isExternal()) {
                $orderTabs['committee'] = 'Committee';
                $hasProcurementTab = $order->canDownloadRfqPdf()
                    || $order->canManageSupplierQuotations()
                    || $order->purchaseOrders->isNotEmpty();
                if ($hasProcurementTab) {
                    $orderTabs['procurement'] = 'Procurement';
                }
            }
            $defaultOrderTab = request()->query('tab', 'overview');
            if (! array_key_exists($defaultOrderTab, $orderTabs)) {
                $defaultOrderTab = 'overview';
            }
        @endphp

        <div class="mt-6" x-data="{ tab: @js($defaultOrderTab) }">
            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <nav class="border-b border-gray-200 overflow-x-auto" aria-label="Order sections">
                    <div class="-mb-px flex min-w-max">
                        @foreach($orderTabs as $key => $label)
                            <button type="button"
                                    @click="tab = @js($key)"
                                    :class="tab === @js($key)
                                        ? 'border-blue-500 text-blue-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="px-4 py-3 text-sm font-medium border-b-2 whitespace-nowrap">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </nav>
            </div>

            <div x-show="tab === 'overview'" x-cloak class="mt-6 space-y-6">
        <div class="bg-white shadow sm:rounded-lg overflow-hidden">
            <div class="px-4 py-3 sm:px-6 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-gray-900">Order summary</h3>
                <p class="text-xs text-gray-500">
                    {{ $order->lines->count() }} item(s) · Order total
                    <span class="font-semibold text-gray-900">UGX {{ number_format($orderTotal, 2) }}</span>
                    @if($budgetUsedPct !== null)
                        <span class="text-gray-400">·</span>
                        <span @class([
                            'font-medium',
                            'text-emerald-700' => $budgetUsedDisplayPct < 90,
                            'text-amber-700' => $budgetUsedDisplayPct >= 90 && $budgetUsedDisplayPct < 100,
                            'text-red-700' => $budgetUsedDisplayPct >= 100,
                        ])>
                            @if($budgetCapEnforced)
                                {{ $budgetUsedDisplayPct }}% of cap
                            @else
                                {{ $budgetUsedPct }}% of original cap
                            @endif
                        </span>
                    @endif
                </p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 divide-y sm:divide-y-0 sm:divide-x divide-gray-100">
                <div class="px-4 py-3 sm:px-5">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Order type</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">{{ $order->orderingTypeLabel() }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $order->orderingTypeValueLabel() }}</p>
                </div>
                @if(! $order->budget_mode)
                <div class="px-4 py-3 sm:px-5">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Order period</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 tabular-nums">{{ number_format((float) ($order->period_of_order_days ?? 0), 0) }} days</p>
                    <p class="text-xs text-gray-500 mt-0.5">Drives suggested qty</p>
                </div>
                @endif
                <div class="px-4 py-3 sm:px-5">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Budget</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 tabular-nums">
                        @if($order->budget_mode === \App\Models\InventoryOrder::BUDGET_MODE_DAYS)
                            {{ number_format((float) ($order->budget_value ?? 0), 0) }} days
                        @elseif($order->budget_mode === \App\Models\InventoryOrder::BUDGET_MODE_AMOUNT)
                            UGX {{ number_format((float) ($order->budget_value ?? 0), 2) }}
                        @elseif($amountCap !== null && $amountCap > 0)
                            UGX {{ number_format($amountCap, 0) }}
                        @else
                            —
                        @endif
                    </p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        @if($order->budget_mode === \App\Models\InventoryOrder::BUDGET_MODE_DAYS)
                            Budget days (AH→AL)
                        @elseif($order->budget_mode === \App\Models\InventoryOrder::BUDGET_MODE_AMOUNT)
                            @if((float) ($order->period_of_order_days ?? 0) > 0)
                                ~{{ number_format((float) $order->period_of_order_days, 1) }} day pool (AH→AL)
                            @else
                                Budget UGX (AH→AL)
                            @endif
                            · UGX {{ number_format($orderTotal, 0) }} ordered
                            @if($order->isDraft())
                                · Cap {{ $budgetCapEnforced ? 'on' : 'off' }}
                            @endif
                        @elseif($amountCap !== null && $amountCap > 0)
                            UGX {{ number_format($orderTotal, 0) }} of generated total
                            @if($order->isDraft())
                                · Cap {{ $budgetCapEnforced ? 'on' : 'off' }}
                            @endif
                        @else
                            Period-based order
                        @endif
                    </p>
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
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Anticipated peak period</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 tabular-nums">
                        {{ number_format((float) ($order->peak_period_percent ?? 0), 0) }}%
                    </p>
                    <p class="text-xs text-gray-500 mt-0.5">Share of the order period that is peak</p>
                </div>
                <div class="px-4 py-3 sm:px-5">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Expected peak increase</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 tabular-nums">
                        {{ number_format((float) ($order->peak_consumption_increase_percent ?? 0), 0) }}%
                    </p>
                    <p class="text-xs text-gray-500 mt-0.5">Consumption increase during peak</p>
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

        @php
            $hasSidebar = (! $order->isDraft() && $order->approvals->isNotEmpty())
                || $canApprove
                || $order->goodsReceivedNotes->isNotEmpty()
                || $order->isFulfilled();
        @endphp

        @if($hasSidebar)
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 xl:grid-cols-3">
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
                        <h3 class="text-sm font-semibold text-gray-900 mb-3">Goods receive notes</h3>
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
                    <div class="border rounded-lg p-4 text-sm bg-green-50 border-green-200 text-green-800">
                        @if($order->isInternal())
                            Internal order fulfilled. Stock has been received at {{ $order->store?->name }}.
                        @else
                            All order items have been fully received and posted to stock.
                        @endif
                        <a href="{{ route('inventory.monitor') }}" class="underline font-medium block mt-1">View Monitor Stock</a>
                    </div>
                @endif
            </div>
        @endif
            </div>

            <div x-show="tab === 'items'" x-cloak class="mt-6">
        <div class="bg-white shadow sm:rounded-lg p-4 sm:p-6 w-full min-w-0">
            <div class="mb-4">
                <h3 class="text-sm font-semibold text-gray-900">
                    @if($order->isInternal())
                        Order line items
                    @elseif($order->isDraft() || $order->isPendingApproval())
                        Purchase request line items
                    @else
                        RFQ line items
                    @endif
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    @if($order->isDraft())
                        Use search and filters to find items. Paginated for large orders.
                    @elseif($order->isInternal())
                        Ordered vs received in sale units. Received totals accumulate when linked stock transfers are confirmed at the destination.
                    @else
                        Ordered vs received in sale units. Received totals update when linked goods receive notes are approved.
                    @endif
                </p>
            </div>
            @livewire('inventory.edit-inventory-order-lines', ['order' => $order], key('order-'.$order->id))
        </div>
            </div>

            @if($order->isExternal())
            <div x-show="tab === 'committee'" x-cloak class="mt-6">
                @include('inventory.partials.order-committee-form', [
                    'order' => $order,
                    'businessUsers' => $businessUsers ?? collect(),
                    'committeeChair' => $committeeChair ?? null,
                    'canManageCommittee' => $canManageCommittee ?? false,
                    'evaluationCommitteeRequired' => $evaluationCommitteeRequired ?? false,
                ])
            </div>
            @endif

            @if($order->isExternal() && $hasProcurementTab)
            <div x-show="tab === 'procurement'" x-cloak class="mt-6 space-y-6">
                @if($order->canDownloadRfqPdf() && $order->isDraft())
                    <div class="bg-white shadow sm:rounded-lg p-4 sm:p-6">
                        <h3 class="text-sm font-semibold text-gray-900">Purchase request document</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Download the current purchase request PDF. It is regenerated when you refresh items or submit for approval.</p>
                        <div class="mt-3">
                            @include('inventory.partials.rfq-download-button', ['order' => $order, 'variant' => 'primary'])
                        </div>
                    </div>
                @endif

                @if($order->canManageSupplierQuotations())
                    <div class="bg-white shadow sm:rounded-lg p-4 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">Quotation analysis &amp; supplier selection</h3>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    Invite suppliers, record quotes, allocate each RFQ line to one or more suppliers, then generate LPOs.
                                </p>
                            </div>
                            <a href="{{ route('inventory.orders.quotations.compare', $order) }}"
                               class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium text-white bg-violet-600 hover:bg-violet-700">
                                Open quotation analysis
                            </a>
                        </div>
                        @if($order->invitedSuppliers->isNotEmpty() || $order->supplierQuotations->isNotEmpty())
                            <ul class="mt-4 divide-y divide-gray-100 border border-gray-200 rounded-lg text-sm">
                                @foreach($order->invitedSuppliers as $supplier)
                                    @php
                                        $q = $order->supplierQuotations->firstWhere('supplier_id', $supplier->id);
                                    @endphp
                                    <li class="px-4 py-2.5 flex flex-wrap items-center justify-between gap-2">
                                        <span class="font-medium text-gray-900">{{ $supplier->name }}</span>
                                        <span class="text-xs text-gray-500">
                                            @if($q)
                                                {{ $q->statusLabel() }}
                                                @if($q->purchaseOrder)
                                                    · {{ $q->purchaseOrder->po_number }}
                                                @endif
                                            @else
                                                Invited — awaiting quotation
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif

                @if($order->purchaseOrders->isNotEmpty())
                    <div class="bg-white shadow sm:rounded-lg p-4 sm:p-6 border border-indigo-100">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">Local purchase orders</h3>
                                <p class="text-xs text-gray-500 mt-0.5">Open an LPO to issue it to the supplier, then receive goods against it.</p>
                            </div>
                            <a href="{{ route('inventory.purchase-orders.index') }}" class="text-sm font-medium text-indigo-700 hover:text-indigo-900">All LPOs →</a>
                        </div>
                        <ul class="mt-3 divide-y divide-gray-100 border border-gray-200 rounded-lg">
                            @foreach($order->purchaseOrders as $po)
                                <li class="px-4 py-3 flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <a href="{{ route('inventory.purchase-orders.show', $po) }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">{{ $po->po_number }}</a>
                                        <p class="text-xs text-gray-500">{{ $po->supplier?->name }} · {{ $po->statusLabel() }}</p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <p class="text-sm font-semibold text-gray-900">UGX {{ number_format((float) $po->total_amount, 2) }}</p>
                                        <a href="{{ route('inventory.purchase-orders.show', $po) }}" class="text-xs font-medium text-indigo-700 hover:underline">Open</a>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
            @endif
        </div>
    </div>

    @if($order->isDraft())
        <script>
            function confirmSubmitInventoryOrder() {
                Swal.fire({
                    title: @json($order->isInternal() ? 'Submit for approval?' : 'Submit purchase request for approval?'),
                    html: @json($order->isInternal()
                        ? 'Internal order <strong>'.e($order->order_number).'</strong> will be sent to your configured approvers. After approval, a stock transfer draft is prepared for the supplying store — no supplier quotation is required.'
                        : 'Purchase request <strong>'.e($order->order_number).'</strong> will be sent to your configured approvers. After approval it becomes an RFQ so you can invite suppliers and compare quotations before issuing LPOs.'),
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
