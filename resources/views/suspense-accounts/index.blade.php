<x-app-layout>
    <div class="py-12 bg-gradient-to-b from-[#011478]/10 to-transparent">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Page Header -->
            <div class="mb-8 flex justify-between items-center bg-white/50 backdrop-blur-sm p-6 rounded-xl shadow-sm">
                <div>
                    <h2 class="text-3xl font-bold text-[#011478]">Suspense Accounts Dashboard</h2>
                    <p class="text-gray-600 mt-2">Track and manage all suspense account balances and money movements</p>
                </div>
                <div class="flex items-center space-x-4">
                    <button onclick="location.reload()" class="bg-[#011478] hover:bg-[#011478]/90 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                        <i class="fas fa-sync-alt mr-2"></i>
                        Refresh
                    </button>
                </div>
            </div>


            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Package Suspense Card -->
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white/20 rounded-lg">
                                <i class="fas fa-box text-2xl"></i>
                            </div>
                        </div>
                        <h3 class="text-2xl font-bold mb-2">{{ number_format($totalPackageSuspense, 0) }} UGX</h3>
                        <p class="text-blue-100 font-medium">Package Suspense</p>
                        <p class="text-blue-200 text-sm mt-2">Funds for paid package items</p>
                    </div>

                    <!-- General Suspense Card -->
                    <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 rounded-xl p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white/20 rounded-lg">
                                <i class="fas fa-clock text-2xl"></i>
                            </div>
                        </div>
                        <h3 class="text-2xl font-bold mb-2">{{ number_format($totalGeneralSuspense, 0) }} UGX</h3>
                        <p class="text-yellow-100 font-medium">General Suspense</p>
                        <p class="text-yellow-200 text-sm mt-2">Ordered items not yet offered</p>
                    </div>

                    <!-- Kashtre Suspense Card -->
                    <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white/20 rounded-lg">
                                <i class="fas fa-hand-holding-usd text-2xl"></i>
                            </div>
                        </div>
                        <h3 class="text-2xl font-bold mb-2">{{ number_format($totalKashtreSuspense, 0) }} UGX</h3>
                        <p class="text-green-100 font-medium">Kashtre Suspense</p>
                        <p class="text-green-200 text-sm mt-2">Service fees and deposits</p>
                    </div>

                    <!-- Withdrawal Suspense Card -->
                    <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-xl p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-white/20 rounded-lg">
                                <i class="fas fa-money-bill-wave text-2xl"></i>
                            </div>
                        </div>
                        <h3 class="text-2xl font-bold mb-2">{{ number_format($totalWithdrawalSuspense, 0) }} UGX</h3>
                        <p class="text-red-100 font-medium">Withdrawal Suspense</p>
                        <p class="text-red-200 text-sm mt-2">Held funds for pending withdrawals</p>
                    </div>
                </div>

            <!-- Total Suspense Balance Card -->
            <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl p-6 text-white mb-8">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-3 bg-white/20 rounded-lg">
                        <i class="fas fa-calculator text-2xl"></i>
                    </div>
                </div>
                <h3 class="text-2xl font-bold mb-2">{{ number_format($totalSuspenseBalance, 0) }} UGX</h3>
                <p class="text-purple-100 font-medium">Total Suspense Balance</p>
                <p class="text-purple-200 text-sm mt-2">Sum of all suspense accounts</p>
            </div>

            <!-- Livewire Table Component -->
            <livewire:suspense-accounts-table />

            <!-- Recent Money Movements -->
            @if($recentMovements->count() > 0)
                <div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-sm p-6 mt-8">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-history text-gray-500 mr-2"></i>
                            Recent Money Movements
                        </h3>
                        <p class="text-gray-600 mt-2">Latest money transfers across all accounts</p>
                    </div>
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transfer ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">From Account</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">To Account</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source / Destination</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($recentMovements as $movement)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">#{{ $movement->id }}</div>
                                                <div class="text-sm text-gray-500">{{ $movement->type ?? 'Transfer' }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">{{ $movement->fromAccount->name ?? 'External' }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">{{ $movement->toAccount->name ?? 'Unknown' }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    {{ $movement->resolvedInsuranceSourceDestinationLabel() }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">{{ number_format($movement->amount, 0) }} UGX</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">{{ $movement->created_at->format('M d, Y H:i') }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                    Completed
                                                </span>
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
</x-app-layout>
