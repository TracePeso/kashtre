<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Money Tracking Dashboard') }}
            @if($isSuperBusiness)
                <span class="text-sm font-normal text-blue-600">(Super Business View)</span>
            @endif
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if($isSuperBusiness && $businessSummary)
            <!-- Business Summary for Super Business -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-8">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Business Summary</h3>
                    <p class="text-sm text-gray-600 mt-1">Overview of all businesses in the system</p>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($businessSummary as $summary)
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-sm font-medium text-gray-900">{{ $summary['business']->name }}</h4>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Business
                                </span>
                            </div>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Client Balance:</span>
                                    <span class="font-medium">{{ number_format($summary['total_client_balance'], 2) }} UGX</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Contractor Balance:</span>
                                    <span class="font-medium">{{ number_format($summary['total_contractor_balance'], 2) }} UGX</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Suspense Balance:</span>
                                    <span class="font-medium">{{ number_format($summary['total_suspense_balance'], 2) }} UGX</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Business Balance:</span>
                                    <span class="font-medium">{{ number_format($summary['total_business_balance'], 2) }} UGX</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Total Transfers:</span>
                                    <span class="font-medium">{{ $summary['total_transfers'] }}</span>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <!-- Account Balances Overview -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-8">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">
                        @if($isSuperBusiness)
                            Kashtre Account Balances
                        @else
                            Account Balances
                        @endif
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">Current balances across all account types</p>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($accountBalances as $type => $account)
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900">{{ $account['name'] }}</h4>
                                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $account['formatted_balance'] }}</p>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ ucfirst(str_replace('_', ' ', $type)) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Recent Money Transfers -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-8">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Recent Money Transfers</h3>
                    <p class="text-sm text-gray-600 mt-1">Latest money movements between accounts</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                @if($isSuperBusiness)
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business</th>
                                @endif
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">From</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">To</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($recentTransfers as $transfer)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $transfer->created_at->inOperationalTimezone()->format('M d, Y H:i') }}
                                </td>
                                @if($isSuperBusiness)
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $transfer->business->name ?? 'Unknown' }}
                                </td>
                                @endif
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        @if($transfer->transfer_type === 'payment_received') bg-green-100 text-green-800
                                        @elseif($transfer->transfer_type === 'order_confirmed') bg-blue-100 text-blue-800
                                        @elseif($transfer->transfer_type === 'service_delivered') bg-purple-100 text-purple-800
                                        @elseif($transfer->transfer_type === 'refund_approved') bg-red-100 text-red-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ ucwords(str_replace('_', ' ', $transfer->transfer_type)) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $transfer->fromAccount ? $transfer->fromAccount->name : 'External' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $transfer->toAccount ? $transfer->toAccount->name : 'External' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $transfer->formatted_amount }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        @if($transfer->status === 'completed') bg-green-100 text-green-800
                                        @elseif($transfer->status === 'pending') bg-yellow-100 text-yellow-800
                                        @elseif($transfer->status === 'failed') bg-red-100 text-red-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ ucfirst($transfer->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    {{ Str::limit($transfer->description, 50) }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ $isSuperBusiness ? '8' : '7' }}" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No recent transfers found
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Client Accounts -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-8">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Client Accounts</h3>
                    <p class="text-sm text-gray-600 mt-1">Top client accounts by balance</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                @if($isSuperBusiness)
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business</th>
                                @endif
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Account Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($clientAccounts as $account)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $account->client->name ?? 'Unknown Client' }}
                                        </div>
                                    </div>
                                </td>
                                @if($isSuperBusiness)
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $account->business->name ?? 'Unknown' }}
                                </td>
                                @endif
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $account->name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $account->formatted_balance }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    @if($account->client)
                                    <a href="{{ route('money-tracking.client-account', $account->client) }}" 
                                       class="text-blue-600 hover:text-blue-900">View Details</a>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ $isSuperBusiness ? '5' : '4' }}" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No client accounts found
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Contractor Accounts -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Contractor Accounts</h3>
                    <p class="text-sm text-gray-600 mt-1">Top contractor accounts by balance</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contractor</th>
                                @if($isSuperBusiness)
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business</th>
                                @endif
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Account Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($contractorAccounts as $account)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $account->contractorProfile->user->name ?? 'Unknown Contractor' }}
                                        </div>
                                    </div>
                                </td>
                                @if($isSuperBusiness)
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $account->business->name ?? 'Unknown' }}
                                </td>
                                @endif
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $account->name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $account->formatted_balance }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ route('money-tracking.contractor-account', $account->contractorProfile) }}" 
                                       class="text-blue-600 hover:text-blue-900">View Details</a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ $isSuperBusiness ? '5' : '4' }}" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No contractor accounts found
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Auto-refresh data every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
    @endpush
</x-app-layout>
