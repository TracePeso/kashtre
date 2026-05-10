<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Balance Statement') }} - {{ $vendor['name'] }}
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('third-party-vendors.show', $vendor['id']) }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    Back to Vendor Details
                </a>
                <a href="{{ route('third-party-vendors.index') }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    All Vendors
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Vendor Information Card -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">{{ $vendor['name'] }}</h3>
                            <p class="text-sm text-gray-500">Code: <code class="bg-gray-100 px-2 py-1 rounded">{{ $vendor['code'] }}</code></p>
                            @if($vendor['email'])
                            <p class="text-sm text-gray-500">Email: {{ $vendor['email'] }}</p>
                            @endif
                            @if($vendor['phone'])
                            <p class="text-sm text-gray-500">Phone: {{ $vendor['phone'] }}</p>
                            @endif
                            <p class="text-sm text-gray-500">
                                Status: 
                                @if($vendor['is_active'])
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Active
                                    </span>
                                @else
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        Inactive
                                    </span>
                                @endif
                            </p>
                        </div>
                        <div class="text-right">
                            <div class="space-y-2">
                                <div>
                                    <p class="text-sm text-gray-500">Credit Limit</p>
                                    <p class="text-lg font-semibold text-gray-700">
                                        UGX {{ number_format((($thirdPartyPayer->credit_limit ?? 0) > 0
                                            ? (float) $thirdPartyPayer->credit_limit
                                            : (float) ($business->max_third_party_credit_limit ?? 0)), 2) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-sm font-medium text-gray-500 mb-2">Total Credits</h3>
                        <p class="text-2xl font-bold text-green-600">UGX {{ number_format($totalCredits, 2) }}</p>
                        <p class="text-xs text-gray-500 mt-1">All credit transactions</p>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-sm font-medium text-gray-500 mb-2">Total Debits</h3>
                        <p class="text-2xl font-bold text-red-600">UGX {{ number_format($totalDebits, 2) }}</p>
                        <p class="text-xs text-gray-500 mt-1">All debit transactions</p>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-sm font-medium text-gray-500 mb-2">Total Balance</h3>
                        <p class="text-2xl font-bold text-gray-700">
                            UGX {{ number_format(abs($totalDebits - $totalCredits), 2) }}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">Calculated as total debits minus total credits</p>
                    </div>
                </div>
            </div>

            <!-- Balance Statement Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:justify-between sm:items-center mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">
                                {{ ($statementView ?? 'items') === 'items' ? 'Statement by item' : 'Statement by transaction' }}
                            </h3>
                            <p class="text-sm text-gray-500 mt-1">
                                Default view itemizes ledger lines against invoice items where possible; switch to transactions for raw ledger rows.
                            </p>
                        </div>
                        <div class="flex rounded-lg border border-gray-200 p-1 bg-gray-50 shrink-0">
                            <a href="{{ route('third-party-vendors.balance-statement', ['vendorId' => $vendor['id'], 'view' => 'items']) }}"
                               class="px-4 py-2 text-sm font-medium rounded-md {{ ($statementView ?? 'items') === 'items' ? 'bg-white shadow text-blue-700' : 'text-gray-600 hover:text-gray-900' }}">
                                By item
                            </a>
                            <a href="{{ route('third-party-vendors.balance-statement', ['vendorId' => $vendor['id'], 'view' => 'transactions']) }}"
                               class="px-4 py-2 text-sm font-medium rounded-md {{ ($statementView ?? '') === 'transactions' ? 'bg-white shadow text-blue-700' : 'text-gray-600 hover:text-gray-900' }}">
                                By transaction
                            </a>
                        </div>
                    </div>
                    <div class="text-sm text-gray-500 mb-4">
                        @if(($statementView ?? 'items') === 'items')
                            Ledger entries {{ $balanceHistories->firstItem() ?? 0 }}–{{ $balanceHistories->lastItem() ?? 0 }} of {{ $balanceHistories->total() }}
                            ({{ $itemStatementRows->count() }} line{{ $itemStatementRows->count() === 1 ? '' : 's' }} on this page — debits split per invoice item).
                        @else
                            Showing {{ $balanceHistories->firstItem() ?? 0 }} to {{ $balanceHistories->lastItem() ?? 0 }} of {{ $balanceHistories->total() }} transactions
                        @endif
                    </div>

                    @if($balanceHistories->count() > 0)
                    <div class="overflow-x-auto">
                        @if(($statementView ?? 'items') === 'items')
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item / line</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($itemStatementRows as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div>{{ $row['created_at']->format('M d, Y') }}</div>
                                        <div class="text-xs text-gray-500">{{ $row['created_at']->format('H:i:s') }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <div class="font-medium">{{ $row['line_label'] }}</div>
                                        @if(!empty($row['detail_description']) && $row['detail_description'] !== $row['line_label'])
                                            <div class="text-xs text-gray-500 mt-0.5">{{ $row['detail_description'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        @if($row['client'] ?? null)
                                            <div class="font-medium">{{ $row['client']->name }}</div>
                                            @if($row['client']->client_id)
                                                <div class="text-xs text-gray-500">ID: {{ $row['client']->client_id }}</div>
                                            @endif
                                        @else
                                            <span class="text-gray-400">N/A</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        @if(!empty($row['invoice']))
                                            <a href="{{ route('invoices.show', $row['invoice']->id) }}"
                                               class="text-blue-600 hover:text-blue-800 font-medium underline">
                                                {{ $row['invoice']->invoice_number ?? 'N/A' }}
                                            </a>
                                        @else
                                            <span class="text-gray-400">N/A</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $row['reference'] ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 py-1 text-xs rounded-full {{ ($row['transaction_type'] ?? '') === 'credit' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ ucfirst($row['transaction_type'] ?? '') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium {{ ($row['transaction_type'] ?? '') === 'credit' ? 'text-green-600' : 'text-red-600' }}">
                                        {{ ($row['transaction_type'] ?? '') === 'credit' ? '+' : '-' }}{{ number_format((float) ($row['amount'] ?? 0), 2) }} UGX
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ !empty($row['payment_method']) ? ucwords(str_replace('_', ' ', $row['payment_method'])) : 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @if(!empty($row['payment_status']))
                                        <span class="px-2 py-1 text-xs rounded-full {{ $row['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : ($row['payment_status'] === 'pending_payment' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                            {{ ucfirst(str_replace('_', ' ', $row['payment_status'])) }}
                                        </span>
                                        @else
                                        <span class="text-gray-400">N/A</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @else
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($balanceHistories as $history)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div>{{ $history->created_at->format('M d, Y') }}</div>
                                        <div class="text-xs text-gray-500">{{ $history->created_at->format('H:i:s') }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <div class="font-medium">{{ $history->description }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        @if($history->client)
                                            <div class="font-medium">{{ $history->client->name }}</div>
                                            @if($history->client->client_id)
                                                <div class="text-xs text-gray-500">ID: {{ $history->client->client_id }}</div>
                                            @endif
                                            @if($history->client->phone_number)
                                                <div class="text-xs text-gray-500">Phone: {{ $history->client->phone_number }}</div>
                                            @endif
                                        @else
                                            <span class="text-gray-400">N/A</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        @if($history->invoice)
                                            <a href="{{ route('invoices.show', $history->invoice->id) }}" 
                                               class="text-blue-600 hover:text-blue-800 font-medium underline">
                                                {{ $history->invoice->invoice_number ?? 'N/A' }}
                                            </a>
                                            @if($history->invoice->total_amount)
                                                <div class="text-xs text-gray-500">
                                                    Total: UGX {{ number_format($history->invoice->total_amount, 2) }}
                                                </div>
                                            @endif
                                        @else
                                            <span class="text-gray-400">N/A</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $history->reference_number ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 py-1 text-xs rounded-full {{ $history->transaction_type === 'credit' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ ucfirst($history->transaction_type) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium {{ $history->transaction_type === 'credit' ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $history->transaction_type === 'credit' ? '+' : '-' }}{{ number_format(abs($history->change_amount), 2) }} UGX
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $history->payment_method ? ucwords(str_replace('_', ' ', $history->payment_method)) : 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @if($history->payment_status)
                                        <span class="px-2 py-1 text-xs rounded-full {{ $history->payment_status === 'paid' ? 'bg-green-100 text-green-800' : ($history->payment_status === 'pending_payment' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                            {{ ucfirst(str_replace('_', ' ', $history->payment_status)) }}
                                        </span>
                                        @else
                                        <span class="text-gray-400">N/A</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @endif
                    </div>
                    
                    <!-- Pagination -->
                    <div class="mt-6">
                        {{ $balanceHistories->links() }}
                    </div>
                    @else
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No transactions found</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Balance history entries will appear here when invoices are created with this vendor.
                        </p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Additional Information -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Account Information</h3>
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Third-Party Payer ID</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $thirdPartyPayer->id }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Account Type</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ ucfirst(str_replace('_', ' ', $thirdPartyPayer->type)) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Account Status</dt>
                            <dd class="mt-1">
                                <span class="px-2 py-1 text-xs rounded-full {{ $thirdPartyPayer->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ ucfirst($thirdPartyPayer->status) }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created At</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $thirdPartyPayer->created_at->format('M d, Y H:i:s') }}</dd>
                        </div>
                        @if($thirdPartyPayer->notes)
                        <div class="md:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">Notes</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $thirdPartyPayer->notes }}</dd>
                        </div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
