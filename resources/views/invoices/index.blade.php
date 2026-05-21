<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Invoices') }}
            </h2>
            <div class="flex space-x-3">
                <a href="/clients" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">
                    Create New Invoice
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Search and Filters -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search Invoices</label>
                            <input type="text" id="search" placeholder="Invoice number, client name..." 
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="status_filter" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select id="status_filter" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">All Statuses</option>
                                <option value="draft">Draft</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="printed">Printed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label for="payment_status_filter" class="block text-sm font-medium text-gray-700 mb-1">Payment Status</label>
                            <select id="payment_status_filter" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">All Payment Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="partial">Partial</option>
                                <option value="paid">Paid</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label for="date_filter" class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                            <select id="date_filter" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">All Dates</option>
                                <option value="today">Today</option>
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                                <option value="year">This Year</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Invoices List -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Invoice Number
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Client
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Total Amount
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Balance Due
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Payment Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($invoices as $invoice)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-blue-600">
                                        <a href="{{ route('invoices.show', $invoice) }}" class="hover:text-blue-800">
                                            {{ $invoice->invoice_number }}
                                        </a>
                                        <span class="ml-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500">Mother</span>
                                    </div>
                                    @if($invoice->vendorPortionInvoices->isNotEmpty())
                                        <div class="mt-1.5 flex flex-wrap gap-1.5">
                                            @foreach($invoice->vendorPortionInvoices as $portion)
                                                <a href="{{ route('invoices.show', $portion) }}"
                                                   class="inline-flex items-center gap-1 rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[10px] font-mono text-slate-700 hover:bg-slate-100"
                                                   title="Insurer follow-up copy {{ $portion->vendor_portion_label }}">
                                                    <span class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-slate-200 text-[9px] font-bold">{{ $portion->vendor_portion_label }}</span>
                                                    {{ $portion->invoice_number }}
                                                    <span class="text-slate-500">UGX {{ number_format($portion->total_amount, 0) }}</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $invoice->client_name }}</div>
                                    <div class="text-sm text-gray-500">{{ $invoice->client_phone }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    UGX {{ number_format($invoice->total_amount, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm {{ $invoice->balance_due > 0 ? 'text-red-600 font-medium' : 'text-green-600' }}">
                                    UGX {{ number_format($invoice->balance_due, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $invoice->status_badge }}">
                                        {{ ucfirst($invoice->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $invoice->payment_status_badge }}">
                                        {{ $invoice->payment_status === 'pending_payment' ? 'Pending Payment' : ucfirst($invoice->payment_status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $invoice->created_at->format('M d, Y') }}
                                    <div class="text-xs text-gray-400">{{ $invoice->created_at->format('H:i') }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <a href="{{ route('invoices.show', $invoice) }}" 
                                       class="text-blue-600 hover:text-blue-900">View</a>
                                    <a href="{{ route('invoices.print', $invoice) }}" target="_blank"
                                       class="text-green-600 hover:text-green-900">Print</a>
                                    @if($invoice->status !== 'cancelled')
                                    <button onclick="cancelInvoice({{ $invoice->id }})"
                                            class="text-red-600 hover:text-red-900">Cancel</button>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center">
                                    <div class="text-gray-500">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <h3 class="mt-2 text-sm font-medium text-gray-900">No invoices found</h3>
                                        <p class="mt-1 text-sm text-gray-500">Get started by creating your first invoice.</p>
                                        <div class="mt-6">
                                            <a href="/clients" 
                                               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                Create New Invoice
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($invoices->hasPages())
                <div class="px-6 py-3 border-t border-gray-200">
                    {{ $invoices->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>

    @if(!empty($invoices) && $invoices->count() > 0)
    <script>
        async function cancelInvoice(invoiceId) {
            const result = await Swal.fire({
                title: 'Cancel Invoice',
                text: 'Are you sure you want to cancel this invoice? This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, cancel it!',
                cancelButtonText: 'No, keep it'
            });

            if (result.isConfirmed) {
                try {
                    const response = await fetch(`/invoices/${invoiceId}/cancel`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                        }
                    });

                    const data = await response.json();

                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Cancelled!',
                            text: 'Invoice has been cancelled successfully.',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        
                        // Reload the page to reflect changes
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: data.message || 'Failed to cancel invoice'
                        });
                    }
                } catch (error) {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'An error occurred while cancelling the invoice'
                    });
                }
            }
        }

        // Optional: Add filtering functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            const statusFilter = document.getElementById('status_filter');
            const paymentStatusFilter = document.getElementById('payment_status_filter');
            const dateFilter = document.getElementById('date_filter');

            // You can implement client-side filtering here or make AJAX calls to filter server-side
        });
    </script>
    @endif
</x-app-layout>
