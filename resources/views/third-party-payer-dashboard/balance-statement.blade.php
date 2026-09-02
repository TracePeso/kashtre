<x-third-party-payer-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Balance Statement') }} - {{ $thirdPartyPayer->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @php
                $availableBalance = (float) ($balanceSummary['available_balance'] ?? 0);
                $totalBalance = (float) ($balanceSummary['total_balance'] ?? 0);
            @endphp
            <!-- Third-Party Payer Information Card -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">{{ $thirdPartyPayer->name }}</h3>
                            <p class="text-sm text-gray-500">Type: {{ ucfirst(str_replace('_', ' ', $thirdPartyPayer->type)) }}</p>
                            @if($thirdPartyPayer->phone_number)
                            <p class="text-sm text-gray-500">Phone: {{ $thirdPartyPayer->phone_number }}</p>
                            @endif
                            @if($thirdPartyPayer->email)
                            <p class="text-sm text-gray-500">Email: {{ $thirdPartyPayer->email }}</p>
                            @endif
                        </div>
                        <div class="text-right">
                            <div class="space-y-2">
                                <div>
                                    <p class="text-sm text-gray-500">Available balance</p>
                                    <p class="text-lg font-semibold {{ $availableBalance < 0 ? 'text-red-600' : ($availableBalance > 0 ? 'text-green-600' : 'text-gray-700') }}">
                                        UGX {{ number_format($availableBalance, 2) }}
                                    </p>
                                    @if($availableBalance < 0)
                                        <p class="text-xs text-red-500">(Amount owed)</p>
                                    @elseif($availableBalance > 0)
                                        <p class="text-xs text-green-500">(Credit available)</p>
                                    @endif
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Total balance</p>
                                    <p class="text-lg font-semibold text-gray-700">
                                        UGX {{ number_format($totalBalance, 2) }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Credit Limit</p>
                                    <p class="text-lg font-semibold text-gray-700">
                                        UGX {{ number_format((($thirdPartyPayer->credit_limit ?? 0) > 0
                                            ? (float) $thirdPartyPayer->credit_limit
                                            : (float) ($thirdPartyPayer->business->max_third_party_credit_limit ?? 0)), 2) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-sm font-medium text-gray-500 mb-2">Total credits</h3>
                        <p class="text-2xl font-bold text-green-600">UGX {{ number_format($balanceSummary['total_credits'] ?? 0, 2) }}</p>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-sm font-medium text-gray-500 mb-2">Total debits</h3>
                        <p class="text-2xl font-bold text-red-600">UGX {{ number_format($balanceSummary['total_debits'] ?? 0, 2) }}</p>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-sm font-medium text-gray-500 mb-2">Available balance</h3>
                        <p class="text-2xl font-bold {{ $availableBalance < 0 ? 'text-red-600' : ($availableBalance > 0 ? 'text-green-600' : 'text-gray-700') }}">
                            UGX {{ number_format($availableBalance, 2) }}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">Total credits minus total debits</p>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-sm font-medium text-gray-500 mb-2">Total balance</h3>
                        <p class="text-2xl font-bold text-gray-700">UGX {{ number_format($totalBalance, 2) }}</p>
                    </div>
                </div>
            </div>

            <!-- Balance Statement Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Transaction History</h3>
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
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Available balance</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total balance</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($balanceHistories as $history)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $history->created_at->format('M d, Y H:i') }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ $history->description }}
                                        @if($history->notes)
                                            <br><span class="text-xs text-gray-500">{{ $history->notes }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        @if($history->client)
                                            <span class="font-medium">{{ $history->client->name }}</span>
                                            @if($history->client->client_id)
                                                <br><span class="text-xs text-gray-500">ID: {{ $history->client->client_id }}</span>
                                            @endif
                                            @if($history->client->phone_number)
                                                <br><span class="text-xs text-gray-500">Phone: {{ $history->client->phone_number }}</span>
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
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        UGX {{ number_format((float) ($history->available_balance_after ?? $history->new_balance ?? 0), 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        UGX {{ number_format((float) ($history->total_balance_after ?? $history->new_balance ?? 0), 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $history->payment_method ? ucwords(str_replace('_', ' ', $history->payment_method)) : 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @if($history->payment_status)
                                        <span class="px-2 py-1 text-xs rounded-full {{ $history->payment_status === 'paid' ? 'bg-green-100 text-green-800' : ($history->payment_status === 'pending_payment' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                            {{ \App\Models\ThirdPartyPayerBalanceHistory::normalizePaymentStatusLabel($history->payment_status) }}
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
                    
                    <!-- Pagination -->
                    <div class="mt-4">
                        {{ $balanceHistories->links() }}
                    </div>
                    @else
                    <div class="text-center py-8">
                        <p class="text-gray-500">No transactions found.</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-third-party-payer-layout>
