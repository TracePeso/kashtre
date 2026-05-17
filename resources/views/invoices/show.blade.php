<x-app-layout>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex justify-between items-center">
                        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                            {{ __('Invoice Details') }} - {{ $invoice->invoice_number }}
                        </h2>
                        <div class="flex space-x-3">
                            <a href="{{ route('invoices.index') }}" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 transition-colors">
                                Back to Invoices
                            </a>
                            <a href="{{ route('invoices.print', $invoice) }}" target="_blank" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">
                                Print Invoice
                            </a>
                            <button onclick="generateQuotation({{ $invoice->id }})" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors">
                                Generate Quotation
                            </button>
                            @if($invoice->quotations->count() > 0)
                                @php
                                    $latestQuotation = $invoice->quotations->sortByDesc('created_at')->first();
                                @endphp
                                <a href="{{ route('quotations.print', $latestQuotation) }}" target="_blank" class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700 transition-colors">
                                    Print Quotation
                                </a>
                            @else
                                <button disabled class="px-4 py-2 bg-gray-400 text-white rounded cursor-not-allowed" title="No quotations generated yet. Use 'Generate Quotation' button first.">
                                    Print Quotation
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            @if($invoice->parent_invoice_id && $invoice->parentInvoice)
                <div class="bg-amber-50 border border-amber-200 rounded-lg shadow-sm mb-6 p-4">
                    <p class="text-sm font-medium text-amber-900">Vendor portion invoice (label {{ $invoice->vendor_portion_label ?? '—' }})</p>
                    <p class="text-xs text-amber-800 mt-1">This row is a trace copy for a single insurer submission in a multi-insurer cascade. Balances and settlement follow the primary invoice.</p>
                    <a href="{{ route('invoices.show', $invoice->parentInvoice) }}" class="inline-block mt-2 text-sm font-semibold text-amber-900 underline hover:text-amber-950">
                        Open primary invoice {{ $invoice->parentInvoice->invoice_number }}
                    </a>
                </div>
            @elseif($invoice->vendorPortionInvoices->isNotEmpty())
                <div class="bg-slate-50 border border-slate-200 rounded-lg shadow-sm mb-6 p-4">
                    <h3 class="text-sm font-semibold text-slate-800 mb-2">Insurer cascade trace (labeled portions)</h3>
                    <p class="text-xs text-slate-600 mb-3">Each letter matches the order amounts were submitted to primary (A), secondary (B), tertiary (C), and so on. These invoices are for follow-up only; the primary invoice above is unchanged.</p>
                    @php $traceSum = $invoice->vendorPortionInvoices->sum('total_amount'); @endphp
                    <ul class="space-y-2 text-sm">
                        @foreach($invoice->vendorPortionInvoices as $portion)
                            <li class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-mono text-slate-900">
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-200 text-xs font-bold text-slate-700 mr-2">{{ $portion->vendor_portion_label }}</span>
                                    {{ $portion->invoice_number }}
                                </span>
                                <span class="text-slate-700">UGX {{ number_format($portion->total_amount, 2) }}</span>
                                <a href="{{ route('invoices.show', $portion) }}" class="text-blue-600 hover:text-blue-800 font-medium">View</a>
                            </li>
                        @endforeach
                    </ul>
                    <p class="flex justify-between gap-2 mt-2 pt-2 border-t border-slate-200 text-sm font-medium text-slate-800">
                        <span>Trace total</span>
                        <span class="font-mono">UGX {{ number_format($traceSum, 2) }}</span>
                    </p>
                </div>
            @endif
            <!-- Invoice Header -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Invoice Information -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Invoice Information</h3>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Invoice Number</dt>
                                    <dd class="text-lg font-semibold text-gray-900">{{ $invoice->invoice_number }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                                    <dd>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $invoice->status_badge }}">
                                            {{ ucfirst($invoice->status) }}
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Payment Status</dt>
                                    <dd>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $invoice->payment_status_badge }}">
                                            {{ $invoice->payment_status === 'pending_payment' ? 'Pending Payment' : ucfirst($invoice->payment_status) }}
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Date Created</dt>
                                    <dd class="text-sm text-gray-900">{{ $invoice->created_at->format('M d, Y H:i') }}</dd>
                                </div>
                                @if($invoice->confirmed_at)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Date Confirmed</dt>
                                    <dd class="text-sm text-gray-900">{{ $invoice->confirmed_at->format('M d, Y H:i') }}</dd>
                                </div>
                                @endif
                            </dl>
                        </div>

                        <!-- Client Information -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Client Information</h3>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Client Name</dt>
                                    <dd class="text-sm text-gray-900">{{ $invoice->client_name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Contact Phone</dt>
                                    <dd class="text-sm text-gray-900">{{ $invoice->client_phone }}</dd>
                                </div>
                                @if($invoice->payment_phone)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Payment Phone</dt>
                                    <dd class="text-sm text-gray-900">{{ $invoice->payment_phone }}</dd>
                                </div>
                                @endif
                                @if($invoice->visit_id)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Visit ID</dt>
                                    <dd class="text-sm text-gray-900">{{ $invoice->visit_id }}</dd>
                                </div>
                                @endif
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Section -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Items</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @if($invoice->items && is_array($invoice->items))
                                    @foreach($invoice->items as $item)
                                    @php
                                        // Get the actual Item model to use display_name attribute
                                        $itemModel = \App\Models\Item::find($item['id'] ?? $item['item_id'] ?? null);
                                        $displayName = $itemModel ? $itemModel->name : ($item['name'] ?? 'N/A');
                                    @endphp
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $displayName }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            UGX {{ number_format($item['price'] ?? 0, 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $item['quantity'] ?? 0 }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            UGX {{ number_format(($item['price'] ?? 0) * ($item['quantity'] ?? 0), 2) }}
                                        </td>
                                    </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                            No items found
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Payment Summary -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Payment Summary</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Financial Summary -->
                        <div>
                            <dl class="space-y-3">
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">Subtotal 1</dt>
                                    <dd class="text-sm text-gray-900">UGX {{ number_format($invoice->subtotal, 2) }}</dd>
                                </div>
                                @if($invoice->package_adjustment != 0)
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">Package Adjustment</dt>
                                    <dd class="text-sm text-gray-900">UGX {{ number_format($invoice->package_adjustment, 2) }}</dd>
                                </div>
                                @endif
                                @if($invoice->account_balance_adjustment != 0)
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">Account Balance(A/c) Adjustment</dt>
                                    <dd class="text-sm text-gray-900">UGX {{ number_format($invoice->account_balance_adjustment, 2) }}</dd>
                                </div>
                                @endif
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">Subtotal 2</dt>
                                    <dd class="text-sm text-gray-900">UGX {{ number_format(max(0, $invoice->subtotal - ($invoice->package_adjustment ?? 0) - ($invoice->account_balance_adjustment ?? 0)), 2) }}</dd>
                                </div>
                                @if($invoice->service_charge > 0)
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">Service Charge</dt>
                                    <dd class="text-sm text-gray-900">UGX {{ number_format($invoice->service_charge, 2) }}</dd>
                                </div>
                                @endif
                                <div class="flex justify-between border-t pt-3">
                                    <dt class="text-lg font-bold text-gray-900">Total</dt>
                                    <dd class="text-lg font-bold text-gray-900">UGX {{ number_format($invoice->total_amount, 2) }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">Amount Paid</dt>
                                    <dd class="text-sm text-gray-900">UGX {{ number_format($invoice->amount_paid, 2) }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">Balance Due</dt>
                                    <dd class="text-sm font-semibold {{ $invoice->balance_due > 0 ? 'text-red-600' : 'text-green-600' }}">
                                        UGX {{ number_format($invoice->balance_due, 2) }}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Payment Methods -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Payment Methods</h4>
                            @if($invoice->payment_methods && is_array($invoice->payment_methods))
                                @php
                                    // Check if payment_methods has amounts
                                    $hasAmounts = false;
                                    foreach($invoice->payment_methods as $method) {
                                        if (is_array($method) && isset($method['method']) && isset($method['amount'])) {
                                            $hasAmounts = true;
                                            break;
                                        }
                                    }
                                @endphp
                                
                                @if($hasAmounts)
                                    <div class="space-y-2">
                                        @foreach($invoice->payment_methods as $methodData)
                                            @if(is_array($methodData) && isset($methodData['method']) && isset($methodData['amount']))
                                                <div class="flex items-center justify-between">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        {{ ucwords(str_replace('_', ' ', $methodData['method'])) }}
                                                    </span>
                                                    <span class="text-sm text-gray-600">
                                                        UGX {{ number_format($methodData['amount'], 2) }}
                                                    </span>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @else
                                    {{-- Fallback for old format --}}
                                    <div class="space-y-2">
                                        @foreach($invoice->payment_methods as $method)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ ucwords(str_replace('_', ' ', $method)) }}
                                        </span>
                                        @endforeach
                                    </div>
                                    @if($invoice->amount_paid > 0)
                                        <div class="text-sm text-gray-600 mt-2">
                                            Total Paid: UGX {{ number_format($invoice->amount_paid, 2) }}
                                        </div>
                                    @endif
                                @endif
                            @else
                                <span class="text-sm text-gray-500">No payment methods specified</span>
                            @endif

                            @if($invoice->notes)
                            <div class="mt-4">
                                <h4 class="text-sm font-medium text-gray-500 mb-2">Notes</h4>
                                <p class="text-sm text-gray-900">{{ $invoice->notes }}</p>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            @php
                $clientModel = $invoice->client;
            @endphp

            {{-- Insurance Authorization Status --}}
            @if($invoice->insurance_authorization_snapshot)
                @php
                    $authSnap = $invoice->insurance_authorization_snapshot;
                    $authStatus = $authSnap['authorization_status'] ?? 'unknown';
                    $isApproved = in_array($authStatus, ['auto_approved', 'approved']);
                    $isRejected = in_array($authStatus, ['auto_rejected', 'rejected']);
                    $isPending = $authStatus === 'pending_review';
                    $fmtNum = fn($v) => number_format((float) ($v ?? 0), 0);
                    $fmtDecimal = fn($v) => number_format((float) ($v ?? 0), 2);
                    $insurancePortionAmt = (float) ($authSnap['insurance_total'] ?? $invoice->insurance_insurance_total ?? 0);
                    $clientPortionDisplay = round(max(0, (float) $invoice->total_amount - $insurancePortionAmt), 2);
                    $isMultiVendor = $authSnap['multi_vendor'] ?? false;
                @endphp
                <div class="mb-6 rounded-lg border {{ $isApproved ? 'bg-green-50 border-green-200' : ($isRejected ? 'bg-red-50 border-red-200' : 'bg-orange-50 border-orange-200') }} p-5">
                    <div class="flex items-center gap-2 mb-3">
                        @if($isApproved)
                            <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <h3 class="text-lg font-semibold text-green-800">Insurance Authorization — Approved</h3>
                        @elseif($isRejected)
                            <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <h3 class="text-lg font-semibold text-red-800">Insurance Authorization — Rejected</h3>
                        @else
                            <svg class="h-6 w-6 text-orange-600 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <h3 class="text-lg font-semibold text-orange-800">Insurance Authorization — Pending Review</h3>
                        @endif
                    </div>

                    @if($isMultiVendor && !empty($authSnap['vendors']))
                        @if(!empty($authSnap['cascade_line_items']) && is_array($authSnap['cascade_line_items']))
                            <div class="mb-3 rounded border border-gray-200 bg-white overflow-hidden">
                                <div class="overflow-x-auto max-h-52 overflow-y-auto">
                                    <table class="min-w-full text-xs">
                                        <thead>
                                            <tr class="bg-gray-50 text-left text-gray-600">
                                                <th class="px-2 py-1 font-medium">Item</th>
                                                <th class="px-2 py-1 text-right font-medium">Qty</th>
                                                <th class="px-2 py-1 text-right font-medium">Amount</th>
                                                <th class="px-2 py-1 font-medium">Paid by</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($authSnap['cascade_line_items'] as $lineRow)
                                                <tr class="border-t border-gray-100">
                                                    <td class="px-2 py-1 text-gray-800">
                                                        {{ $lineRow['name'] ?? 'Line item' }}
                                                        @if(!empty($lineRow['code']))
                                                            <span class="text-gray-400">({{ $lineRow['code'] }})</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-2 py-1 text-right text-gray-600">{{ $lineRow['quantity'] ?? '' }}</td>
                                                    <td class="px-2 py-1 text-right text-gray-900">
                                                        UGX {{ $fmtDecimal($lineRow['line_total'] ?? 0) }}
                                                    </td>
                                                    <td class="px-2 py-1 text-gray-800">
                                                        @if(!empty($lineRow['secondary_insurer']) && (float) ($lineRow['secondary_covered_amount'] ?? 0) > 0.001)
                                                            @php
                                                                $primaryPay = (float) ($lineRow['covered_amount'] ?? 0);
                                                                if ($primaryPay <= 0.001) {
                                                                    $primaryPay = max(0, (float) ($lineRow['line_total'] ?? 0) - (float) $lineRow['secondary_covered_amount']);
                                                                }
                                                                $pct = isset($lineRow['coverage_percent']) ? (float) $lineRow['coverage_percent'] : null;
                                                            @endphp
                                                            <div class="space-y-0.5 leading-snug">
                                                                <div>
                                                                    {{ $lineRow['primary_insurer'] }}
                                                                    @if($pct !== null && $pct > 0 && $pct < 100)
                                                                        <span class="text-gray-400">({{ rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.') }}%)</span>
                                                                    @endif
                                                                    : <span class="font-mono">UGX {{ $fmtDecimal($primaryPay) }}</span>
                                                                </div>
                                                                <div>{{ $lineRow['secondary_insurer'] }}: <span class="font-mono">UGX {{ $fmtDecimal($lineRow['secondary_covered_amount']) }}</span></div>
                                                            </div>
                                                        @elseif(!empty($lineRow['primary_insurer']) && (float) ($lineRow['covered_amount'] ?? 0) > 0.001 && (float) ($lineRow['client_portion'] ?? 0) > 0.001)
                                                            <div class="space-y-0.5 leading-snug">
                                                                <div>{{ $lineRow['primary_insurer'] }}: <span class="font-mono">UGX {{ $fmtDecimal($lineRow['covered_amount']) }}</span></div>
                                                                <div class="text-gray-600">Client: <span class="font-mono">UGX {{ $fmtDecimal($lineRow['client_portion']) }}</span></div>
                                                            </div>
                                                        @else
                                                            {{ $lineRow['attribution_label'] ?? ($lineRow['primary_insurer'] ?? 'Client') }}
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    @endif

                    @php
                        $snapSub = (float) ($authSnap['subtotal'] ?? $invoice->subtotal ?? 0);
                        $snapSc = (float) ($authSnap['service_charge'] ?? $invoice->service_charge ?? 0);
                        $snapTot = (float) ($authSnap['total_amount'] ?? $invoice->total_amount ?? 0);
                        $scVendorShares = [];
                        if ($snapSc > 0.001 && ! empty($authSnap['vendors']) && is_array($authSnap['vendors'])) {
                            $scHolder = null;
                            $candidates = [];
                            foreach ($authSnap['vendors'] as $v) {
                                if (! is_array($v)) {
                                    continue;
                                }
                                $st = strtolower((string) ($v['authorization_status'] ?? ''));
                                if (in_array($st, ['failed', 'skipped'], true)) {
                                    continue;
                                }
                                if ((float) ($v['insurance_total'] ?? 0) <= 0.001) {
                                    continue;
                                }
                                $candidates[] = $v;
                            }
                            usort($candidates, fn ($a, $b) => ((float) ($a['priority'] ?? 999)) <=> ((float) ($b['priority'] ?? 999)));
                            if ($candidates !== []) {
                                $scHolder = $candidates[0];
                            }
                            if ($scHolder) {
                                $scVendorShares[] = ['v' => $scHolder, 'amt' => round($snapSc, 2)];
                            }
                        }
                    @endphp
                    @if($snapSub > 0 || $snapSc > 0 || $snapTot > 0)
                        <div class="mb-3 rounded border border-gray-200 bg-gray-50 p-3 text-xs text-gray-800">
                            <p class="font-semibold text-gray-900 mb-2">Invoice summary</p>
                            @if($snapSub > 0)
                                <p class="flex justify-between gap-2"><span>Clinical subtotal</span><span class="font-mono">UGX {{ $fmtDecimal($snapSub) }}</span></p>
                            @endif
                            @if($snapSc > 0)
                                @if(count($scVendorShares))
                                    @php $scRow = $scVendorShares[0]; @endphp
                                    <p class="flex justify-between gap-2 mt-1">
                                        <span>Service charge — {{ $scRow['v']['vendor_name'] ?? 'Insurer' }}@if(! empty($scRow['v']['portion_label'])) [{{ $scRow['v']['portion_label'] }}]@endif</span>
                                        <span class="font-mono">UGX {{ $fmtDecimal($scRow['amt']) }}</span>
                                    </p>
                                @else
                                    <p class="flex justify-between gap-2 mt-1"><span>Service charge</span><span class="font-mono">UGX {{ $fmtDecimal($snapSc) }}</span></p>
                                @endif
                            @endif
                            @if($snapTot > 0)
                                <p class="flex justify-between gap-2 font-semibold border-t border-gray-200 mt-2 pt-2"><span>Invoice total</span><span class="font-mono">UGX {{ $fmtDecimal($snapTot) }}</span></p>
                            @endif
                        </div>
                    @endif

                    @if($isMultiVendor && !empty($authSnap['vendors']))
                        <p class="mb-4 text-xs text-gray-700 leading-snug">
                            @foreach($authSnap['vendors'] as $vendorIdx => $vendor)
                                @php $vendorInsuranceAmount = (float) ($vendor['insurance_total'] ?? 0); @endphp
                                <span class="font-medium text-gray-900">{{ $vendor['vendor_name'] ?? 'Vendor '.($vendorIdx + 1) }}</span>@if(!empty($vendor['portion_label'])) [{{ $vendor['portion_label'] }}]@endif: UGX {{ $fmtDecimal($vendorInsuranceAmount) }}@if(!$loop->last) · @endif
                            @endforeach
                        </p>
                    @endif

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm border-t pt-4">
                        <div>
                            <p class="text-gray-500">Total Insurance Coverage</p>
                            <p class="font-bold {{ $isApproved ? 'text-green-800' : ($isPending ? 'text-orange-800' : 'text-red-800') }}">UGX {{ $fmtDecimal($insurancePortionAmt) }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Final Client Portion</p>
                            <p class="font-bold text-gray-900">UGX {{ $fmtDecimal($clientPortionDisplay) }}</p>
                        </div>
                        @if($invoice->insurance_authorization_reference)
                            <div>
                                <p class="text-gray-500">Authorization reference</p>
                                <p class="font-medium text-gray-900">{{ $invoice->insurance_authorization_reference }}</p>
                            </div>
                        @endif
                    </div>

                    @if($isRejected && !empty($authSnap['rejection_reason']))
                        <p class="mt-3 text-sm text-red-700"><strong>Reason:</strong> {{ $authSnap['rejection_reason'] }}</p>
                    @endif

                    @if($isPending)
                        <p class="mt-3 text-xs text-orange-700">The insurer is reviewing this authorization. You can collect the client portion in the meantime. This page will show updated status once the insurer decides.</p>
                    @endif
                </div>
            @endif

            @if($clientModel && $clientModel->insurance_company_id && ($clientModel->has_deductible || $clientModel->copay_amount || $clientModel->coinsurance_percentage))
            <!-- Payment Responsibility (Insurance) -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6" x-data="{ expanded: true }">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Payment Responsibility</h3>
                            <p class="text-xs text-yellow-600 mt-1">
                                <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                Payment responsibility for this invoice based on the client's insurance plan.
                            </p>
                        </div>
                        <button @click="expanded = !expanded" class="text-gray-500 hover:text-gray-700 transition-colors">
                            <svg x-show="expanded" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                            </svg>
                            <svg x-show="!expanded" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                    </div>

                    <div x-show="expanded" x-transition>
                        <div class="space-y-4">
                            @if($clientModel->has_deductible && $clientModel->deductible_amount)
                            <div id="deductible-card-container">
                                <a href="{{ route('payment-responsibility.pay', ['client' => $clientModel->id, 'type' => 'deductible']) }}" 
                                   id="deductible-pay-link"
                                   class="block bg-yellow-50 border-2 border-yellow-200 rounded-lg p-4 hover:border-yellow-400 hover:shadow-md transition-all cursor-pointer">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-2 mb-2">
                                                <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                                </svg>
                                                <h4 class="text-sm font-semibold text-yellow-800">Deductible</h4>
                                                <span id="deductible-action-text" class="text-xs text-yellow-600 ml-2">Click to pay →</span>
                                            </div>
                                            <p class="text-lg font-bold text-yellow-900 mb-1">UGX {{ number_format($clientModel->deductible_amount, 2) }}</p>
                                            <p class="text-xs text-yellow-700">Amount client must pay before insurance coverage begins</p>
                                            <div id="deductible-status" class="mt-2">
                                                <p class="text-xs text-yellow-600">
                                                    <span id="deductible-used">UGX 0.00</span> used, 
                                                    <span id="deductible-remaining">UGX {{ number_format($clientModel->deductible_amount, 2) }}</span> remaining
                                                </p>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <span id="deductible-status-badge" class="px-3 py-1 text-xs font-medium rounded-full bg-yellow-200 text-yellow-800">
                                                Not Met
                                            </span>
                                        </div>
                                    </div>
                                </a>

                                <!-- Non-clickable display when met -->
                                <div id="deductible-details-display" 
                                     class="hidden bg-green-50 border-2 border-green-200 rounded-lg p-4 cursor-default">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-2 mb-2">
                                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                <h4 class="text-sm font-semibold text-green-800">Deductible</h4>
                                                <span class="text-xs text-green-600 ml-2">✓ Fully Paid</span>
                                            </div>
                                            <p class="text-lg font-bold text-green-900 mb-1">UGX {{ number_format($clientModel->deductible_amount, 2) }}</p>
                                            <p class="text-xs text-green-700">Amount client must pay before insurance coverage begins</p>
                                            <div id="deductible-status-met" class="mt-2">
                                                <p class="text-xs text-green-600">
                                                    <span id="deductible-used-met">UGX 0.00</span> used, 
                                                    <span id="deductible-remaining-met">UGX 0.00</span> remaining
                                                </p>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <span id="deductible-status-badge-met" class="px-3 py-1 text-xs font-medium rounded-full bg-green-200 text-green-800">
                                                Met
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif

                            @if($clientModel->copay_amount)
                            <div id="copay-card-container">
                                <a href="{{ route('payment-responsibility.pay', ['client' => $clientModel->id, 'type' => 'copay']) }}" 
                                   id="copay-pay-link"
                                   class="block bg-blue-50 border-2 border-blue-200 rounded-lg p-4 hover:border-blue-400 hover:shadow-md transition-all cursor-pointer">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-2 mb-2">
                                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                                </svg>
                                                <h4 class="text-sm font-semibold text-blue-800">Co-pay</h4>
                                                <span id="copay-action-text" class="text-xs text-blue-600 ml-2">Click to pay →</span>
                                            </div>
                                            <p class="text-lg font-bold text-blue-900 mb-1">UGX {{ number_format($clientModel->copay_amount, 2) }} per visit</p>
                                            <p class="text-xs text-blue-700">
                                                Fixed amount payable at each visit
                                                @if($clientModel->copay_max_limit)
                                                    <br>Maximum: UGX {{ number_format($clientModel->copay_max_limit, 2) }}
                                                @endif
                                            </p>
                                        </div>
                                        <div class="ml-4">
                                            <span id="copay-status-badge" class="px-3 py-1 text-xs font-medium rounded-full bg-blue-200 text-blue-800">
                                                Required
                                            </span>
                                        </div>
                                    </div>
                                </a>

                                <!-- Non-clickable display when paid -->
                                <div id="copay-details-display" 
                                     class="hidden bg-green-50 border-2 border-green-200 rounded-lg p-4 cursor-default mt-2">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-2 mb-2">
                                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                <h4 class="text-sm font-semibold text-green-800">Co-pay</h4>
                                                <span class="text-xs text-green-600 ml-2">✓ Paid</span>
                                            </div>
                                            <p class="text-lg font-bold text-green-900 mb-1">UGX {{ number_format($clientModel->copay_amount, 2) }} per visit</p>
                                            <p class="text-xs text-green-700">
                                                Fixed amount payable at each visit
                                                @if($clientModel->copay_max_limit)
                                                    <br>Maximum: UGX {{ number_format($clientModel->copay_max_limit, 2) }}
                                                @endif
                                            </p>
                                            <p class="text-xs text-green-600 mt-2">
                                                <span id="copay-paid-amount">UGX 0.00</span> paid for this visit
                                            </p>
                                        </div>
                                        <div class="ml-4">
                                            <span id="copay-status-badge-paid" class="px-3 py-1 text-xs font-medium rounded-full bg-green-200 text-green-800">
                                                Paid
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif

                            @if($clientModel->coinsurance_percentage)
                            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-2 mb-2">
                                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                            </svg>
                                            <h4 class="text-sm font-semibold text-purple-800">Co-insurance</h4>
                                        </div>
                                        <p class="text-lg font-bold text-purple-900 mb-1">{{ number_format($clientModel->coinsurance_percentage, 2) }}%</p>
                                        <p class="text-xs text-purple-700">Percentage of invoice amount paid by client</p>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Business & Branch Information -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Business Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Business</dt>
                            <dd class="text-sm text-gray-900">{{ $invoice->business->name ?? 'N/A' }}</dd>
                        </div>
                        @if($invoice->branch)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Branch</dt>
                            <dd class="text-sm text-gray-900">{{ $invoice->branch->name ?? 'N/A' }}</dd>
                        </div>
                        @endif
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created By</dt>
                            <dd class="text-sm text-gray-900">{{ $invoice->createdBy->name ?? 'N/A' }}</dd>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Failed Transactions & Reinitiation Section -->
            @php
                $failedTransactions = \App\Models\Transaction::where('invoice_id', $invoice->id)
                    ->where('status', 'failed')
                    ->where('method', 'mobile_money')
                    ->where('provider', 'yo')
                    ->get();
            @endphp
            
            @if($failedTransactions->count() > 0)
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Failed Transactions</h3>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                        <div class="flex items-center">
                            <svg class="h-5 w-5 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                            <p class="text-sm text-red-800">
                                This invoice has {{ $failedTransactions->count() }} failed mobile money transaction(s). 
                                You can reinitiate payment for these transactions.
                            </p>
                        </div>
                    </div>
                    
                    @foreach($failedTransactions as $transaction)
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-3">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    Transaction #{{ $transaction->id }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    Amount: UGX {{ number_format($transaction->amount, 2) }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    Reference: {{ $transaction->external_reference ?? $transaction->reference ?? 'N/A' }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    Failed: {{ $transaction->updated_at->format('M d, Y H:i:s') }}
                                </p>
                            </div>
                            <div class="flex space-x-2">
                                <button onclick="reinitiateTransaction({{ $transaction->id }})" 
                                        class="px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition-colors">
                                    Reinitiate Payment
                                </button>
                            </div>
                        </div>
                    </div>
                    @endforeach
                    
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <button onclick="reinitiateAllFailedTransactions({{ $invoice->id }})" 
                                class="px-4 py-2 bg-orange-600 text-white rounded hover:bg-orange-700 transition-colors">
                            Reinitiate All Failed Transactions
                        </button>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    <script>
        // Reinitiate a single failed transaction
        async function reinitiateTransaction(transactionId) {
            try {
                const response = await fetch('/invoices/reinitiate-failed-transaction', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        transaction_id: transactionId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Payment Reinitiated!',
                        text: data.message || 'Payment has been reinitiated successfully.'
                    }).then(() => {
                        // Reload the page to show updated status
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message || 'Failed to reinitiate payment.'
                    });
                }
            } catch (error) {
                console.error('Error reinitiating transaction:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred while reinitiating the payment.'
                });
            }
        }

        // Reinitiate all failed transactions for an invoice
        async function reinitiateAllFailedTransactions(invoiceId) {
            try {
                const response = await fetch('/invoices/reinitiate-failed-invoice', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        invoice_id: invoiceId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'All Payments Reinitiated!',
                        text: data.message || 'All failed payments have been reinitiated successfully.'
                    }).then(() => {
                        // Reload the page to show updated status
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message || 'Failed to reinitiate payments.'
                    });
                }
            } catch (error) {
                console.error('Error reinitiating all transactions:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred while reinitiating the payments.'
                });
            }
        }

        async function generateQuotation(invoiceId) {
            try {
                console.log('Generating quotation for invoice:', invoiceId);
                
                // Check if CSRF token exists
                const csrfToken = document.querySelector('meta[name="csrf-token"]');
                if (!csrfToken) {
                    console.error('CSRF token not found');
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'CSRF token not found. Please refresh the page and try again.'
                    });
                    return;
                }
                
                // Show loading state
                Swal.fire({
                    title: 'Generating Quotation...',
                    html: `
                        <div class="text-center">
                            <div class="mb-4">
                                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-600 mx-auto"></div>
                            </div>
                            <p class="text-sm text-gray-600">Please wait while we generate your quotation...</p>
                        </div>
                    `,
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    allowEscapeKey: false
                });

                console.log('Making API call to:', `/invoices/${invoiceId}/generate-quotation`);
                
                // Make API call to generate quotation
                const response = await fetch(`/invoices/${invoiceId}/generate-quotation`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken.getAttribute('content'),
                        'Accept': 'application/json',
                    }
                });

                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                console.log('Response data:', data);

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Quotation Generated!',
                        html: `
                            <div class="text-center">
                                <p class="mb-2">Quotation has been generated successfully.</p>
                                <p class="text-sm text-gray-600">Quotation Number: <strong>${data.quotation_number}</strong></p>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'View Quotation',
                        cancelButtonText: 'Print Quotation',
                        showDenyButton: true,
                        denyButtonText: 'Stay Here'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // View quotation
                            window.open(`/quotations/${data.quotation_id}`, '_blank');
                        } else if (result.dismiss === Swal.DismissReason.cancel) {
                            // Print quotation
                            window.open(`/quotations/${data.quotation_id}/print`, '_blank');
                        }
                        // If "Stay Here" is clicked, do nothing
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message || 'Failed to generate quotation'
                    });
                }
            } catch (error) {
                console.error('Error generating quotation:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: `An error occurred while generating the quotation: ${error.message}`
                });
            }
        }
        
        @if($invoice->client)
        document.addEventListener('DOMContentLoaded', function () {
            const paymentResponsibility = {
                hasDeductible: {{ $invoice->client->has_deductible ? 'true' : 'false' }},
                deductibleAmount: {{ $invoice->client->deductible_amount ?? 0 }},
                deductibleUsed: 0,
                deductibleRemaining: {{ $invoice->client->deductible_amount ?? 0 }},
                copayAmount: {{ $invoice->client->copay_amount ?? 0 }},
                copayMaxLimit: {{ $invoice->client->copay_max_limit ?? 0 }},
                coinsurancePercentage: {{ $invoice->client->coinsurance_percentage ?? 0 }},
            };

            async function calculateDeductibleUsed() {
                try {
                    const response = await fetch(`/api/v1/clients/{{ $invoice->client->id }}/deductible-used`, {
                        method: 'GET',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                        }
                    });

                    if (response.ok) {
                        const data = await response.json();
                        if (data.success && data.deductible_used !== undefined) {
                            paymentResponsibility.deductibleUsed = parseFloat(data.deductible_used) || 0;
                            paymentResponsibility.deductibleRemaining = Math.max(0, paymentResponsibility.deductibleAmount - paymentResponsibility.deductibleUsed);
                            updateDeductibleDisplay();
                        }
                    } else {
                        paymentResponsibility.deductibleRemaining = paymentResponsibility.deductibleAmount;
                        paymentResponsibility.deductibleUsed = 0;
                    }
                } catch (error) {
                    paymentResponsibility.deductibleRemaining = paymentResponsibility.deductibleAmount;
                    paymentResponsibility.deductibleUsed = 0;
                }
            }

            function updateDeductibleDisplay() {
                const deductibleUsedEl = document.getElementById('deductible-used');
                const deductibleRemainingEl = document.getElementById('deductible-remaining');
                const deductibleStatusBadge = document.getElementById('deductible-status-badge');
                const deductibleActionText = document.getElementById('deductible-action-text');
                const deductiblePayLink = document.getElementById('deductible-pay-link');
                const deductibleDetailsDisplay = document.getElementById('deductible-details-display');
                const deductibleUsedMetEl = document.getElementById('deductible-used-met');
                const deductibleRemainingMetEl = document.getElementById('deductible-remaining-met');

                if (!deductibleUsedEl || !deductibleRemainingEl || !deductibleStatusBadge) {
                    return;
                }

                deductibleUsedEl.textContent = `UGX ${paymentResponsibility.deductibleUsed.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                deductibleRemainingEl.textContent = `UGX ${paymentResponsibility.deductibleRemaining.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

                if (paymentResponsibility.deductibleRemaining <= 0) {
                    deductibleStatusBadge.textContent = 'Met';
                    deductibleStatusBadge.className = 'px-3 py-1 text-xs font-medium rounded-full bg-green-200 text-green-800';

                    if (deductibleUsedMetEl) {
                        deductibleUsedMetEl.textContent = `UGX ${paymentResponsibility.deductibleUsed.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                    }
                    if (deductibleRemainingMetEl) {
                        deductibleRemainingMetEl.textContent = `UGX ${paymentResponsibility.deductibleRemaining.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                    }

                    if (deductiblePayLink) {
                        deductiblePayLink.classList.add('hidden');
                    }
                    if (deductibleDetailsDisplay) {
                        deductibleDetailsDisplay.classList.remove('hidden');
                    }
                    if (deductibleActionText) {
                        deductibleActionText.textContent = '✓ Fully Paid';
                        deductibleActionText.classList.remove('text-yellow-600');
                        deductibleActionText.classList.add('text-green-600');
                    }
                } else {
                    deductibleStatusBadge.textContent = 'Not Met';
                    deductibleStatusBadge.className = 'px-3 py-1 text-xs font-medium rounded-full bg-yellow-200 text-yellow-800';

                    if (deductiblePayLink) {
                        deductiblePayLink.classList.remove('hidden');
                    }
                    if (deductibleDetailsDisplay) {
                        deductibleDetailsDisplay.classList.add('hidden');
                    }
                    if (deductibleActionText) {
                        deductibleActionText.textContent = 'Click to pay →';
                        deductibleActionText.classList.remove('text-green-600');
                        deductibleActionText.classList.add('text-yellow-600');
                    }
                }
            }

            async function checkCopayStatus() {
                try {
                    const response = await fetch(`/api/v1/clients/{{ $invoice->client->id }}/copay-status`, {
                        method: 'GET',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                        }
                    });

                    if (response.ok) {
                        const data = await response.json();
                        if (data.success && data.copay_paid !== undefined) {
                            updateCopayDisplay(data.copay_paid, data.copay_paid_amount || 0);
                        }
                    }
                } catch (error) {
                    // ignore
                }
            }

            function updateCopayDisplay(isPaid, paidAmount) {
                const copayPayLink = document.getElementById('copay-pay-link');
                const copayDetailsDisplay = document.getElementById('copay-details-display');
                const copayActionText = document.getElementById('copay-action-text');
                const copayStatusBadge = document.getElementById('copay-status-badge');
                const copayPaidAmountEl = document.getElementById('copay-paid-amount');
                const copayStatusBadgePaid = document.getElementById('copay-status-badge-paid');

                if (isPaid) {
                    if (copayPayLink) copayPayLink.classList.add('hidden');
                    if (copayDetailsDisplay) copayDetailsDisplay.classList.remove('hidden');
                    if (copayActionText) copayActionText.textContent = '✓ Paid';
                    if (copayStatusBadge) {
                        copayStatusBadge.textContent = 'Paid';
                        copayStatusBadge.className = 'px-3 py-1 text-xs font-medium rounded-full bg-green-200 text-green-800';
                    }
                    if (copayStatusBadgePaid) {
                        copayStatusBadgePaid.className = 'px-3 py-1 text-xs font-medium rounded-full bg-green-200 text-green-800';
                    }
                    if (copayPaidAmountEl) {
                        copayPaidAmountEl.textContent = `UGX ${paidAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                    }
                } else {
                    if (copayPayLink) copayPayLink.classList.remove('hidden');
                    if (copayDetailsDisplay) copayDetailsDisplay.classList.add('hidden');
                    if (copayActionText) copayActionText.textContent = 'Click to pay →';
                    if (copayStatusBadge) {
                        copayStatusBadge.textContent = 'Required';
                        copayStatusBadge.className = 'px-3 py-1 text-xs font-medium rounded-full bg-blue-200 text-blue-800';
                    }
                }
            }

            if (paymentResponsibility.hasDeductible && paymentResponsibility.deductibleAmount > 0) {
                calculateDeductibleUsed();
            }
            if (paymentResponsibility.copayAmount > 0) {
                checkCopayStatus();
            }
        });
        @endif
    </script>
</x-app-layout>
