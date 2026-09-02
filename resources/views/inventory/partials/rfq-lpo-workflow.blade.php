@if($showEvaluationCommittee ?? false)
    <section class="bg-white shadow sm:rounded-lg overflow-hidden border border-slate-200">
        <div class="px-5 py-4 border-b border-gray-200 bg-slate-50/80">
            <h3 class="text-sm font-semibold text-gray-900">Evaluation committee</h3>
            <p class="text-xs text-gray-500 mt-0.5">Configured under Inventory → Settings. Required before LPOs are generated.</p>
        </div>
        <div class="px-5 py-4">
            @include('inventory.partials.order-committee-display', ['order' => $order])
        </div>
    </section>
@endif

<section class="bg-white shadow sm:rounded-lg overflow-hidden border border-indigo-100">
    <div class="px-5 py-4 border-b border-indigo-100 bg-indigo-50/60 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">Next: local purchase orders</h3>
            <p class="text-xs text-gray-500 mt-0.5">
                Accept the quotations you want to buy from, generate draft LPOs, then open each LPO and <strong>Issue LPO</strong> to email the supplier.
                Goods are received only against issued LPOs.
            </p>
        </div>
        @if(($acceptedWithoutLpoCount ?? 0) > 0)
            <form action="{{ route('inventory.orders.purchase-orders.generate-accepted', $order) }}" method="POST">
                @csrf
                <button type="submit" class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                    Generate LPOs for accepted quotes
                </button>
            </form>
        @endif
    </div>
    <ul class="divide-y divide-gray-100">
        @foreach($sheet['suppliers'] as $supplierRow)
            @php
                $supHasLpo = (bool) ($supplierRow['has_lpo'] ?? false);
                $supIsAccepted = (bool) ($supplierRow['is_accepted'] ?? false);
                $supCanAccept = (bool) ($supplierRow['can_accept'] ?? false);
                $supQuotationId = $supplierRow['quotation_id'] ?? null;
            @endphp
            <li class="px-5 py-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-gray-900">{{ $supplierRow['supplier_name'] }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Total: UGX {{ number_format($supplierRow['total_amount'], 2) }}
                        · {{ $supplierRow['status_label'] }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if($supHasLpo)
                        @php
                            $lpoQuotation = $order->supplierQuotations->firstWhere('id', $supQuotationId);
                        @endphp
                        @if($lpoQuotation && $lpoQuotation->purchaseOrder)
                            <a href="{{ route('inventory.purchase-orders.show', $lpoQuotation->purchaseOrder) }}"
                               class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 hover:bg-indigo-100">
                                Open {{ $lpoQuotation->purchaseOrder->po_number }}
                            </a>
                        @endif
                    @elseif($supIsAccepted)
                        <form action="{{ route('inventory.quotations.purchase-order', $supQuotationId) }}" method="POST">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                                Generate LPO
                            </button>
                        </form>
                    @elseif($supCanAccept)
                        <form action="{{ route('inventory.quotations.accept', $supQuotationId) }}" method="POST">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700">
                                Accept quotation
                            </button>
                        </form>
                        <form action="{{ route('inventory.quotations.reject', $supQuotationId) }}" method="POST">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-md hover:bg-red-100">
                                Reject
                            </button>
                        </form>
                    @else
                        <span class="text-xs text-gray-500">{{ $supplierRow['status_label'] }}</span>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
</section>
