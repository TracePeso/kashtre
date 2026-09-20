<x-cashier-layout>
    <div class="py-12 bg-gradient-to-b from-[#011478]/10 to-transparent">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Welcome Section -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-xl shadow-sm p-6 text-white mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold">Welcome, {{ $user->name }}!</h2>
                        <p class="text-blue-100 mt-1">Cashier Dashboard</p>
                    </div>
                    <div class="text-right">
                        <p class="text-blue-100 text-sm">Last Update</p>
                        <p class="text-white font-semibold">{{ now()->format('H:i:s') }}</p>
                    </div>
                </div>
            </div>

            <!-- Overview Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                
                <!-- Today's Sales Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Today's Sales</h3>
                        <span class="text-xs text-gray-400">{{ today()->format('M d, Y') }}</span>
                    </div>
                    <div class="flex items-baseline">
                        <span class="text-2xl font-bold text-green-600 dark:text-green-400">
                            {{ number_format($todaySalesTotal, 2) }}
                        </span>
                        <span class="ml-2 text-sm text-gray-500">UGX</span>
                    </div>
                </div>

                <!-- Today's Transactions Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Today's Transactions</h3>
                        <span class="text-xs text-gray-400">Count</span>
                    </div>
                    <div class="flex items-baseline">
                        <span class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                            {{ $todayTransactionCount }}
                        </span>
                        <span class="ml-2 text-sm text-gray-500">transactions</span>
                    </div>
                </div>

                <!-- Balance Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Current Balance</h3>
                        <span class="text-xs text-gray-400">Available</span>
                    </div>
                    <div class="flex items-baseline">
                        <span class="text-2xl font-bold {{ ($user->current_balance ?? 0) < 0 ? 'text-red-600' : 'text-gray-900' }} dark:{{ ($user->current_balance ?? 0) < 0 ? 'text-red-400' : 'text-white' }}">
                            {{ number_format(abs($user->current_balance ?? 0), 2) }}
                        </span>
                        <span class="ml-2 text-sm text-gray-500">UGX</span>
                    </div>
                </div>

            </div>

            <!-- Recent Invoices -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Invoices</h3>
                </div>
                
                @if($recentInvoices->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Client</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Invoice Number</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($recentInvoices as $invoice)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $invoice->created_at->inOperationalTimezone()->format('M d, Y H:i') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $invoice->client->name ?? $invoice->client_name ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $invoice->invoice_number ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">
                                    {{ number_format($invoice->total_amount ?? 0, 2) }} UGX
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-8">
                    <p class="text-gray-500 dark:text-gray-400">No invoices found.</p>
                </div>
                @endif
            </div>

        </div>
    </div>
</x-cashier-layout>

