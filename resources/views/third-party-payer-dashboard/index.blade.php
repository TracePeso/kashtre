<x-third-party-payer-layout>
    <div class="py-12 bg-gradient-to-b from-[#011478]/10 to-transparent">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Welcome Section -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-xl shadow-sm p-6 text-white mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        @php
                            $account = Auth::guard('third_party_payer')->user();
                        @endphp
                        <h2 class="text-2xl font-bold">Welcome, {{ $account->name ?? $account->username }}!</h2>
                        <p class="text-blue-100 mt-1">{{ $thirdPartyPayer->name }} Dashboard</p>
                    </div>
                    <div class="text-right">
                        <p class="text-blue-100 text-sm">Last Update</p>
                        <p class="text-white font-semibold">{{ now()->format('H:i:s') }}</p>
                    </div>
                </div>
            </div>

            <!-- Overview Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                
                <!-- Total Balance Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Balance</h3>
                        <span class="text-xs text-gray-400">Outstanding</span>
                    </div>
                    <div class="flex items-baseline">
                        <span class="text-2xl font-bold {{ $currentBalance < 0 ? 'text-red-600' : 'text-gray-900' }} dark:{{ $currentBalance < 0 ? 'text-red-400' : 'text-white' }}">
                            {{ number_format(abs($currentBalance), 2) }}
                        </span>
                        <span class="ml-2 text-sm text-gray-500">UGX</span>
                    </div>
                </div>

                <!-- Current Balance Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Current Balance</h3>
                        <span class="text-xs text-gray-400">Available</span>
                    </div>
                    <div class="flex items-baseline">
                        <span class="text-2xl font-bold text-green-600 dark:text-green-400">
                            {{ number_format(abs($currentBalance), 2) }}
                        </span>
                        <span class="ml-2 text-sm text-gray-500">UGX</span>
                    </div>
                </div>

                <!-- Total Outstanding Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Outstanding</h3>
                        <span class="text-xs text-gray-400">Accounts Receivable</span>
                    </div>
                    <div class="flex items-baseline">
                        <span class="text-2xl font-bold text-orange-600 dark:text-orange-400">
                            {{ number_format($totalOutstanding, 2) }}
                        </span>
                        <span class="ml-2 text-sm text-gray-500">UGX</span>
                    </div>
                </div>

                <!-- Total Due Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Due</h3>
                        <span class="text-xs text-gray-400">Invoices</span>
                    </div>
                    <div class="flex items-baseline">
                        <span class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                            {{ number_format($totalDue, 2) }}
                        </span>
                        <span class="ml-2 text-sm text-gray-500">UGX</span>
                    </div>
                </div>

            </div>

            <!-- Quick Actions -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
                <div class="flex flex-wrap gap-4">
                    <a href="{{ route('third-party-payer-dashboard.balance-statement') }}" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors flex items-center">
                        <i class="fas fa-history mr-2"></i>
                        View Balance Statement
                    </a>
                </div>
            </div>

            <!-- Credit Limit and Exclusions -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Credit Limit Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Credit Limit</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Maximum Credit Limit</label>
                            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">
                                {{ number_format($effectiveCreditLimit, 2) }} UGX
                            </p>
                            @php
                                $payerHasLimit = $thirdPartyPayer->credit_limit && $thirdPartyPayer->credit_limit > 0;
                                $usingBusinessDefault = !$payerHasLimit && ($business->max_third_party_credit_limit ?? 0) > 0;
                            @endphp
                            @if($usingBusinessDefault)
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Using business default limit
                            </p>
                            @elseif($payerHasLimit)
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Account-specific limit
                            </p>
                            @endif
                        </div>
                        @if($effectiveCreditLimit > 0)
                        <div>
                            <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Available Credit</label>
                            @php
                                // If current balance is negative, they owe money (used credit)
                                // If current balance is positive, they have a credit balance (no used credit)
                                $usedCredit = $currentBalance < 0 ? abs($currentBalance) : 0;
                                $availableCredit = max(0, $effectiveCreditLimit - $usedCredit);
                            @endphp
                            <p class="text-xl font-semibold {{ $availableCredit > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }} mt-1">
                                {{ number_format($availableCredit, 2) }} UGX
                            </p>
                            @if($usedCredit > 0)
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Used: {{ number_format($usedCredit, 2) }} UGX
                            </p>
                            @endif
                        </div>
                        @else
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">No credit limit set</p>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Exclusions Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Excluded Items</h3>
                    @if(count($effectiveExcludedItems) > 0)
                        <div class="space-y-2">
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                The following items are excluded from third-party payment for your account:
                                @php
                                    $hasThirdPartyExclusions = !empty($business->third_party_excluded_items) && is_array($business->third_party_excluded_items);
                                    $hasCreditExclusions = !empty($business->credit_excluded_items) && is_array($business->credit_excluded_items);
                                    $businessExcludedCount = $hasThirdPartyExclusions ? count(array_filter($business->third_party_excluded_items)) : ($hasCreditExclusions ? count(array_filter($business->credit_excluded_items)) : 0);
                                    $payerExcludedCount = is_array($thirdPartyPayer->excluded_items) ? count(array_filter($thirdPartyPayer->excluded_items)) : 0;
                                @endphp
                                @if($businessExcludedCount > 0 && $payerExcludedCount > 0)
                                    <span class="text-xs text-gray-500 dark:text-gray-500 block mt-1">
                                        (Includes business-level and account-specific exclusions)
                                    </span>
                                @elseif($businessExcludedCount > 0)
                                    @if($hasThirdPartyExclusions)
                                        <span class="text-xs text-gray-500 dark:text-gray-500 block mt-1">
                                            (Using business default third-party exclusions)
                                        </span>
                                    @elseif($hasCreditExclusions)
                                        <span class="text-xs text-gray-500 dark:text-gray-500 block mt-1">
                                            (Using business credit exclusions)
                                        </span>
                                    @endif
                                @elseif($payerExcludedCount > 0)
                                    <span class="text-xs text-gray-500 dark:text-gray-500 block mt-1">
                                        (Account-specific exclusions)
                                    </span>
                                @endif
                            </p>
                            <div class="max-h-64 overflow-y-auto">
                                <ul class="space-y-2">
                                    @php
                                        // Ensure effectiveExcludedItems are integers for comparison
                                        $excludedItemIds = array_map('intval', $effectiveExcludedItems);
                                    @endphp
                                    @foreach($items->whereIn('id', $excludedItemIds) as $item)
                                    <li class="flex items-center text-sm text-gray-700 dark:text-gray-300">
                                        <span class="mr-2">
                                            @if($item->type === 'service')
                                                <i class="fas fa-stethoscope text-blue-500"></i>
                                            @elseif($item->type === 'good')
                                                <i class="fas fa-box text-green-500"></i>
                                            @elseif($item->type === 'package')
                                                <i class="fas fa-cube text-purple-500"></i>
                                            @elseif($item->type === 'bulk')
                                                <i class="fas fa-layer-group text-orange-500"></i>
                                            @else
                                                <i class="fas fa-tag text-gray-500"></i>
                                            @endif
                                        </span>
                                        <span>{{ $item->name }}@if($item->code) ({{ $item->code }})@endif</span>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400">No items excluded</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">All items are available for third-party payment</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Transactions</h3>
                    <a href="{{ route('third-party-payer-dashboard.balance-statement') }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</a>
                </div>
                
                @if($recentTransactions->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Client</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Invoice</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Balance</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Payment Method</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($recentTransactions as $transaction)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $transaction->created_at->inOperationalTimezone()->format('M d, Y H:i') }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                    {{ $transaction->description }}
                                    @if($transaction->reference_number)
                                        <br><span class="text-xs text-gray-500 dark:text-gray-400">Ref: {{ $transaction->reference_number }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    @if($transaction->client)
                                        <span class="font-medium">{{ $transaction->client->name }}</span>
                                        @if($transaction->client->client_id)
                                            <br><span class="text-xs text-gray-500 dark:text-gray-400">ID: {{ $transaction->client->client_id }}</span>
                                        @endif
                                    @else
                                        <span class="text-gray-400">N/A</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    @if($transaction->invoice)
                                        <a href="{{ route('invoices.show', $transaction->invoice->id) }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-600">
                                            {{ $transaction->invoice->invoice_number ?? 'N/A' }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">N/A</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2 py-1 text-xs rounded-full {{ $transaction->transaction_type === 'credit' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                        {{ ucfirst($transaction->transaction_type) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium {{ $transaction->transaction_type === 'credit' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $transaction->transaction_type === 'credit' ? '+' : '-' }}{{ number_format(abs($transaction->change_amount), 2) }} UGX
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ number_format($transaction->new_balance, 2) }} UGX
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $transaction->payment_method ? ucwords(str_replace('_', ' ', $transaction->payment_method)) : 'N/A' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-8">
                    <p class="text-gray-500 dark:text-gray-400">No transactions found.</p>
                </div>
                @endif
            </div>

            <!-- Accounts Receivable Summary -->
            @if($accountsReceivable->count() > 0)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Outstanding Invoices</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Invoice #</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Client</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount Due</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount Paid</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Balance</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($accountsReceivable->take(10) as $ar)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $ar->invoice->invoice_number ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $ar->client->name ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ number_format($ar->amount_due, 2) }} UGX
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ number_format($ar->amount_paid, 2) }} UGX
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-orange-600">
                                    {{ number_format($ar->balance, 2) }} UGX
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2 py-1 text-xs rounded-full {{ $ar->status === 'paid' ? 'bg-green-100 text-green-800' : ($ar->status === 'overdue' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                        {{ ucfirst($ar->status) }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

        </div>
    </div>
</x-third-party-payer-layout>

