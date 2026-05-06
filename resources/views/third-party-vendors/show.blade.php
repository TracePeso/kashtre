<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Third Party Vendor Details') }} - {{ $vendor['name'] }}
            </h2>
            <a href="{{ route('third-party-vendors.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                Back to List
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Vendor Information & Financial Summary -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Vendor Information</h3>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Vendor Name</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $vendor['name'] }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Code</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <code class="bg-gray-100 px-2 py-1 rounded">{{ $vendor['code'] }}</code>
                                    </dd>
                                </div>
                                @if($vendor['email'])
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Email</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $vendor['email'] }}</dd>
                                </div>
                                @endif
                                @if($vendor['phone'])
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Phone</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $vendor['phone'] }}</dd>
                                </div>
                                @endif
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                                    <dd class="mt-1">
                                        @if($thirdPartyPayer)
                                            @if($thirdPartyPayer->isActive())
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    ✓ Active
                                                </span>
                                            @elseif($thirdPartyPayer->isSuspended())
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                    ⊘ Suspended
                                                </span>
                                            @elseif($thirdPartyPayer->isBlocked())
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                    ✕ Blocked
                                                </span>
                                            @endif
                                        @elseif($vendor['is_active'])
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Active
                                            </span>
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                Inactive
                                            </span>
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Connected At</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ \Carbon\Carbon::parse($vendor['connected_at'])->format('M d, Y H:i') }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Financial Summary</h3>
                            @if($thirdPartyPayer)
                                <dl class="space-y-3 mb-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Total Balance</dt>
                                        <dd class="mt-1 text-lg font-semibold text-gray-900">
                                            UGX {{ number_format(abs($totalDebits - $totalCredits), 2) }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Current Balance</dt>
                                        <dd class="mt-1 text-lg font-semibold {{ $currentBalance < 0 ? 'text-red-600' : ($currentBalance > 0 ? 'text-green-600' : 'text-gray-900') }}">
                                            UGX {{ number_format($currentBalance, 2) }}
                                            @if($currentBalance < 0)
                                                <span class="text-xs text-red-500">(Amount Owed)</span>
                                            @elseif($currentBalance > 0)
                                                <span class="text-xs text-green-500">(Credit Available)</span>
                                            @endif
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Credit Limit</dt>
                                        <dd class="mt-1 text-lg font-semibold text-gray-900">
                                            UGX {{ number_format((($thirdPartyPayer->credit_limit ?? 0) > 0
                                                ? (float) $thirdPartyPayer->credit_limit
                                                : (float) ($business->max_third_party_credit_limit ?? 0)), 2) }}
                                        </dd>
                                        @if(in_array('Manage Credit Limits', (array) (auth()->user()->permissions ?? [])))
                                            <a
                                                href="{{ route('credit-limit-requests.create', ['entity_type' => 'third_party_payer', 'entity_id' => $thirdPartyPayer->id]) }}"
                                                class="mt-2 inline-flex items-center px-3 py-1.5 border border-blue-600 text-xs font-medium rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100"
                                            >
                                                Request credit limit change
                                            </a>
                                        @endif
                                    </div>
                                </dl>
                                <div class="mt-2">
                                    <a href="{{ route('third-party-vendors.balance-statement', $vendor['id']) }}"
                                       class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Open financial summary
                                    </a>
                                </div>
                            @else
                                <dl class="space-y-3">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                                        <dd class="mt-1 text-sm text-gray-500">
                                            No third-party payer account found for this vendor. Balance history will appear here once invoices are created with this vendor.
                                        </dd>
                                    </div>
                                </dl>
                                <form action="{{ route('third-party-vendors.create-payer', $vendor['id']) }}" method="POST" class="mt-4">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                                        + Create Payer Account
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs Section -->
            @if($thirdPartyPayer)
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex" aria-label="Tabs">
                        <button onclick="showTab('transactions')" id="tab-transactions" class="tab-button active w-1/3 py-4 px-6 text-center border-b-2 font-medium text-sm transition-colors">
                            <span class="border-b-2 border-blue-500 pb-4 px-1 text-blue-600">Transactions</span>
                        </button>
                        <button onclick="showTab('invoices')" id="tab-invoices" class="tab-button w-1/3 py-4 px-6 text-center border-b-2 font-medium text-sm transition-colors">
                            <span class="border-b-2 border-transparent pb-4 px-1 text-gray-500 hover:text-gray-700">Invoices</span>
                        </button>
                        <button onclick="showTab('exclusions')" id="tab-exclusions" class="tab-button w-1/3 py-4 px-6 text-center border-b-2 font-medium text-sm transition-colors">
                            <span class="border-b-2 border-transparent pb-4 px-1 text-gray-500 hover:text-gray-700">Service Exclusions</span>
                        </button>
                    </nav>
                </div>

                <!-- Transactions Tab Content -->
                <div id="content-transactions" class="tab-content p-6">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Recent Transactions</h3>
                            <p class="text-sm text-gray-500">Showing last 10 transactions</p>
                        </div>
                        <a href="{{ route('third-party-vendors.balance-statement', $vendor['id']) }}" 
                           class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                            View Full Statement
                        </a>
                    </div>
                    
                    @if($balanceHistories->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($balanceHistories as $history)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $history->created_at->format('Y-m-d H:i:s') }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ $history->description }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        @if($history->client)
                                            <span class="font-medium">{{ $history->client->name }}</span>
                                            @if($history->client->client_id)
                                                <br><span class="text-xs text-gray-500">ID: {{ $history->client->client_id }}</span>
                                            @endif
                                        @else
                                            <span class="text-gray-400">N/A</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        @if($history->invoice)
                                            <a href="{{ route('invoices.show', $history->invoice->id) }}" class="text-blue-600 hover:text-blue-800 font-medium">
                                                {{ $history->invoice->invoice_number ?? 'N/A' }}
                                            </a>
                                        @else
                                            <span class="text-gray-400">N/A</span>
                                        @endif
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
                    </div>
                    
                    <div class="mt-4 text-center">
                        <a href="{{ route('third-party-vendors.balance-statement', $vendor['id']) }}" 
                           class="text-blue-600 hover:text-blue-800 font-medium">
                            View all transactions →
                        </a>
                    </div>
                    @else
                    <div class="text-center py-8">
                        <p class="text-gray-500">No transactions found. Balance history entries will appear here when invoices are created with this vendor.</p>
                    </div>
                    @endif

                </div>

                <!-- Invoices Tab Content -->
                <div id="content-invoices" class="tab-content p-6 hidden">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Invoices</h3>
                            <p class="text-sm text-gray-500">All invoices for this vendor</p>
                        </div>
                    </div>
                    
                    @if($invoices->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount Paid</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance Due</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($invoices as $invoice)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ $invoice['invoice_number'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div>{{ $invoice['client_name'] }}</div>
                                        @if($invoice['client_id'])
                                            <div class="text-xs text-gray-500">ID: {{ $invoice['client_id'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div>{{ $invoice['business_name'] ?? 'N/A' }}</div>
                                        @if($invoice['branch_name'])
                                            <div class="text-xs text-gray-500">{{ $invoice['branch_name'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        UGX {{ number_format($invoice['total_amount'], 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600">
                                        UGX {{ number_format($invoice['amount_paid'], 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium {{ $invoice['balance_due'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                                        UGX {{ number_format($invoice['balance_due'], 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @php
                                            $statusColors = [
                                                'paid' => 'bg-green-100 text-green-800',
                                                'pending_payment' => 'bg-yellow-100 text-yellow-800',
                                                'partial' => 'bg-blue-100 text-blue-800',
                                            ];
                                            $paymentStatus = $invoice['payment_status'] ?? 'pending_payment';
                                            $statusColor = $statusColors[$paymentStatus] ?? 'bg-gray-100 text-gray-800';
                                        @endphp
                                        <span class="px-2 py-1 text-xs rounded-full {{ $statusColor }}">
                                            {{ ucfirst(str_replace('_', ' ', $paymentStatus)) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ \Carbon\Carbon::parse($invoice['created_at'])->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="{{ route('invoices.show', $invoice['id']) }}" class="text-blue-600 hover:text-blue-900">
                                            View
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No invoices found</h3>
                        <p class="mt-1 text-sm text-gray-500">Invoices will appear here when clients make purchases with this vendor's insurance.</p>
                    </div>
                    @endif
                </div>

                <!-- Service Exclusions Tab Content -->
                <div id="content-exclusions" class="tab-content p-6 hidden">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Service Exclusions for this Third Party</h3>
                            <p class="text-sm text-gray-500">
                                These exclusions apply to <span class="font-semibold">{{ $vendor['name'] }}</span> for all invoices paid by this third party.
                            </p>
                        </div>
                        <a href="{{ route('third-party-payers.show', $thirdPartyPayer) }}"
                           class="hidden md:inline-flex items-center text-xs font-medium text-blue-600 hover:text-blue-800">
                            Open third party payer page
                            <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    </div>

                    @if(in_array('Edit Third Party Payers', (array) (auth()->user()->permissions ?? [])))
                        <div class="max-w-4xl">
                            <div class="bg-blue-50 border border-blue-100 rounded-lg p-4 mb-4">
                                <p class="text-xs text-blue-800">
                                    Select items from your price list that should be excluded from this third party's terms.
                                    Invoices containing excluded items will not be saved when payment method is insurance / third party.
                                    Business-level defaults from Business Settings still apply on top of these.
                                </p>
                            </div>

                            <form action="{{ route('third-party-payers.update-excluded-items', $thirdPartyPayer) }}" method="POST" class="space-y-4">
                            @csrf
                            @method('POST')
                            <input type="hidden" name="from_vendor_page" value="1">

                            <div class="mb-4">
                                <label for="excluded_items_vendor" class="block text-sm font-medium text-gray-700 mb-2">
                                    Excluded Items
                                </label>

                                <!-- Quick Filter Buttons (same UX as Third Party Payers page) -->
                                <div class="mb-3 flex flex-wrap gap-2">
                                    <button type="button" class="filter-btn-tpp px-3 py-1 text-sm rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 active" data-filter="all">
                                        All Items
                                    </button>
                                    <button type="button" class="filter-btn-tpp px-3 py-1 text-sm rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" data-filter="service">
                                        Services
                                    </button>
                                    <button type="button" class="filter-btn-tpp px-3 py-1 text-sm rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" data-filter="good">
                                        Goods
                                    </button>
                                    <button type="button" class="filter-btn-tpp px-3 py-1 text-sm rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" data-filter="package">
                                        Packages
                                    </button>
                                    <button type="button" class="filter-btn-tpp px-3 py-1 text-sm rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" data-filter="bulk">
                                        Bulk Items
                                    </button>
                                </div>

                                <select
                                    name="excluded_items[]"
                                    id="excluded_items_vendor"
                                    multiple
                                    style="width: 100%;"
                                >
                                    @foreach($items as $item)
                                        <option
                                            value="{{ $item->id }}"
                                            data-type="{{ $item->type }}"
                                            {{ in_array($item->id, old('excluded_items', (array) ($thirdPartyPayer->excluded_items ?? []))) ? 'selected' : '' }}
                                        >
                                            {{ $item->name }}@if($item->code) ({{ $item->code }})@endif
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-2 text-xs text-gray-500">
                                    Tip: Use quick filters to narrow down items by type, then search and select multiple items.
                                </p>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit"
                                        class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                                    Update Exclusions
                                </button>
                            </div>
                            </form>
                        </div>
                    @else
                        <p class="text-sm text-gray-500">
                            You do not have permission to edit third party payer exclusions. Contact an administrator if you need this changed.
                        </p>
                    @endif
                </div>
            </div>

            <!-- Vendor Management / Blocking Card -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    @if($thirdPartyPayer->isActive())
                        <!-- Active - show suspend/block buttons -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Vendor Management</h3>
                            <p class="text-sm text-gray-600 mb-4">This vendor is currently active. You can suspend or block it.</p>
                            
                            <!-- Suspend Form -->
                            <div class="border-l-4 border-yellow-400 bg-yellow-50 p-4 rounded">
                                <h4 class="font-semibold text-yellow-900 mb-3">⊘ Suspend Vendor</h4>
                                <p class="text-sm text-yellow-800 mb-3">Temporarily suspend this vendor. They can be reactivated later.</p>
                                <form action="{{ route('third-party-vendors.block', $vendor['id']) }}" method="POST" class="space-y-3">
                                    @csrf
                                    <input type="hidden" name="status" value="suspended">
                                    <div>
                                        <label class="block text-sm font-medium text-yellow-900 mb-2">Reason for suspension:</label>
                                        <textarea name="reason" required placeholder="Enter reason for suspension..."
                                                  class="w-full px-3 py-2 border border-yellow-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500" rows="2"></textarea>
                                    </div>
                                    <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg bg-yellow-600 text-white hover:bg-yellow-700">
                                        Suspend
                                    </button>
                                </form>
                            </div>

                            <!-- Block Form -->
                            <div class="border-l-4 border-red-400 bg-red-50 p-4 rounded">
                                <h4 class="font-semibold text-red-900 mb-3">✕ Block Vendor</h4>
                                <p class="text-sm text-red-800 mb-3">Block this vendor. This will prevent any access to this vendor's data.</p>
                                <form action="{{ route('third-party-vendors.block', $vendor['id']) }}" method="POST" class="space-y-3">
                                    @csrf
                                    <input type="hidden" name="status" value="blocked">
                                    <div>
                                        <label class="block text-sm font-medium text-red-900 mb-2">Reason for blocking:</label>
                                        <textarea name="reason" required placeholder="Enter reason for blocking..."
                                                  class="w-full px-3 py-2 border border-red-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500" rows="2"></textarea>
                                    </div>
                                    <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg bg-red-600 text-white hover:bg-red-700"
                                            onclick="return confirm('Are you sure you want to block this vendor?')">
                                        Block
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <!-- Suspended or Blocked - show reactivate button -->
                        <div class="border-l-4 border-green-400 bg-green-50 p-4 rounded">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Vendor Management</h3>
                            <h4 class="font-semibold text-green-900 mb-3 mt-3">✓ Reactivate Vendor</h4>
                            <p class="text-sm text-green-800 mb-4">
                                This vendor is currently {{ $thirdPartyPayer->status === 'suspended' ? 'suspended' : 'blocked' }}.
                                @if($thirdPartyPayer->block_reason)
                                    <br><strong>Reason:</strong> {{ $thirdPartyPayer->block_reason }}
                                @endif
                                <br>Click below to reactivate it.
                            </p>
                            <form action="{{ route('third-party-vendors.reactivate', $vendor['id']) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg bg-green-600 text-white hover:bg-green-700"
                                        onclick="return confirm('Are you sure you want to reactivate this vendor?')">
                                    Reactivate Vendor
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
            @else
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <div class="text-center py-8">
                        <p class="text-gray-500">No third-party payer account found for this vendor. Balance history will appear here once invoices are created with this vendor.</p>
                    </div>
                </div>
            </div>
            @endif

{{-- Select2 CSS for exclusions tab --}}
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/css/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

{{-- jQuery + Select2 JS (only used for the exclusions tab) --}}
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
function showTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    // Remove active class from all tabs
    document.querySelectorAll('.tab-button').forEach(button => {
        const span = button.querySelector('span');
        span.classList.remove('border-blue-500', 'text-blue-600');
        span.classList.add('border-transparent', 'text-gray-500');
    });
    
    // Show selected tab content
    document.getElementById('content-' + tabName).classList.remove('hidden');
    
    // Add active class to selected tab
    const activeButton = document.getElementById('tab-' + tabName);
    const activeSpan = activeButton.querySelector('span');
    activeSpan.classList.remove('border-transparent', 'text-gray-500');
    activeSpan.classList.add('border-blue-500', 'text-blue-600');
}

// Select2 + filter behaviour for exclusions tab (mirrors Third Party Payer page)
$(document).ready(function () {
    const $select = $('#excluded_items_vendor');
    if (!$select.length) return;

    $select.select2({
        theme: 'bootstrap-5',
        placeholder: 'Select items to exclude from third-party payer terms',
        allowClear: true,
        width: '100%'
    });

    $('.filter-btn-tpp').on('click', function() {
        const filter = $(this).data('filter');

        // Update active button
        $('.filter-btn-tpp').removeClass('active bg-blue-600 text-white').addClass('bg-white text-gray-700');
        $(this).removeClass('bg-white text-gray-700').addClass('active bg-blue-600 text-white');

        // Filter options
        if (filter === 'all') {
            $select.find('option').prop('disabled', false);
        } else {
            $select.find('option').each(function() {
                const $option = $(this);
                if ($option.data('type') === filter) {
                    $option.prop('disabled', false);
                } else {
                    $option.prop('disabled', true);
                }
            });
        }

        // Refresh Select2 and open dropdown to show filtered results
        $select.trigger('change.select2');
        $select.select2('open');
    });
});
</script>

<style>
.tab-button {
    cursor: pointer;
}
.tab-button span {
    transition: all 0.2s;
}
.tab-button.active span {
    border-bottom-color: #3b82f6;
    color: #3b82f6;
}

/* Select2 styling for exclusions tab */
.select2-container--bootstrap-5 .select2-selection--multiple {
    min-height: 3rem;
    border-radius: 0.5rem;
    border-color: #e5e7eb;
    padding: 0.25rem 0.5rem;
}

.select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
    background-color: #eff6ff;
    border-color: #bfdbfe;
    color: #1e3a8a;
    border-radius: 9999px;
    padding: 0.1rem 0.5rem;
    font-size: 0.75rem;
}

.select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove {
    color: #1d4ed8;
    margin-right: 0.25rem;
}

.select2-container--bootstrap-5 .select2-results__option {
    font-size: 0.85rem;
    padding-top: 0.35rem;
    padding-bottom: 0.35rem;
}

.select2-container--bootstrap-5 .select2-results__options {
    max-height: 260px;
}
</style>
        </div>
    </div>
</x-app-layout>
