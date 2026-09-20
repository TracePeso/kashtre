<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Quotation Details') }} - {{ $quotation->quotation_number }}
            </h2>
            <div class="flex space-x-3">
                <a href="{{ route('quotations.index') }}" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 transition-colors">
                    Back to Quotations
                </a>
                <a href="{{ route('quotations.print', $quotation) }}" target="_blank" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">
                    Print Quotation
                </a>
                @if($quotation->status === 'draft')
                <button onclick="updateQuotationStatus('{{ $quotation->id }}', 'sent')" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors">
                    Mark as Sent
                </button>
                @endif
                @if($quotation->status === 'sent')
                <button onclick="updateQuotationStatus('{{ $quotation->id }}', 'accepted')" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors">
                    Mark as Accepted
                </button>
                <button onclick="updateQuotationStatus('{{ $quotation->id }}', 'rejected')" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition-colors">
                    Mark as Rejected
                </button>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Quotation Header -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Quotation Information -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Quotation Information</h3>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Quotation Number</dt>
                                    <dd class="text-lg font-semibold text-gray-900">{{ $quotation->quotation_number }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                                    <dd>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $quotation->status_badge }}">
                                            {{ ucfirst($quotation->status) }}
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Date Generated</dt>
                                    <dd class="text-sm text-gray-900">{{ $quotation->generated_at ? $quotation->generated_at->format('M d, Y H:i') : $quotation->created_at->inOperationalTimezone()->format('M d, Y H:i') }}</dd>
                                </div>
                                @if($quotation->valid_until)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Valid Until</dt>
                                    <dd class="text-sm text-gray-900">{{ $quotation->valid_until->format('M d, Y H:i') }}</dd>
                                </div>
                                @endif
                                @if($quotation->invoice)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Related Invoice</dt>
                                    <dd class="text-sm text-gray-900">
                                        <a href="{{ route('invoices.show', $quotation->invoice) }}" class="text-blue-600 hover:text-blue-800">
                                            {{ $quotation->invoice->invoice_number }}
                                        </a>
                                    </dd>
                                </div>
                                @endif
                            </dl>
                        </div>

                        <!-- Client Information -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Client Information</h3>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Client Name</dt>
                                    <dd class="text-sm text-gray-900">{{ $quotation->client_name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Contact Phone</dt>
                                    <dd class="text-sm text-gray-900">{{ $quotation->client_phone }}</dd>
                                </div>
                                @if($quotation->payment_phone)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Payment Phone</dt>
                                    <dd class="text-sm text-gray-900">{{ $quotation->payment_phone }}</dd>
                                </div>
                                @endif
                                @if($quotation->visit_id)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Visit ID</dt>
                                    <dd class="text-sm text-gray-900">{{ $quotation->visit_id }}</dd>
                                </div>
                                @endif
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Validity Warning -->
            @if($quotation->valid_until && $quotation->is_expired)
            <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Quotation Expired</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <p>This quotation expired on {{ $quotation->valid_until->format('M d, Y H:i') }}.</p>
                        </div>
                    </div>
                </div>
            </div>
            @elseif($quotation->valid_until && $quotation->days_until_expiry !== null && $quotation->days_until_expiry <= 7)
            <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Quotation Expiring Soon</h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <p>This quotation will expire in {{ $quotation->days_until_expiry }} days.</p>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Items Section -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Items</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @if($quotation->items && is_array($quotation->items))
                                    @foreach($quotation->items as $item)
                                    @php
                                        // Get the actual Item model to use display_name attribute
                                        $itemModel = \App\Models\Item::find($item['id'] ?? $item['item_id'] ?? null);
                                        $displayName = $itemModel ? $itemModel->name : ($item['name'] ?? 'N/A');
                                    @endphp
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $displayName }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $item['type'] ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            UGX {{ number_format($item['price'] ?? 0, 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $item['quantity'] ?? 0 }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            UGX {{ number_format(($item['price'] ?? 0) * ($item['quantity'] ?? 0), 2) }}
                                        </td>
                                    </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                            No items found
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Summary Section -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Summary</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Financial Summary -->
                        <div>
                            <dl class="space-y-3">
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">Subtotal</dt>
                                    <dd class="text-sm text-gray-900">UGX {{ number_format($quotation->subtotal, 2) }}</dd>
                                </div>
                                @if($quotation->package_adjustment != 0)
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">Package Adjustment</dt>
                                    <dd class="text-sm text-gray-900">UGX {{ number_format($quotation->package_adjustment, 2) }}</dd>
                                </div>
                                @endif
                                @if($quotation->account_balance_adjustment != 0)
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">Account Balance Adjustment</dt>
                                    <dd class="text-sm text-gray-900">UGX {{ number_format($quotation->account_balance_adjustment, 2) }}</dd>
                                </div>
                                @endif
                                @if($quotation->service_charge > 0)
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">Service Charge</dt>
                                    <dd class="text-sm text-gray-900">UGX {{ number_format($quotation->service_charge, 2) }}</dd>
                                </div>
                                @endif
                                <div class="flex justify-between border-t pt-3">
                                    <dt class="text-lg font-bold text-gray-900">Total Amount</dt>
                                    <dd class="text-lg font-bold text-gray-900">UGX {{ number_format($quotation->total_amount, 2) }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Business Information -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Business Information</h4>
                            <div class="space-y-2">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Business</dt>
                                    <dd class="text-sm text-gray-900">{{ $quotation->business->name ?? 'N/A' }}</dd>
                                </div>
                                @if($quotation->branch)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Branch</dt>
                                    <dd class="text-sm text-gray-900">{{ $quotation->branch->name ?? 'N/A' }}</dd>
                                </div>
                                @endif
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Created By</dt>
                                    <dd class="text-sm text-gray-900">{{ $quotation->createdBy->name ?? 'N/A' }}</dd>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes Section -->
            @if($quotation->notes)
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Notes</h3>
                    <p class="text-sm text-gray-900">{{ $quotation->notes }}</p>
                </div>
            </div>
            @endif
        </div>
    </div>

    <script>
        async function updateQuotationStatus(quotationId, status) {
            try {
                const response = await fetch(`/quotations/${quotationId}/status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ status: status })
                });

                const data = await response.json();

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Status Updated!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message || 'Failed to update status'
                    });
                }
            } catch (error) {
                console.error('Error updating quotation status:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred while updating the status'
                });
            }
        }
    </script>
</x-app-layout>

