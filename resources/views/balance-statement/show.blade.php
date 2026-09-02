<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Client Account Statement') }} - {{ $client->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Client Information Card -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">{{ $client->name }}</h3>
                            <p class="text-sm text-gray-500">Client ID: {{ $client->client_id }}</p>
                            <p class="text-sm text-gray-500">Phone: {{ $client->phone_number }}</p>
                        </div>
                        <div class="text-right">
                            <div class="space-y-2">
                                @php
                                    $statementBalances = $statementBalances ?? \App\Services\ClientBalanceStatementPresenter::summaryBalances($client);
                                    $headerAvailableBalance = (float) ($statementBalances['available_balance'] ?? 0);
                                    $headerSuspenseBalance = (float) ($statementBalances['suspense_balance'] ?? 0);
                                    $headerTotalBalance = (float) ($statementBalances['total_balance'] ?? 0);

                                    // Check if client is paying via insurance
                                    $isInsuranceClient = false;
                                    $hasInsuranceInvoices = $client->balanceHistories()
                                        ->where('payment_method', 'insurance')
                                        ->exists();
                                    
                                    if ($hasInsuranceInvoices) {
                                        // If client has insurance invoices, check if all recent invoices are insurance
                                        $recentInvoices = \App\Models\Invoice::where('client_id', $client->id)
                                            ->whereNull('parent_invoice_id')
                                            ->where('status', 'confirmed')
                                            ->orderBy('created_at', 'desc')
                                            ->limit(10)
                                            ->get();
                                        
                                        if ($recentInvoices->count() > 0) {
                                            $insuranceCount = $recentInvoices->filter(function($invoice) {
                                                $methods = is_array($invoice->payment_methods) ? $invoice->payment_methods : ($invoice->payment_methods ? json_decode($invoice->payment_methods, true) : []);
                                                return in_array('insurance', $methods ?? []);
                                            })->count();
                                            
                                            // If most recent invoices are insurance, treat as insurance client
                                            $isInsuranceClient = ($insuranceCount / $recentInvoices->count()) >= 0.5;
                                        }
                                    }
                                    
                                    $availableBalance = $headerAvailableBalance;
                                    $suspenseBalance = $headerSuspenseBalance;
                                    $totalBalance = $headerTotalBalance;

                                    if ($isInsuranceClient && ! $client->has_deductible && ! $client->copay_amount && ! $client->coinsurance_percentage) {
                                        $availableBalance = 0;
                                        $suspenseBalance = 0;
                                        $totalBalance = 0;
                                    }

                                    $creditLimit = $client->max_credit ?? 0;
                                    $amountOwed = $availableBalance < 0 ? abs($availableBalance) : 0;
                                    $creditRemaining = max(0, $creditLimit - $amountOwed);

                                    $availableBalanceColor = $availableBalance < 0 ? 'text-red-600' : ($availableBalance > 0 ? 'text-green-600' : 'text-gray-700');
                                    $totalBalanceColor = $totalBalance < 0 ? 'text-red-600' : ($totalBalance > 0 ? 'text-green-600' : 'text-gray-700');
                                    $creditRemainingColor = $creditRemaining > 0 ? 'text-green-600' : 'text-red-600';
                                @endphp
                                
                                @if($client->is_credit_eligible)
                                    {{-- Credit Client: Show Total Balance, Available Balance, and Credit Limit --}}
                                    <div>
                                        <p class="text-sm text-gray-500">Total Balance</p>
                                        <p class="text-lg font-semibold {{ $totalBalanceColor }}">
                                            UGX {{ number_format($totalBalance, 2) }}
                                        </p>
                                        @if($totalBalance < 0)
                                            <p class="text-xs text-red-500">(Amount Owed)</p>
                                        @endif
                                    </div>
                                    
                                    <div>
                                        <p class="text-sm text-gray-500">Available Balance</p>
                                        <p class="text-lg font-semibold {{ $availableBalanceColor }}">
                                            UGX {{ number_format($availableBalance, 2) }}
                                        </p>
                                        @if($availableBalance < 0)
                                            <p class="text-xs text-red-500">(Amount Owed)</p>
                                        @elseif($availableBalance > 0)
                                            <p class="text-xs text-green-500">(Credit Available)</p>
                                        @endif
                                    </div>
                                    
                                    <div>
                                        <p class="text-sm text-gray-500">Credit Limit</p>
                                        <p class="text-lg font-semibold text-gray-700">
                                            UGX {{ number_format($creditLimit, 2) }}
                                        </p>
                                    </div>
                                    
                                    <div>
                                        <p class="text-sm text-gray-500">Credit Remaining</p>
                                        <p class="text-lg font-semibold {{ $creditRemainingColor }}">
                                            UGX {{ number_format($creditRemaining, 2) }}
                                        </p>
                                        @if($creditRemaining <= 0)
                                            <p class="text-xs text-red-500">(Credit Limit Exceeded)</p>
                                        @elseif($amountOwed > 0)
                                            <p class="text-xs text-gray-500">({{ number_format($amountOwed, 2) }} used of {{ number_format($creditLimit, 2) }})</p>
                                        @else
                                            <p class="text-xs text-green-500">(No credit used)</p>
                                        @endif
                                    </div>
                                @else
                                    {{-- Non-Credit Client: Show Total Balance and Available Balance --}}
                                    <div>
                                        <p class="text-sm text-gray-500">Total Balance</p>
                                        <p class="text-lg font-semibold {{ $totalBalanceColor }}">
                                            UGX {{ number_format($totalBalance, 2) }}
                                        </p>
                                        @if($totalBalance < 0)
                                            <p class="text-xs text-red-500">(Amount Owed)</p>
                                        @endif
                                    </div>
                                    
                                    <div>
                                        <p class="text-sm text-gray-500">Available Balance</p>
                                        <p class="text-lg font-semibold {{ $availableBalanceColor }}">
                                            UGX {{ number_format($availableBalance, 2) }}
                                        </p>
                                        @if($availableBalance < 0)
                                            <p class="text-xs text-red-500">(Amount Owed)</p>
                                        @elseif($availableBalance > 0)
                                            <p class="text-xs text-green-500">(Credit Available)</p>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Balance Statement Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Client Account Statement</h3>
                        <div class="flex space-x-2">
                            @php
                                $credits = (float) ($statementBalances['total_credits'] ?? 0);
                                $debits = (float) ($statementBalances['total_debits'] ?? 0);
                                $calculatedBalance = $credits - $debits;
                                $isInitiator = \App\Models\CreditLimitApprovalApprover::where('business_id', auth()->user()->business_id)
                                    ->where('approver_id', auth()->user()->id)
                                    ->where('approval_level', 'initiator')
                                    ->exists();
                            @endphp
                            @if($client->is_credit_eligible && in_array('Manage Credit Limits', (array) (auth()->user()->permissions ?? [])) && $isInitiator)
                                <a href="{{ route('credit-limit-requests.create', ['entity_type' => 'client', 'entity_id' => $client->id]) }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors inline-block">
                                    Request Credit Limit Change
                                </a>
                            @endif
                            @php
                                // Check if there are any delivered services with outstanding amounts
                                // Only show button if money has moved from suspense to final accounts (services delivered)
                                $hasDeliveredServices = false;
                                if ($client->is_credit_eligible && $calculatedBalance < 0) {
                                    $invoicesWithDeliveredServices = \App\Models\BalanceHistory::where('client_id', $client->id)
                                        ->where('transaction_type', 'debit')
                                        ->affectingClientBalance()
                                        ->whereNotNull('invoice_id')
                                        ->where('change_amount', '!=', 0)
                                        ->with(['invoice'])
                                        ->get()
                                        ->filter(function ($entry) {
                                            if (!$entry->invoice || $entry->invoice->balance_due <= 0) {
                                                return false;
                                            }
                                            
                                            // Check if money has moved from suspense to final accounts (services delivered)
                                            return \App\Models\MoneyTransfer::where('invoice_id', $entry->invoice_id)
                                                ->where('transfer_type', 'suspense_to_final')
                                                ->where('money_moved_to_final_account', true)
                                                ->exists();
                                        });
                                    
                                    $hasDeliveredServices = $invoicesWithDeliveredServices->count() > 0;
                                }
                            @endphp
                            @if($client->is_credit_eligible && $calculatedBalance < 0 && $hasDeliveredServices && in_array('Process Pay Back', (array) (auth()->user()->permissions ?? [])))
                                <a href="{{ route('balance-statement.pay-back.show', $client->id) }}" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors inline-block">
                                    Pay Out Standing Amount
                                </a>
                            @endif
                        </div>
                    </div>

                    @if($balanceHistories->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Debit</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Credit</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Available balance</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total balance</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($balanceHistories as $history)
                                        @php
                                            $isInsuranceTracking = $history->payment_method === 'insurance';
                                            $insurancePayerLabel = $isInsuranceTracking ? $history->statementInsuranceBracketLabel() : null;
                                        @endphp
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $history->created_at->format('Y-m-d H:i:s') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @php
                                                    $ledgerTypeLabel = ucfirst($history->transaction_type);
                                                    $typeLabel = $isInsuranceTracking ? 'Insurance' : $ledgerTypeLabel;
                                                    $typeTooltip = $typeLabel;
                                                    if ($isInsuranceTracking) {
                                                        $typeTooltip = $insurancePayerLabel
                                                            ? 'Insurance · Ledger: '.$ledgerTypeLabel.' · Payer: '.$insurancePayerLabel
                                                            : 'Insurance · Ledger: '.$ledgerTypeLabel;
                                                    }
                                                @endphp
                                                {{-- Insurance rows show Type "Insurance"; ledger movement (e.g. Debit) in tooltip --}}
                                                <span title="{{ $typeTooltip }}" class="inline-flex max-w-[14rem] min-w-0 overflow-hidden truncate px-2 py-1 text-xs font-semibold rounded-full 
                                                    @if($isInsuranceTracking) bg-purple-100 text-purple-800 ring-1 ring-purple-200
                                                    @elseif($history->transaction_type === 'credit') bg-green-100 text-green-800
                                                    @elseif($history->transaction_type === 'debit') bg-red-100 text-red-800
                                                    @elseif($history->transaction_type === 'payment') bg-orange-100 text-orange-800
                                                    @elseif($history->transaction_type === 'package') bg-blue-100 text-blue-800
                                                    @else bg-yellow-100 text-yellow-800 @endif">
                                                    {{ $typeLabel }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-900">
                                                @php
                                                    $description = $history->description;
                                                    
                                                    // Simplify descriptions for statements
                                                    if (str_contains($description, 'Payment received via mobile_money')) {
                                                        $description = 'Mobile Money Payment';
                                                    } elseif (str_contains($description, 'Payment received for invoice')) {
                                                        $description = 'Invoice Payment';
                                                    } elseif (str_contains($description, 'Payment received via')) {
                                                        $description = str_replace('Payment received via ', '', $description);
                                                        $description = ucwords(str_replace('_', ' ', $description)) . ' Payment';
                                                    } elseif (str_contains($description, 'Payment for:')) {
                                                        // Extract item names from "Payment for: Item1, Item2, Item3"
                                                        $description = str_replace('Payment for: ', '', $description);
                                                        
                                                        // Remove quantities and types (e.g., "(x2) - good")
                                                        $description = preg_replace('/\s*\(x\d+\)\s*-\s*\w+/', '', $description);
                                                        
                                                        // Remove client and business info (e.g., "for Tonny Musis (ID: DEH2123C) at Demo Hospital")
                                                        $description = preg_replace('/\s+for\s+[^,]+(?:\([^)]+\))?(?:\s+at\s+[^-]+)?/', '', $description);
                                                        
                                                        // Remove invoice reference (e.g., "- Invoice: P2025090013")
                                                        $description = preg_replace('/\s*-\s*Invoice:\s*[A-Z0-9]+/', '', $description);
                                                        
                                                        // Remove "payment" word and change service charge to Service Fee
                                                        $description = preg_replace('/\bpayment\b/i', '', $description);
                                                        $description = preg_replace('/\bservice\s+charge\b/i', 'Service Fee', $description);
                                                        $description = preg_replace('/\breceived\s+via\b/i', 'via', $description);
                                                        $description = preg_replace('/\bcompleted\s*-\s*Item\s+purchased:\s*/i', '', $description);
                                                        
                                                        // Clean up any remaining extra spaces and commas
                                                        $description = preg_replace('/\s*,\s*$/', '', $description);
                                                        $description = preg_replace('/\s+/', ' ', trim($description));
                                                        
                                                        // If description is too long, truncate it
                                                        if (strlen($description) > 50) {
                                                            $description = substr($description, 0, 47) . '...';
                                                        }
                                                    } elseif (str_contains($description, 'Service Charge')) {
                                                        $description = 'Service Fee';
                                                    }

                                                    if ($isInsuranceTracking && $insurancePayerLabel) {
                                                        $description = str_replace('[Insurance]', '[' . $insurancePayerLabel . ']', $description);
                                                    }
                                                @endphp
                                                {{ $description }}
                                            </td>
                                            @php
                                                $ledgerDebit = 0.0;
                                                $ledgerCredit = 0.0;
                                                if (! $isInsuranceTracking) {
                                                    if ($history->transaction_type === 'credit' || ($history->change_amount ?? 0) > 0) {
                                                        $ledgerCredit = abs((float) ($history->change_amount ?? 0));
                                                    } elseif ($history->transaction_type === 'debit' || ($history->change_amount ?? 0) < 0) {
                                                        $ledgerDebit = abs((float) ($history->change_amount ?? 0));
                                                    }
                                                }
                                                $availableAfter = (float) ($history->available_balance_after ?? $history->new_balance ?? 0);
                                                $totalAfter = (float) ($history->total_balance_after ?? $availableAfter);
                                            @endphp
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-red-600">
                                                {{ $ledgerDebit > 0 ? 'UGX '.number_format($ledgerDebit, 2) : '—' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-green-600">
                                                {{ $ledgerCredit > 0 ? 'UGX '.number_format($ledgerCredit, 2) : '—' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900">
                                                UGX {{ number_format($availableAfter, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900">
                                                UGX {{ number_format($totalAfter, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $history->reference_number ?? '-' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if(($headerSuspenseBalance ?? 0) > 0)
                            <p class="mt-3 text-xs text-gray-500">
                                Total balance includes UGX {{ number_format($headerSuspenseBalance, 2) }} currently held in suspense (added to each row’s available balance).
                            </p>
                        @endif

                        <!-- Pagination -->
                        <div class="mt-4">
                            {{ $balanceHistories->links() }}
                        </div>
                    @else
                        <div class="text-center py-8">
                            <p class="text-gray-500">No balance statement found for this client.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>


</x-app-layout>

