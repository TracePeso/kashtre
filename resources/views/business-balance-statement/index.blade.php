<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Business Account Statement') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
<div class="container mx-auto px-4 py-8">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Business Account Statement</h1>
            <p class="text-gray-600">
                @if(auth()->user()->business_id == 1)
                    Super Business View - All Businesses Financial Overview
                @else
                    Business Financial Overview
                @endif
            </p>
        </div>

        <!-- Action Button -->
        @if(auth()->user()->business_id != 1 && $canUserCreateWithdrawal(auth()->user()))
        <div class="mb-8">
            <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Withdraw Funds</h3>
                        <p class="text-gray-600">Create a new withdrawal request for your business</p>
                    </div>
                    <a href="{{ route('withdrawal-requests.create') }}" 
                       class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium transition-colors flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Withdraw Funds
                    </a>
                </div>
            </div>
        </div>
        @endif

        <!-- Summary Cards - Reorganized: Total Balance, Available Balance, Pending Maturity, Pending Payments, Others -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <!-- 1. Total Balance -->
            <a href="#statement-transactions"
               class="block bg-white rounded-lg shadow-md p-6 border border-gray-200 cursor-pointer hover:shadow-lg hover:border-gray-300 transition-all focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900">Total Balance</h3>
                        <p class="text-2xl font-bold text-green-600">
                            UGX {{ number_format($totalBalance ?? (($totalCredits ?? 0) - ($totalDebits ?? 0)), 2) }}
                        </p>
                    </div>
                </div>
            </a>

            <!-- 2. Available Balance -->
            <a href="#statement-transactions"
               class="block bg-white rounded-lg shadow-md p-6 border border-gray-200 cursor-pointer hover:shadow-lg hover:border-gray-300 transition-all focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900">Available Balance</h3>
                        <p class="text-2xl font-bold text-blue-600">
                            UGX {{ number_format($availableBalance ?? 0, 2) }}
                        </p>
                    </div>
                </div>
            </a>

            <!-- 3. Pending Maturity -->
            <button type="button"
                    class="w-full text-left bg-white rounded-lg shadow-md p-6 border border-gray-200 cursor-pointer hover:shadow-lg hover:border-gray-300 transition-all focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2"
                    onclick="toggleTab('pendingMaturityTab')">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">Pending Maturity</h3>
                            <p class="text-2xl font-bold text-purple-600">
                                UGX {{ number_format($pendingMaturityTotal ?? 0, 2) }}
                            </p>
                        </div>
                    </div>
                    <svg id="pendingMaturityTabIcon" class="w-5 h-5 text-gray-400 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </div>
            </button>

            <!-- 4. Pending Payments -->
            <button type="button"
                    class="w-full text-left bg-white rounded-lg shadow-md p-6 border border-gray-200 cursor-pointer hover:shadow-lg hover:border-gray-300 transition-all focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2"
                    onclick="toggleTab('pendingPaymentsTab')">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">Pending Payments</h3>
                            <p class="text-2xl font-bold text-orange-600">
                                UGX {{ number_format($pendingPaymentsTotal ?? 0, 2) }}
                            </p>
                        </div>
                    </div>
                    <svg id="pendingPaymentsTabIcon" class="w-5 h-5 text-gray-400 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </div>
            </button>
        </div>

        <!-- Additional Cards Row -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- Total Debits -->
            <a href="#statement-transactions"
               class="block bg-white rounded-lg shadow-md p-6 border border-gray-200 cursor-pointer hover:shadow-lg hover:border-gray-300 transition-all focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 text-red-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900">Total Debits</h3>
                        <p class="text-2xl font-bold text-red-600">
                            UGX {{ number_format($totalDebits ?? 0, 2) }}
                        </p>
                    </div>
                </div>
            </a>

            <!-- Total Credits -->
            <a href="#statement-transactions"
               class="block bg-white rounded-lg shadow-md p-6 border border-gray-200 cursor-pointer hover:shadow-lg hover:border-gray-300 transition-all focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-emerald-100 text-emerald-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900">Total Credits</h3>
                        <p class="text-2xl font-bold text-emerald-600">
                            UGX {{ number_format($totalCredits ?? 0, 2) }}
                        </p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Pending Maturity Table (Hidden by default) -->
        <div id="pendingMaturityTab" class="bg-white rounded-lg shadow-md border border-gray-200 mb-8 hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Pending Maturity - Credit Transactions</h2>
                <p class="text-sm text-gray-600 mt-1">Credit transactions with outstanding days to maturity</p>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            @if(auth()->user()->business_id == 1)
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business</th>
                            @endif
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client/Payer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount Due</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount Paid</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outstanding Days to Maturity</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($pendingMaturityList ?? [] as $ar)
                            <tr class="hover:bg-gray-50">
                                @if(auth()->user()->business_id == 1)
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ $ar->business->name ?? 'N/A' }}
                                    </td>
                                @endif
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    @if($ar->payer_type === 'third_party' && $ar->thirdPartyPayer)
                                        {{ $ar->thirdPartyPayer->name ?? 'N/A' }}
                                        <span class="text-xs text-gray-500">(Third Party)</span>
                                    @elseif($ar->client)
                                        {{ $ar->client->name ?? 'N/A' }}
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    @if($ar->invoice)
                                        <a href="{{ route('invoices.show', $ar->invoice->id) }}" class="text-blue-600 hover:text-blue-900">
                                            {{ $ar->invoice->invoice_number ?? 'N/A' }}
                                        </a>
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ number_format($ar->amount_due, 2) }} UGX
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ number_format($ar->amount_paid, 2) }} UGX
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-red-600">
                                    {{ number_format($ar->balance, 2) }} UGX
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $ar->due_date ? \Carbon\Carbon::parse($ar->due_date)->format('M d, Y') : 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        @if($ar->outstanding_days_to_maturity <= 7) bg-red-100 text-red-800
                                        @elseif($ar->outstanding_days_to_maturity <= 30) bg-orange-100 text-orange-800
                                        @else bg-yellow-100 text-yellow-800 @endif">
                                        {{ $ar->outstanding_days_to_maturity ?? 0 }} days
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->business_id == 1 ? '8' : '7' }}" class="px-6 py-8 text-center text-gray-500">
                                    No pending maturity transactions found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pending Payments Table (Hidden by default) -->
        <div id="pendingPaymentsTab" class="bg-white rounded-lg shadow-md border border-gray-200 mb-8 hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Pending Payments</h2>
                <p class="text-sm text-gray-600 mt-1">Transactions with pending payment status</p>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            @if(auth()->user()->business_id == 1)
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business</th>
                            @endif
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days to mature</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($pendingPaymentsList ?? [] as $history)
                            <tr class="hover:bg-gray-50">
                                @if(auth()->user()->business_id == 1)
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ $history->business->name ?? 'N/A' }}
                                    </td>
                                @endif
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Credit
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">
                                    +{{ number_format($history->amount, 2) }} UGX
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <div class="max-w-xs">
                                        {{ $history->description }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $history->created_at->format('M d, Y H:i') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $history->businessStatementPaymentStatusBadgeClass() }}">
                                        {{ $history->businessStatementPaymentStatusDisplay() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    {{ $history->businessStatementCreditMaturityLabel() }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    @if($history->payment_method)
                                        {{ ucwords(str_replace('_', ' ', $history->payment_method)) }}
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->business_id == 1 ? '8' : '7' }}" class="px-6 py-8 text-center text-gray-500">
                                    No pending payments found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Transactions Tab -->
        <div id="statement-transactions" class="scroll-mt-24 bg-white rounded-lg shadow-md border border-gray-200 mb-8">
            <div class="px-6 py-4 border-b border-gray-200 cursor-pointer hover:bg-gray-50 transition-colors" onclick="toggleTab('recentTransactionsTab')">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <h2 class="text-xl font-semibold text-gray-900">Recent Transactions</h2>
                        @if(auth()->user()->business_id == 1)
                            <a href="{{ route('business-balance-statement.show', 1) }}" 
                               class="ml-4 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                               onclick="event.stopPropagation();">
                                View All Transactions
                            </a>
                        @endif
                    </div>
                    <svg id="recentTransactionsTabIcon" class="w-5 h-5 text-gray-400 transform transition-transform rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </div>
            </div>
            
            <div id="recentTransactionsTab">
                <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days to mature</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($businessBalanceHistories as $history)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    @if(auth()->user()->business_id == 1)
                                        <a href="{{ route('business-balance-statement.show', $history->business) }}" 
                                           class="text-blue-600 hover:text-blue-900">
                                            {{ $history->business->name }}
                                        </a>
                                    @else
                                        {{ $history->business->name }}
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($history->type === 'credit')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Credit
                                        </span>
                                    @elseif($history->type === 'package')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            Package
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Debit
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    @if($history->type === 'credit')
                                        <span class="text-green-600">+{{ number_format($history->amount, 2) }} UGX</span>
                                    @elseif($history->type === 'package')
                                        <span class="text-blue-600">+{{ number_format($history->amount, 2) }} UGX</span>
                                    @else
                                        <span class="text-red-600">{{ number_format($history->amount, 2) }} UGX</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <div class="max-w-xs">
                                        {{ $history->description }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $history->created_at->format('M d, Y H:i') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $history->businessStatementPaymentStatusBadgeClass() }}">
                                        {{ $history->businessStatementPaymentStatusDisplay() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    {{ $history->businessStatementCreditMaturityLabel() }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    @if($history->payment_method)
                                        {{ ucwords(str_replace('_', ' ', $history->payment_method)) }}
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                    No transactions found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
                
                <!-- Pagination -->
                @if($businessBalanceHistories->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200">
                        {{ $businessBalanceHistories->links() }}
                    </div>
                @endif
            </div>
        </div>

        <!-- Navigation -->
        <div class="mt-8 flex justify-between">
            <a href="{{ route('dashboard') }}" 
               class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-colors">
                Back to Dashboard
            </a>
            
            @if(auth()->user()->business_id == 1)
                <a href="{{ route('kashtre-balance-statement.index') }}" 
                   class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors">
                    View Kashtre Statement
                </a>
            @endif
        </div>
                    </div>
            </div>
        </div>
    </div>

    <script>
        function toggleTab(tabId) {
            const tab = document.getElementById(tabId);
            const iconId = tabId + 'Icon';
            const icon = document.getElementById(iconId);
            
            if (tab) {
                // Toggle visibility
                if (tab.classList.contains('hidden')) {
                    tab.classList.remove('hidden');
                    if (icon) {
                        icon.classList.add('rotate-180');
                    }
                    tab.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    tab.classList.add('hidden');
                    if (icon) {
                        icon.classList.remove('rotate-180');
                    }
                }
            }
        }
    </script>
</x-app-layout>

