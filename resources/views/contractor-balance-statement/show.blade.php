<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $contractorProfile->user->name ?? 'Contractor' }} - Contractor Account Statement
            </h2>
            <div class="flex items-center space-x-3">
                <a href="{{ route('contractor-withdrawal-requests.create', $contractorProfile) }}"
                   class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    Request New Withdrawal
                </a>
                <a href="{{ route('contractor-balance-statement.index') }}" 
                   class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Back to All Contractors
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <!-- Header -->
                    <div class="mb-8">
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">Contractor Account Statement</h1>
                        <p class="text-gray-600">Detailed financial overview for {{ $contractorProfile->user->name ?? 'Contractor' }}</p>
                    </div>

                    <!-- Summary Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <!-- Available Balance -->
                        <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-gray-900">Available Balance</h3>
                                    <p class="text-2xl font-bold text-blue-600">
                                        UGX {{ number_format($availableBalance, 2) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Total Balance -->
                        <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-green-100 text-green-600">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-gray-900">Total Balance</h3>
                                    <p class="text-2xl font-bold text-green-600">
                                        UGX {{ number_format($availableBalance, 2) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Total Debits -->
                        <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-red-100 text-red-600">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-gray-900">Total Debits</h3>
                                    <p class="text-2xl font-bold text-red-600">
                                        UGX {{ number_format($totalDebits, 2) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Transaction History -->
                    <div class="bg-white rounded-lg shadow-md border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-xl font-semibold text-gray-900">Transaction History</h2>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($contractorBalanceHistories as $history)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $history->created_at->inOperationalTimezone()->format('M d, Y H:i') }}
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
                                            <td class="px-6 py-4 text-sm text-gray-900">
                                                <div class="max-w-xs">
                                                    @php
                                                        $description = $history->description;
                                                        $referenceNumber = null;
                                                        
                                                        // Handle Contractor Withdrawal entries
                                                        if (str_contains($description, 'Contractor Withdrawal') || $history->reference_type === 'contractor_withdrawal') {
                                                            $description = 'Contractor Withdrawal';
                                                            // Get UUID from metadata or description
                                                            if ($history->metadata && isset($history->metadata['contractor_uuid'])) {
                                                                $referenceNumber = $history->metadata['contractor_uuid'];
                                                            } elseif (preg_match('/Contractor Withdrawal - ([a-f0-9-]+)/i', $history->description, $matches)) {
                                                                $referenceNumber = $matches[1];
                                                            }
                                                        } elseif (str_contains($description, 'Payment received via mobile_money')) {
                                                            $description = 'Mobile Money';
                                                        } elseif (str_contains($description, 'Payment received for invoice')) {
                                                            $description = 'Invoice';
                                                        } elseif (str_contains($description, 'Payment received via')) {
                                                            $description = str_replace('Payment received via ', '', $description);
                                                            $description = ucwords(str_replace('_', ' ', $description));
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
                                                        
                                                        // If no reference number found yet, try to get from metadata
                                                        if (!$referenceNumber && $history->metadata) {
                                                            if (isset($history->metadata['contractor_uuid'])) {
                                                                $referenceNumber = $history->metadata['contractor_uuid'];
                                                            } elseif (isset($history->metadata['invoice_number'])) {
                                                                $referenceNumber = $history->metadata['invoice_number'];
                                                            }
                                                        }
                                                    @endphp
                                                    {{ $description }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                @if($history->type === 'credit')
                                                    <span class="text-green-600">+{{ number_format($history->amount, 2) }} UGX</span>
                                                @elseif($history->type === 'package')
                                                    <span class="text-blue-600">+{{ number_format($history->amount, 2) }} UGX</span>
                                                @else
                                                    <span class="text-red-600">-{{ number_format($history->amount, 2) }} UGX</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $referenceNumber ?? ($history->reference_number ?? 'N/A') }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                                No transactions found for this contractor.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if($contractorBalanceHistories->hasPages())
                            <div class="px-6 py-4 border-t border-gray-200">
                                {{ $contractorBalanceHistories->links() }}
                            </div>
                        @endif
                    </div>

                    <!-- Quick Actions Section -->
                    <div class="mt-8 bg-gray-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Withdrawal Requests Summary -->
                            <div class="bg-white rounded-lg p-4 border border-gray-200">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-md font-medium text-gray-900">Withdrawal Requests</h4>
                                    @if($pendingWithdrawalCount > 0)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            {{ $pendingWithdrawalCount }} Pending
                                        </span>
                                    @endif
                                </div>
                                
                                @if($recentWithdrawalRequests->count() > 0)
                                    <div class="space-y-2">
                                        @foreach($recentWithdrawalRequests as $request)
                                            <div class="flex items-center justify-between text-sm">
                                                <div>
                                                    <span class="font-medium">UGX {{ number_format($request->amount, 2) }}</span>
                                                    <span class="text-gray-500 ml-2">{{ $request->created_at->inOperationalTimezone()->format('M d, Y') }}</span>
                                                </div>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                    @if($request->status === 'completed') bg-green-100 text-green-800
                                                    @elseif($request->status === 'rejected') bg-red-100 text-red-800
                                                    @elseif(in_array($request->status, ['pending', 'business_approved', 'kashtre_approved', 'approved', 'processing'])) bg-yellow-100 text-yellow-800
                                                    @else bg-gray-100 text-gray-800
                                                    @endif">
                                                    {{ $request->getStatusLabel() }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                    
                                    <div class="mt-3 pt-3 border-t border-gray-200">
                                        <a href="{{ route('contractor-withdrawal-requests.index', $contractorProfile) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                            View All Withdrawal Requests →
                                        </a>
                                    </div>
                                @else
                                    <p class="text-gray-500 text-sm">No withdrawal requests yet</p>
                                @endif
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="bg-white rounded-lg p-4 border border-gray-200">
                                <h4 class="text-md font-medium text-gray-900 mb-3">Actions</h4>
                                
                                <div class="space-y-3">
                                    @if(($availableBalance ?? 0) > 0)
                                        <a href="{{ route('contractor-withdrawal-requests.create', $contractorProfile) }}" 
                                           class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors text-center block">
                                            Request New Withdrawal
                                        </a>
                                    @else
                                        <div class="w-full bg-gray-300 text-gray-500 px-4 py-2 rounded-lg text-center">
                                            No Balance Available
                                        </div>
                                    @endif
                                    
                                    <a href="{{ route('dashboard') }}" 
                                       class="w-full bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors text-center block">
                                        Back to Dashboard
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

