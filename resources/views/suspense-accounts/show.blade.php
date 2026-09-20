<x-app-layout>
    <div class="py-12 bg-gradient-to-b from-[#011478]/10 to-transparent">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Page Header -->
            <div class="mb-8 flex justify-between items-center bg-white/50 backdrop-blur-sm p-6 rounded-xl shadow-sm">
                <div>
                    <h2 class="text-3xl font-bold text-[#011478] flex items-center">
                        <i class="fas fa-piggy-bank mr-3"></i>
                        {{ $account->name }}
                    </h2>
                    <p class="text-gray-600 mt-2">Detailed view of suspense account information and money movements</p>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="{{ route('suspense-accounts.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-sm p-6">
                <!-- Account Information -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Account Information -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="p-6 border-b border-gray-200">
                            <h4 class="text-lg font-semibold text-gray-900">Account Information</h4>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="font-medium text-gray-600">Account Name:</span>
                                    <span class="text-gray-900">{{ $account->name }}</span>
                                </div>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="font-medium text-gray-600">Account Type:</span>
                                    <span>
                                        @switch($account->type)
                                            @case('package_suspense_account')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Package Suspense</span>
                                                @break
                                            @case('general_suspense_account')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">General Suspense</span>
                                                @break
                                            @case('kashtre_suspense_account')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Kashtre Suspense</span>
                                                @break
                                            @case('withdrawal_suspense_account')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">Withdrawal Suspense</span>
                                                @break
                                            @default
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">{{ ucfirst(str_replace('_', ' ', $account->type)) }}</span>
                                        @endswitch
                                    </span>
                                </div>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="font-medium text-gray-600">Current Balance:</span>
                                    <span class="text-2xl font-bold text-[#011478]">
                                        {{ number_format($account->balance, 0) }} UGX
                                    </span>
                                </div>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="font-medium text-gray-600">Currency:</span>
                                    <span class="text-gray-900">{{ $account->currency }}</span>
                                </div>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="font-medium text-gray-600">Status:</span>
                                    <span>
                                        @if($account->is_active)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Inactive</span>
                                        @endif
                                    </span>
                                </div>
                                @if($account->client && $account->type !== 'withdrawal_suspense_account')
                                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                        <span class="font-medium text-gray-600">Client:</span>
                                        <span class="text-gray-900">{{ $account->client->name }}</span>
                                    </div>
                                @endif
                                @if($account->type === 'withdrawal_suspense_account')
                                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                        <span class="font-medium text-gray-600">Business:</span>
                                        <span class="text-gray-900">{{ $account->business->name }}</span>
                                    </div>
                                @endif
                                @if($account->description)
                                    <div class="flex justify-between items-center py-2">
                                        <span class="font-medium text-gray-600">Description:</span>
                                        <span class="text-gray-900">{{ $account->description }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    <!-- Account Purpose -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="p-6 border-b border-gray-200">
                            <h4 class="text-lg font-semibold text-gray-900">Account Purpose</h4>
                        </div>
                        <div class="p-6">
                            @switch($account->type)
                                @case('package_suspense_account')
                                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                        <h5 class="text-blue-900 font-semibold flex items-center mb-2">
                                            <i class="fas fa-box mr-2"></i>Package Suspense Account
                                        </h5>
                                        <p class="text-blue-800">Holds funds for paid package items if nothing has been used. See more notes in the side panel.</p>
                                    </div>
                                    @break
                                @case('general_suspense_account')
                                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                        <h5 class="text-yellow-900 font-semibold flex items-center mb-2">
                                            <i class="fas fa-clock mr-2"></i>General Suspense Account
                                        </h5>
                                        <p class="text-yellow-800">Holds funds for ordered items not yet offered for all clients. If the item is eventually not offered, this list is sent to the technical supervisor for verification, then finance for authorization, then CEO for approval. After approval, the funds return to the client's account.</p>
                                    </div>
                                    @break
                                @case('kashtre_suspense_account')
                                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                        <h5 class="text-green-900 font-semibold flex items-center mb-2">
                                            <i class="fas fa-hand-holding-usd mr-2"></i>Kashtre Suspense Account
                                        </h5>
                                        <p class="text-green-800">Holds all service fees charged on the invoice. For services paid for but not yet offered. Includes service fees for deposits.</p>
                                    </div>
                                    @break
                                @case('withdrawal_suspense_account')
                                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                                        <h5 class="text-purple-900 font-semibold flex items-center mb-2">
                                            <i class="fas fa-money-bill-wave mr-2"></i>Withdrawal Suspense Account
                                        </h5>
                                        <p class="text-purple-800">Holds funds for pending withdrawal requests from businesses. Funds are held here until the withdrawal is approved and processed, or rejected and returned to the business account.</p>
                                    </div>
                                    @break
                                @default
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                        <h5 class="text-gray-900 font-semibold flex items-center mb-2">
                                            <i class="fas fa-info-circle mr-2"></i>Account Information
                                        </h5>
                                        <p class="text-gray-800">{{ $account->description ?? 'No description available.' }}</p>
                                    </div>
                            @endswitch
                        </div>
                    </div>
                </div>

                <!-- Money Movements -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-8">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-exchange-alt text-purple-500 mr-2"></i>
                            Money Movements
                        </h3>
                    </div>
                    <div class="p-6">
                        @if($moneyMovements->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">From Account</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">To Account</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($moneyMovements as $movement)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {{ $movement->created_at->inOperationalTimezone()->format('M d, Y H:i') }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    @if($movement->fromAccount)
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                            {{ $movement->fromAccount->name }}
                                                        </span>
                                                    @else
                                                        <span class="text-gray-500">Unknown</span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    @if($movement->toAccount)
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                            {{ $movement->toAccount->name }}
                                                        </span>
                                                    @else
                                                        <span class="text-gray-500">Unknown</span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-[#011478]">
                                                    {{ number_format($movement->amount, 0) }} UGX
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-500">
                                                    {{ $movement->description }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    @if($movement->status === 'completed')
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Completed</span>
                                                    @elseif($movement->status === 'pending')
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Pending</span>
                                                    @else
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">{{ ucfirst($movement->status) }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="flex justify-center mt-6">
                                {{ $moneyMovements->links() }}
                            </div>
                        @else
                            <div class="text-center py-12">
                                <i class="fas fa-exchange-alt text-6xl text-gray-300 mb-4"></i>
                                <h5 class="text-gray-500 text-lg font-medium">No money movements found</h5>
                                <p class="text-gray-400">This account has no recorded money movements yet.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Balance History -->
                @if($balanceHistory->count() > 0)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-chart-line text-indigo-500 mr-2"></i>
                                Recent Balance History
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction Type</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance After</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($balanceHistory as $history)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {{ $history->created_at->inOperationalTimezone()->format('M d, Y H:i') }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    @if(isset($history->transaction_type))
                                                        {{-- BalanceHistory (client-level) --}}
                                                        @if($history->transaction_type === 'credit')
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Credit</span>
                                                        @else
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Debit</span>
                                                        @endif
                                                    @else
                                                        {{-- BusinessBalanceHistory (business-level) --}}
                                                        @if($history->type === 'credit')
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Credit</span>
                                                        @elseif($history->type === 'package')
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Package</span>
                                                        @else
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Debit</span>
                                                        @endif
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold {{ (isset($history->transaction_type) && $history->transaction_type === 'credit') || (isset($history->type) && $history->type === 'credit') ? 'text-green-600' : 'text-red-600' }}">
                                                    @if(isset($history->transaction_type))
                                                        {{-- BalanceHistory (client-level) --}}
                                                        {{ $history->transaction_type === 'credit' ? '+' : '-' }}{{ number_format($history->amount, 0) }} UGX
                                                    @else
                                                        {{-- BusinessBalanceHistory (business-level) --}}
                                                        {{ $history->type === 'credit' ? '+' : '-' }}{{ number_format($history->amount, 0) }} UGX
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                                    @if(isset($history->balance_after))
                                                        {{-- BalanceHistory (client-level) --}}
                                                        {{ number_format($history->balance_after, 0) }} UGX
                                                    @else
                                                        {{-- BusinessBalanceHistory (business-level) --}}
                                                        {{ number_format($history->new_balance, 0) }} UGX
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-500">
                                                    {{ $history->description }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
