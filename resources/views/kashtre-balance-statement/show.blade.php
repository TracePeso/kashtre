<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Kashtre Detailed Statement') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Kashtre Detailed Statement</h1>
                    <p class="text-gray-600">Complete Transaction History</p>
                </div>
                <a href="{{ route('kashtre-balance-statement.index') }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors">
                    Back to Summary
                </a>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <!-- Available Balance -->
            <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                <div class="text-center">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Available Balance</h3>
                    <p class="text-3xl font-bold text-blue-600">
                        UGX {{ number_format(($totalCredits ?? 0) - ($totalDebits ?? 0), 2) }}
                    </p>
                </div>
            </div>

            <!-- Total Balance -->
            <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                <div class="text-center">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Total Balance</h3>
                    <p class="text-3xl font-bold text-green-600">
                        UGX {{ number_format(($totalCredits ?? 0) + ($pendingPayments ?? 0) - ($totalDebits ?? 0), 2) }}
                    </p>
                </div>
            </div>

            <!-- Total Debits -->
            <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                <div class="text-center">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Total Debits</h3>
                    <p class="text-3xl font-bold text-red-600">
                        UGX {{ number_format($totalDebits ?? 0, 2) }}
                    </p>
                </div>
            </div>

            <!-- Pending Payments -->
            <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                <div class="text-center">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Pending Payments</h3>
                    <p class="text-3xl font-bold text-orange-600">
                        UGX {{ number_format($pendingPayments ?? 0, 2) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Full Transaction Table -->
        <div class="bg-white rounded-lg shadow-md border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">All Transactions</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($kashtreBalanceHistories as $history)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <div>
                                        <div class="font-medium">{{ $history->created_at->format('M d, Y') }}</div>
                                        <div class="text-gray-500">{{ $history->created_at->format('H:i:s') }}</div>
                                    </div>
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
                                            
                                            // Simplify descriptions for statements
                                            if (str_contains($description, 'Payment received via mobile_money')) {
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
                                        <span class="text-red-600">{{ number_format($history->amount, 2) }} UGX</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($history->reference_type && $history->reference_id)
                                        @if($history->reference_type === 'invoice')
                                            @php
                                                $invoice = \App\Models\Invoice::find($history->reference_id);
                                            @endphp
                                            @if($invoice)
                                                <a href="{{ route('invoices.show', $invoice->id) }}" class="text-blue-600 hover:text-blue-800">
                                                    {{ $invoice->invoice_number }}
                                                </a>
                                            @else
                                                Invoice #{{ $history->reference_id }}
                                            @endif
                                        @else
                                            {{ ucfirst($history->reference_type) }} #{{ $history->reference_id }}
                                        @endif
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $history->kashtreStatementPaymentStatusBadgeClass() }}">
                                        {{ $history->kashtreStatementPaymentStatusDisplay() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $history->kashtreStatementPaymentMethodLabel() }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                    No transactions found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($kashtreBalanceHistories->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $kashtreBalanceHistories->links() }}
                </div>
            @endif
        </div>

        <!-- Export Options -->
        <div class="mt-8 bg-white rounded-lg shadow-md border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Export Options</h3>
            <div class="flex space-x-4">
                <button onclick="exportToCSV()" 
                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors">
                    Export to CSV
                </button>
                <button onclick="exportToPDF()" 
                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-colors">
                    Export to PDF
                </button>
            </div>
        </div>

        <!-- Navigation -->
        <div class="mt-8 flex justify-between">
            <a href="{{ route('dashboard') }}" 
               class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-colors">
                Back to Dashboard
            </a>
            
            <a href="{{ route('kashtre-balance-statement.index') }}" 
               class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors">
                Back to Summary
            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function exportToCSV() {
        // Implementation for CSV export
        alert('CSV export functionality will be implemented here');
    }

    function exportToPDF() {
        // Implementation for PDF export
        alert('PDF export functionality will be implemented here');
    }
    </script>
</x-app-layout>
