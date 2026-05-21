<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $business->name }} - Balance Statement
            </h2>
                          <a href="{{ route('business-balance-statement.index') }}"  
               class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                Back to All Businesses
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <div class="mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">
                                Balance Statement for {{ $business->name }}
                            </h3>
                            <div class="text-right">
                                <p class="text-sm text-gray-600">Current Balance</p>
                                <p class="text-lg font-semibold text-gray-900">
                                    {{ number_format($business->account_balance, 0) }} UGX
                                </p>
                            </div>
                        </div>
                    </div>

                    @if($businessBalanceHistories->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Date
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Type
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Description
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Amount
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Reference
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Payment Status
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Days to mature
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Payment Method
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($businessBalanceHistories as $history)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $history->created_at->format('M d, Y H:i') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    @if($history->type === 'credit') bg-green-100 text-green-800
                                                    @elseif($history->type === 'package') bg-blue-100 text-blue-800
                                                    @elseif($history->type === 'debit') bg-red-100 text-red-800
                                                    @else bg-gray-100 text-gray-800 @endif">
                                                    {{ ucfirst($history->type) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-900">
                                                @php
                                                    $description = $history->description;
                                                    
                                                    // Simplify descriptions for statements
                                                    if (str_contains($description, 'Payment received via mobile_money')) {
                                                        $description = 'Mobile Money Payment';
                                                    } elseif (str_contains($description, 'Payment received for invoice')) {
                                                        $description = 'Invoice Payment';
                                                    } elseif (str_contains($description, 'Payment received via')) {
                                                        $description = str_replace('Payment received via ', '', $description);
                                                        $description = ucwords(str_replace('_', ' ', $description)) . ' Payment';
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
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <span class="@if($history->type === 'credit') text-green-600 @elseif($history->type === 'package') text-blue-600 @else text-red-600 @endif font-semibold">
                                                    @if($history->type === 'package')
                                                        {{-- Package entries: no + or - prefix, just amount --}}
                                                        {{ number_format($history->amount, 0) }} UGX
                                                    @elseif($history->type === 'credit')
                                                        {{-- Credit entries: show + prefix --}}
                                                        +{{ number_format($history->amount, 0) }} UGX
                                                    @else
                                                        {{-- Debit entries: no negative sign, just amount in red --}}
                                                        {{ number_format($history->amount, 0) }} UGX
                                                    @endif
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $history->reference_number ?? '-' }}
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
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-6">
                            {{ $businessBalanceHistories->links() }}
                        </div>
                    @else
                        <div class="text-center py-12">
                            <div class="text-gray-500">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No balance statement</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    No balance statement records found for {{ $business->name }}.
                                </p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

