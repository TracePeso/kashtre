<x-app-layout>
    <div class="min-h-screen bg-gray-50 py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="md:flex md:items-center md:justify-between">
                <div class="flex-1 min-w-0">
                    <nav class="flex" aria-label="Breadcrumb">
                        <ol class="flex items-center space-x-4">
                            <li>
                                <div>
                                    <a href="{{ route('maturation-periods.index', ['tab' => 'entities']) }}" class="text-gray-400 hover:text-gray-500">
                                        <svg class="flex-shrink-0 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                                        </svg>
                                        <span class="sr-only">Maturation Periods</span>
                                    </a>
                                </div>
                            </li>
                            <li>
                                <div class="flex items-center">
                                    <svg class="flex-shrink-0 h-5 w-5 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <a href="{{ route('payment-method-accounts.transactions', $paymentMethodAccount) }}" class="ml-4 text-sm font-medium text-gray-500 hover:text-gray-700">Account Transactions</a>
                                </div>
                            </li>
                            <li>
                                <div class="flex items-center">
                                    <svg class="flex-shrink-0 h-5 w-5 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="ml-4 text-sm font-medium text-gray-500">Transaction Details</span>
                                </div>
                            </li>
                        </ol>
                    </nav>
                    <h2 class="mt-2 text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                        Transaction Details
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ $transaction->reference }}
                    </p>
                </div>
                <div class="mt-4 flex md:mt-0 md:ml-4">
                    <a href="{{ route('payment-method-accounts.transactions', $paymentMethodAccount) }}" 
                       class="ml-3 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back to Transactions
                    </a>
                </div>
            </div>

            <!-- Transaction Information -->
            <div class="mt-8">
                <div class="bg-white shadow sm:rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Transaction Information</h3>
                        <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Transaction ID</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $transaction->uuid ?? $transaction->id }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Reference</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $transaction->reference }}</dd>
                            </div>
                            @if($transaction->external_reference)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">External Reference</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $transaction->external_reference }}</dd>
                            </div>
                            @endif
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Type</dt>
                                <dd class="mt-1">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $transaction->type === 'credit' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ ucfirst($transaction->type) }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Amount</dt>
                                <dd class="mt-1 text-lg font-semibold {{ $transaction->type === 'credit' ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $transaction->type === 'credit' ? '+' : '-' }}{{ number_format($transaction->amount, 2) }} {{ $transaction->currency ?? 'UGX' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Status</dt>
                                <dd class="mt-1">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        @if($transaction->status === 'completed') bg-green-100 text-green-800
                                        @elseif($transaction->status === 'pending') bg-yellow-100 text-yellow-800
                                        @elseif($transaction->status === 'failed') bg-red-100 text-red-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ ucfirst($transaction->status) }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Date</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $transaction->created_at->format('M d, Y H:i:s') }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Transaction For</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ ucfirst(str_replace('_', ' ', $transaction->transaction_for)) }}</dd>
                            </div>
                            @if($transaction->description)
                            <div class="sm:col-span-2">
                                <dt class="text-sm font-medium text-gray-500">Description</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $transaction->description }}</dd>
                            </div>
                            @endif
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Client Information -->
            @if($transaction->client)
            <div class="mt-8 bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Client Information</h3>
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Client Name</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="{{ route('clients.show', $transaction->client) }}" class="text-blue-600 hover:text-blue-800">
                                    {{ $transaction->client->full_name }}
                                </a>
                            </dd>
                        </div>
                        @if($transaction->client->phone_number)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Phone Number</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $transaction->client->phone_number }}</dd>
                        </div>
                        @endif
                        @if($transaction->client->email)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Email</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $transaction->client->email }}</dd>
                        </div>
                        @endif
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Client ID</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $transaction->client->id }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
            @endif

            <!-- Invoice Information -->
            @if($transaction->invoice)
            <div class="mt-8 bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Invoice Information</h3>
                        <a href="{{ route('invoices.show', $transaction->invoice) }}" 
                           class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                            View Full Invoice →
                        </a>
                    </div>
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Invoice Number</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-mono">
                                <a href="{{ route('invoices.show', $transaction->invoice) }}" class="text-blue-600 hover:text-blue-800">
                                    {{ $transaction->invoice->invoice_number }}
                                </a>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Invoice Status</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    @if($transaction->invoice->status === 'confirmed') bg-green-100 text-green-800
                                    @elseif($transaction->invoice->status === 'pending') bg-yellow-100 text-yellow-800
                                    @elseif($transaction->invoice->status === 'cancelled') bg-red-100 text-red-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ ucfirst($transaction->invoice->status) }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Total Amount</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-semibold">{{ number_format($transaction->invoice->total_amount, 2) }} {{ $transaction->invoice->currency ?? 'UGX' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Amount Paid</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-semibold">{{ number_format($transaction->invoice->amount_paid, 2) }} {{ $transaction->invoice->currency ?? 'UGX' }}</dd>
                        </div>
                        @if($transaction->invoice->service_charge)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Service Charge</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ number_format($transaction->invoice->service_charge, 2) }} {{ $transaction->invoice->currency ?? 'UGX' }}</dd>
                        </div>
                        @endif
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Invoice Date</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $transaction->invoice->created_at->format('M d, Y H:i:s') }}</dd>
                        </div>
                        @if($transaction->invoice->payment_methods)
                        <div class="sm:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">Payment Methods</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @foreach($transaction->invoice->payment_methods as $method)
                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-800 mr-2">
                                        {{ ucfirst(str_replace('_', ' ', $method)) }}
                                    </span>
                                @endforeach
                            </dd>
                        </div>
                        @endif
                    </dl>

                    <!-- Invoice Items Summary -->
                    @if($transaction->invoice->items && count($transaction->invoice->items) > 0)
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <h4 class="text-sm font-medium text-gray-900 mb-3">Invoice Items</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($transaction->invoice->items as $item)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $item['name'] ?? $item['displayName'] ?? $item['item_name'] ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $item['quantity'] ?? 1 }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ number_format($item['price'] ?? $item['unit_price'] ?? 0, 2) }} {{ $transaction->invoice->currency ?? 'UGX' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ number_format(($item['price'] ?? $item['unit_price'] ?? 0) * ($item['quantity'] ?? 1), 2) }} {{ $transaction->invoice->currency ?? 'UGX' }}
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Payment Method Account Information -->
            <div class="mt-8 bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Payment Method Account</h3>
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Account Name</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $paymentMethodAccount->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Payment Method</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $paymentMethodAccount->payment_method_name }}</dd>
                        </div>
                        @if($paymentMethodAccount->provider)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Provider</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $paymentMethodAccount->provider }}</dd>
                        </div>
                        @endif
                        @if($paymentMethodAccount->account_number)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Account Number</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $paymentMethodAccount->account_number }}</dd>
                        </div>
                        @endif
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Balance Before Transaction</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ number_format($transaction->balance_before ?? 0, 2) }} {{ $transaction->currency ?? 'UGX' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Balance After Transaction</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-semibold">{{ number_format($transaction->balance_after ?? 0, 2) }} {{ $transaction->currency ?? 'UGX' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Metadata -->
            @if($transaction->metadata)
            <div class="mt-8 bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Additional Information</h3>
                    <pre class="bg-gray-50 p-4 rounded-lg text-xs overflow-auto">{{ json_encode($transaction->metadata, JSON_PRETTY_PRINT) }}</pre>
                </div>
            </div>
            @endif

            <!-- Metadata -->
            <div class="mt-8 bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Metadata</h3>
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created At</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $transaction->created_at->format('M d, Y H:i:s') }}</dd>
                        </div>
                        @if($transaction->createdBy)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created By</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $transaction->createdBy->name }}</dd>
                        </div>
                        @endif
                        @if($transaction->updated_at)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Updated At</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $transaction->updated_at->format('M d, Y H:i:s') }}</dd>
                        </div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

