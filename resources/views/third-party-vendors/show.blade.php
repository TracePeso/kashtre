<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Third Party Vendor Details') }} - {{ $vendor['name'] }}
            </h2>
            <a href="{{ route('third-party-vendors.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                Back to List
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Vendor Information & Financial Summary -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Vendor Information</h3>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Vendor Name</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $vendor['name'] }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Code</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <code class="bg-gray-100 px-2 py-1 rounded">{{ $vendor['code'] }}</code>
                                    </dd>
                                </div>
                                @if($vendor['email'])
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Email</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $vendor['email'] }}</dd>
                                </div>
                                @endif
                                @if($vendor['phone'])
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Phone</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $vendor['phone'] }}</dd>
                                </div>
                                @endif
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                                    <dd class="mt-1">
                                        @if($thirdPartyPayer)
                                            @if($thirdPartyPayer->isActive())
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    ✓ Active
                                                </span>
                                            @elseif($thirdPartyPayer->isSuspended())
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                    ⊘ Suspended
                                                </span>
                                            @elseif($thirdPartyPayer->isBlocked())
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                    ✕ Blocked
                                                </span>
                                            @endif
                                        @elseif($vendor['is_active'])
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Active
                                            </span>
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                Inactive
                                            </span>
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Connected At</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ \Carbon\Carbon::parse($vendor['connected_at'])->format('M d, Y H:i') }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Financial Summary</h3>
                            @if($thirdPartyPayer)
                                <dl class="space-y-3 mb-4">
                                    @php
                                        $availableBalance = (float) ($balanceSummary['available_balance'] ?? 0);
                                        $totalBalance = (float) ($balanceSummary['total_balance'] ?? 0);
                                    @endphp
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Available balance</dt>
                                        <dd class="mt-1 text-lg font-semibold {{ $availableBalance < 0 ? 'text-red-600' : ($availableBalance > 0 ? 'text-green-600' : 'text-gray-900') }}">
                                            UGX {{ number_format($availableBalance, 2) }}
                                            @if($availableBalance < 0)
                                                <span class="text-xs text-red-500">(Amount owed)</span>
                                            @elseif($availableBalance > 0)
                                                <span class="text-xs text-green-500">(Credit available)</span>
                                            @endif
                                        </dd>
                                        <p class="text-xs text-gray-500 mt-1">Total credits minus total debits</p>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Total balance</dt>
                                        <dd class="mt-1 text-lg font-semibold text-gray-900">
                                            UGX {{ number_format($totalBalance, 2) }}
                                        </dd>
                                        <p class="text-xs text-gray-500 mt-1">Available balance plus suspense (no separate suspense wallet for vendor payers)</p>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Credit Limit</dt>
                                        <dd class="mt-1 text-lg font-semibold text-gray-900">
                                            UGX {{ number_format((($thirdPartyPayer->credit_limit ?? 0) > 0
                                                ? (float) $thirdPartyPayer->credit_limit
                                                : (float) ($business->max_third_party_credit_limit ?? 0)), 2) }}
                                        </dd>
                                        @if(in_array('Manage Credit Limits', (array) (auth()->user()->permissions ?? [])))
                                            <a
                                                href="{{ route('credit-limit-requests.create', ['entity_type' => 'third_party_payer', 'entity_id' => $thirdPartyPayer->id]) }}"
                                                class="mt-2 inline-flex items-center px-3 py-1.5 border border-blue-600 text-xs font-medium rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100"
                                            >
                                                Request credit limit change
                                            </a>
                                        @endif
                                    </div>
                                </dl>
                                <div class="mt-2">
                                    <a href="{{ route('third-party-vendors.balance-statement', ['vendorId' => $vendor['id'], 'view' => 'items']) }}"
                                       class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Open financial summary
                                    </a>
                                </div>
                            @else
                                <dl class="space-y-3">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                                        <dd class="mt-1 text-sm text-gray-500">
                                            No third-party payer account found for this vendor. Balance history will appear here once invoices are created with this vendor.
                                        </dd>
                                    </div>
                                </dl>
                                <form action="{{ route('third-party-vendors.create-payer', $vendor['id']) }}" method="POST" class="mt-4">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                                        + Create Payer Account
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs: Items + Invoices -->
            @if($thirdPartyPayer)
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex" aria-label="Tabs">
                        <button type="button" onclick="showTab('items')" id="tab-items" class="tab-button active w-1/2 py-4 px-6 text-center border-b-2 font-medium text-sm transition-colors">
                            <span class="border-b-2 border-blue-500 pb-4 px-1 text-blue-600">Items</span>
                        </button>
                        <button type="button" onclick="showTab('invoices')" id="tab-invoices" class="tab-button w-1/2 py-4 px-6 text-center border-b-2 font-medium text-sm transition-colors">
                            <span class="border-b-2 border-transparent pb-4 px-1 text-gray-500 hover:text-gray-700">Invoices</span>
                        </button>
                    </nav>
                </div>

                <!-- Items Tab (itemized / line-oriented ledger rows) -->
                <div id="content-items" class="tab-content p-6">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Recent activity by item</h3>
                            <p class="text-sm text-gray-500">Each debit is split across invoice line items by line totals when the invoice has item rows; credits stay one row each.</p>
                        </div>
                        <a href="{{ route('third-party-vendors.balance-statement', ['vendorId' => $vendor['id'], 'view' => 'items']) }}"
                           class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                            View full statement (by item)
                        </a>
                    </div>

                    @if($itemStatementRows->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item / line</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($itemStatementRows as $row)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $row['created_at']->format('Y-m-d H:i:s') }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <div class="font-medium">{{ $row['line_label'] }}</div>
                                        @if(!empty($row['detail_description']) && $row['detail_description'] !== $row['line_label'])
                                            <div class="text-xs text-gray-500 mt-0.5">{{ $row['detail_description'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        @if($row['client'] ?? null)
                                            <span class="font-medium">{{ $row['client']->name }}</span>
                                            @if($row['client']->client_id)
                                                <br><span class="text-xs text-gray-500">ID: {{ $row['client']->client_id }}</span>
                                            @endif
                                        @else
                                            <span class="text-gray-400">N/A</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        @if(!empty($row['invoice']))
                                            <a href="{{ route('invoices.show', $row['invoice']->id) }}" class="text-blue-600 hover:text-blue-800 font-medium">
                                                {{ $row['invoice']->invoice_number ?? 'N/A' }}
                                            </a>
                                        @else
                                            <span class="text-gray-400">N/A</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 py-1 text-xs rounded-full {{ ($row['transaction_type'] ?? '') === 'credit' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ ucfirst($row['transaction_type'] ?? '') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium {{ ($row['transaction_type'] ?? '') === 'credit' ? 'text-green-600' : 'text-red-600' }}">
                                        {{ ($row['transaction_type'] ?? '') === 'credit' ? '+' : '-' }}{{ number_format((float) ($row['amount'] ?? 0), 2) }} UGX
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @if(!empty($row['payment_status']))
                                        <span class="px-2 py-1 text-xs rounded-full {{ $row['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : ($row['payment_status'] === 'pending_payment' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                            {{ \App\Models\ThirdPartyPayerBalanceHistory::normalizePaymentStatusLabel($row['payment_status']) }}
                                        </span>
                                        @else
                                        <span class="text-gray-400">N/A</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 text-center">
                        <a href="{{ route('third-party-vendors.balance-statement', ['vendorId' => $vendor['id'], 'view' => 'items']) }}"
                           class="text-blue-600 hover:text-blue-800 font-medium">
                            View full statement (by item) →
                        </a>
                    </div>
                    @else
                    <div class="text-center py-8">
                        <p class="text-gray-500">No activity yet. Entries appear when invoices post to this vendor.</p>
                    </div>
                    @endif
                </div>

                <!-- Invoices Tab -->
                <div id="content-invoices" class="tab-content p-6 hidden">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Invoices</h3>
                            <p class="text-sm text-gray-500">Insurance invoices for this vendor</p>
                        </div>
                        <a href="{{ route('third-party-vendors.balance-statement', ['vendorId' => $vendor['id'], 'view' => 'invoices']) }}"
                           class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                            View all invoices
                        </a>
                    </div>

                    @if(count($vendorInvoices ?? []) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance due</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($vendorInvoices as $invoice)
                                @php
                                    $payStatus = $invoice['payment_status'] ?? 'pending_payment';
                                    $statusClass = match ($payStatus) {
                                        'paid' => 'bg-green-100 text-green-800',
                                        'partial' => 'bg-blue-100 text-blue-800',
                                        'pending_payment' => 'bg-yellow-100 text-yellow-800',
                                        default => 'bg-gray-100 text-gray-700',
                                    };
                                @endphp
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ !empty($invoice['created_at']) ? \Carbon\Carbon::parse($invoice['created_at'])->format('Y-m-d H:i') : '—' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        @if(!empty($invoice['id']))
                                            <a href="{{ route('invoices.show', $invoice['id']) }}" class="text-blue-600 hover:text-blue-800">
                                                {{ $invoice['invoice_number'] ?? 'N/A' }}
                                            </a>
                                        @else
                                            {{ $invoice['invoice_number'] ?? 'N/A' }}
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span class="font-medium">{{ $invoice['client_name'] ?? 'N/A' }}</span>
                                        @if(!empty($invoice['client_id']))
                                            <br><span class="text-xs text-gray-500">ID: {{ $invoice['client_id'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">UGX {{ number_format((float) ($invoice['total_amount'] ?? 0), 2) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-green-700">UGX {{ number_format((float) ($invoice['amount_paid'] ?? 0), 2) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium {{ ($invoice['balance_due'] ?? 0) > 0 ? 'text-amber-700' : 'text-gray-900' }}">
                                        UGX {{ number_format((float) ($invoice['balance_due'] ?? 0), 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 py-1 text-xs rounded-full {{ $statusClass }}">
                                            {{ ucfirst(str_replace('_', ' ', $payStatus)) }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-8">
                        <p class="text-gray-500">No invoices yet for this vendor.</p>
                    </div>
                    @endif

                </div>
            </div>

            <!-- Vendor Management / Blocking Card -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    @if($thirdPartyPayer->isActive())
                        <!-- Active - show suspend/block buttons -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Vendor Management</h3>
                            <p class="text-sm text-gray-600 mb-4">This vendor is currently active. You can suspend or block it.</p>
                            
                            <!-- Suspend Form -->
                            <div class="border-l-4 border-yellow-400 bg-yellow-50 p-4 rounded">
                                <h4 class="font-semibold text-yellow-900 mb-3">⊘ Suspend Vendor</h4>
                                <p class="text-sm text-yellow-800 mb-3">Temporarily suspend this vendor. They can be reactivated later.</p>
                                <form action="{{ route('third-party-vendors.block', $vendor['id']) }}" method="POST" class="space-y-3">
                                    @csrf
                                    <input type="hidden" name="status" value="suspended">
                                    <div>
                                        <label class="block text-sm font-medium text-yellow-900 mb-2">Reason for suspension:</label>
                                        <textarea name="reason" required placeholder="Enter reason for suspension..."
                                                  class="w-full px-3 py-2 border border-yellow-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500" rows="2"></textarea>
                                    </div>
                                    <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg bg-yellow-600 text-white hover:bg-yellow-700">
                                        Suspend
                                    </button>
                                </form>
                            </div>

                            <!-- Block Form -->
                            <div class="border-l-4 border-red-400 bg-red-50 p-4 rounded">
                                <h4 class="font-semibold text-red-900 mb-3">✕ Block Vendor</h4>
                                <p class="text-sm text-red-800 mb-3">Block this vendor. This will prevent any access to this vendor's data.</p>
                                <form action="{{ route('third-party-vendors.block', $vendor['id']) }}" method="POST" class="space-y-3">
                                    @csrf
                                    <input type="hidden" name="status" value="blocked">
                                    <div>
                                        <label class="block text-sm font-medium text-red-900 mb-2">Reason for blocking:</label>
                                        <textarea name="reason" required placeholder="Enter reason for blocking..."
                                                  class="w-full px-3 py-2 border border-red-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500" rows="2"></textarea>
                                    </div>
                                    <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg bg-red-600 text-white hover:bg-red-700"
                                            onclick="return confirm('Are you sure you want to block this vendor?')">
                                        Block
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <!-- Suspended or Blocked - show reactivate button -->
                        <div class="border-l-4 border-green-400 bg-green-50 p-4 rounded">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Vendor Management</h3>
                            <h4 class="font-semibold text-green-900 mb-3 mt-3">✓ Reactivate Vendor</h4>
                            <p class="text-sm text-green-800 mb-4">
                                This vendor is currently {{ $thirdPartyPayer->status === 'suspended' ? 'suspended' : 'blocked' }}.
                                @if($thirdPartyPayer->block_reason)
                                    <br><strong>Reason:</strong> {{ $thirdPartyPayer->block_reason }}
                                @endif
                                <br>Click below to reactivate it.
                            </p>
                            <form action="{{ route('third-party-vendors.reactivate', $vendor['id']) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg bg-green-600 text-white hover:bg-green-700"
                                        onclick="return confirm('Are you sure you want to reactivate this vendor?')">
                                    Reactivate Vendor
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
            @else
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <div class="text-center py-8">
                        <p class="text-gray-500">No third-party payer account found for this vendor. Balance history will appear here once invoices are created with this vendor.</p>
                    </div>
                </div>
            </div>
            @endif

<script>
function showTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    // Remove active class from all tabs
    document.querySelectorAll('.tab-button').forEach(button => {
        const span = button.querySelector('span');
        span.classList.remove('border-blue-500', 'text-blue-600');
        span.classList.add('border-transparent', 'text-gray-500');
    });
    
    // Show selected tab content
    document.getElementById('content-' + tabName).classList.remove('hidden');
    
    // Add active class to selected tab
    const activeButton = document.getElementById('tab-' + tabName);
    const activeSpan = activeButton.querySelector('span');
    activeSpan.classList.remove('border-transparent', 'text-gray-500');
    activeSpan.classList.add('border-blue-500', 'text-blue-600');
}
</script>

<style>
.tab-button {
    cursor: pointer;
}
.tab-button span {
    transition: all 0.2s;
}
.tab-button.active span {
    border-bottom-color: #3b82f6;
    color: #3b82f6;
}
</style>
        </div>
    </div>
</x-app-layout>
