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
                <a href="{{ route('inventory.orders.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to order goods</a>
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
                        'bg-violet-100 text-violet-800' => $order->budget_mode === \App\Models\InventoryOrder::BUDGET_MODE_DAYS,
                        'bg-emerald-100 text-emerald-800' => $order->budget_mode === \App\Models\InventoryOrder::BUDGET_MODE_AMOUNT,
                    ])>
                        {{ $order->orderingTypeLabel() }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500">
                    @if($order->isInternal())
                        {{ $order->sourceStore?->selectLabel() ?? '—' }} → {{ $order->store?->selectLabel() ?? '—' }}
                    @else
                        {{ $order->store->selectLabel() }}
                        @if($order->supplier)
                            · {{ $order->supplier->name }}
                        @endif
                    @endif
                    · {{ $order->orderingTypeValueLabel() }}
                </p>
            </div>
            @if($order->isDraft())
                <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
                    @if($order->isExternal())
                        @if($order->hasRfqDocument())
                            <a href="{{ $order->rfqDocumentUrl() }}"
                               target="_blank"
                               class="inline-flex items-center px-4 py-2 border border-blue-300 rounded-md shadow-sm text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100">
                                Download draft RFQ
                            </a>
                        @endif
                        <a href="{{ route('inventory.orders.pdf', $order) }}"
                           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Export RFQ PDF
                        </a>
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
                            {{ $order->isInternal() ? 'Submit for approval' : 'Submit RFQ for approval' }}
                        </button>
                    </form>
                </div>
            @else
                <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
                    @if($order->isExternal())
                        @if($order->hasRfqDocument())
                            <a href="{{ $order->rfqDocumentUrl() }}"
                               target="_blank"
                               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                Download RFQ document
                            </a>
                        @endif
                        <a href="{{ route('inventory.orders.pdf', $order) }}"
                           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Export RFQ PDF
                        </a>
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
                    <strong>Draft RFQ.</strong>
                    @if($order->hasRfqDocument())
                        A draft RFQ document has been generated automatically.
                    @endif
                    Choose the <strong>supplier</strong> for this order (above the line items), review quantities, then <strong>Submit RFQ for approval</strong>.
                @endif
            </div>
        @endif

        @if($order->isPendingApproval())
            <div class="mt-4 bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded text-sm">
                @if($order->isInternal())
                    <strong>Awaiting approval.</strong> After approvers sign off, create a stock transfer from {{ $order->sourceStore?->name }} to {{ $order->store?->name }}.
                @else
                    <strong>Awaiting RFQ approval.</strong> After approvers sign off, record supplier quotations and generate LPOs.
                @endif
            </div>
        @endif

        @if($order->isInternal() && $order->isFulfilled())
            <div class="mt-4 bg-blue-50 border border-blue-200 text-blue-900 px-4 py-3 rounded text-sm">
                <strong>Internal order approved.</strong>
                <a href="{{ route('inventory.transfers.create') }}" class="underline font-medium">Make a transfer request</a>
                from {{ $order->sourceStore?->name }} to {{ $order->store?->name }} to move stock.
            </div>
        @elseif($order->isRfqApproved())
            <div class="mt-4 bg-blue-50 border border-blue-200 text-blue-900 px-4 py-3 rounded text-sm">
                <strong>RFQ approved.</strong> Record supplier quotations below, accept a quote, then generate and issue an LPO. Goods are received only against issued LPOs.
            </div>
        @endif

        @if($order->isPoIssued() && ! $order->isFulfilled())
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
            $budgetCap = (float) ($order->budget_value ?? 0);
            $budgetCapEnforced = (bool) ($order->budget_cap_enforced ?? true);
            $budgetUsedPct = $amountCap !== null && $amountCap > 0
                ? round(($orderTotal / $amountCap) * 100, 1)
                : null;
            $budgetUsedDisplayPct = $budgetUsedPct !== null && $budgetCapEnforced
                ? min(100, $budgetUsedPct)
                : $budgetUsedPct;
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
                @if(! $order->budget_mode || $order->budget_mode === \App\Models\InventoryOrder::BUDGET_MODE_AMOUNT)
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
                            {{ number_format($budgetCap, 0) }} days
                        @elseif($order->budget_mode === \App\Models\InventoryOrder::BUDGET_MODE_AMOUNT)
                            UGX {{ number_format($budgetCap, 0) }}
                        @elseif($amountCap !== null && $amountCap > 0)
                            UGX {{ number_format($amountCap, 0) }}
                        @else
                            —
                        @endif
                    </p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        @if($order->budget_mode === \App\Models\InventoryOrder::BUDGET_MODE_AMOUNT)
                            UGX {{ number_format($orderTotal, 0) }} of cap
                            @if($order->isDraft())
                                · Cap {{ $budgetCapEnforced ? 'on' : 'off' }}
                            @endif
                        @elseif($order->budget_mode)
                            Applied at generation
                        @elseif($amountCap !== null && $amountCap > 0)
                            UGX {{ number_format($orderTotal, 0) }} of generated total
                            @if($order->isDraft())
                                · Cap {{ $budgetCapEnforced ? 'on' : 'off' }}
                            @endif
                        @else
                            Period-based order
                        @endif
                    </p>
                    @if($budgetUsedDisplayPct !== null)
                        <div class="mt-2 h-1.5 w-full rounded-full bg-gray-100 overflow-hidden">
                            <div @class([
                                'h-full rounded-full',
                                'bg-emerald-500' => $budgetUsedDisplayPct < 90,
                                'bg-amber-500' => $budgetUsedDisplayPct >= 90 && $budgetUsedDisplayPct < 100,
                                'bg-red-500' => $budgetUsedDisplayPct >= 100,
                            ]) style="width: {{ min(100, $budgetUsedDisplayPct) }}%"></div>
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
                        @if((float) ($order->peak_consumption_increase_percent ?? 0) > 0)
                            <span class="text-gray-400 font-normal">·</span>
                            +{{ number_format((float) $order->peak_consumption_increase_percent, 0) }}% consumption
                        @endif
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

        @if($order->isExternal() && $order->hasRfqDocument() && $order->isDraft())
            <div class="mt-4 bg-white shadow sm:rounded-lg p-4 sm:p-6">
                <h3 class="text-sm font-semibold text-gray-900">Draft RFQ document</h3>
                <p class="text-xs text-gray-500 mt-0.5">Generated automatically when this order was created. Refreshed when you use Refresh items or submit for approval.</p>
                <div class="mt-3">
                    <a href="{{ $order->rfqDocumentUrl() }}"
                       target="_blank"
                       class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-800">
                        {{ $order->rfq_document_original_name ?? ($order->order_number.'.pdf') }}
                    </a>
                </div>
            </div>
        @endif

        @if($order->canManageSupplierQuotations() && $order->supplier_id)
            <div class="mt-6 bg-white shadow sm:rounded-lg p-4 sm:p-6">
                <h3 class="text-sm font-semibold text-gray-900">Supplier quotation</h3>
                <p class="text-xs text-gray-500 mt-0.5">Record the quote from {{ $order->supplier?->name }}, accept it, then generate an LPO.</p>
                <div class="mt-4">
                    @include('inventory.orders.partials.supplier-quotation-card', ['order' => $order])
                </div>
            </div>
        @endif

        @if($order->purchaseOrders->isNotEmpty())
            <div class="mt-6 bg-white shadow sm:rounded-lg p-4 sm:p-6">
                <h3 class="text-sm font-semibold text-gray-900">Local purchase orders</h3>
                <ul class="mt-3 divide-y divide-gray-100 border border-gray-200 rounded-lg">
                    @foreach($order->purchaseOrders as $po)
                        <li class="px-4 py-3 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <a href="{{ route('inventory.purchase-orders.show', $po) }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">{{ $po->po_number }}</a>
                                <p class="text-xs text-gray-500">{{ $po->supplier?->name }} · {{ $po->statusLabel() }}</p>
                            </div>
                            <p class="text-sm font-semibold text-gray-900">UGX {{ number_format((float) $po->total_amount, 2) }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mt-6 bg-white shadow sm:rounded-lg p-4 sm:p-6 w-full min-w-0">
            <div class="mb-4">
                <h3 class="text-sm font-semibold text-gray-900">{{ $order->isInternal() ? 'Order line items' : 'RFQ line items' }}</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    @if($order->isDraft())
                        Use search and filters to find items. Paginated for large orders.
                    @else
                        Ordered vs received (SUOM). Received totals update when linked goods receive notes are approved.
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
                    <div @class([
                        'border rounded-lg p-4 text-sm',
                        'bg-green-50 border-green-200 text-green-800' => ! $order->isInternal(),
                        'bg-blue-50 border-blue-200 text-blue-900' => $order->isInternal(),
                    ])>
                        @if($order->isInternal())
                            Internal order approved. Create a stock transfer to move stock.
                            <a href="{{ route('inventory.transfers.create') }}" class="underline font-medium block mt-1">Make a transfer request</a>
                        @else
                            All order items have been fully received and posted to stock.
                            <a href="{{ route('inventory.monitor') }}" class="underline font-medium block mt-1">View Monitor Stock</a>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </div>

    @if($order->isDraft())
        <script>
            function confirmSubmitInventoryOrder() {
                Swal.fire({
                    title: '{{ $order->isInternal() ? 'Submit for approval?' : 'Submit RFQ for approval?' }}',
                    html: `@if($order->isInternal())Internal order <strong>{{ $order->order_number }}</strong> will be sent to your configured approvers. After approval, create a stock transfer to fulfill — no supplier quotation is required.@else RFQ <strong>{{ $order->order_number }}</strong> will be sent to your configured approvers. After approval, record supplier quotations before generating an LPO.@endif`,
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
