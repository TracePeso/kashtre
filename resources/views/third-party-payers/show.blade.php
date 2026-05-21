<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Third Party Payer Details') }} - {{ $thirdPartyPayer->name }}
            </h2>
            <div class="flex space-x-2">
                @php
                    $isInitiator = \App\Models\CreditLimitApprovalApprover::where('business_id', auth()->user()->business_id)
                        ->where('approver_id', auth()->user()->id)
                        ->where('approval_level', 'initiator')
                        ->exists();
                @endphp
                @if($thirdPartyPayer->credit_limit !== null && $thirdPartyPayer->credit_limit >= 0 && in_array('Manage Credit Limits', (array) (auth()->user()->permissions ?? [])) && $isInitiator)
                    <a href="{{ route('credit-limit-requests.create', ['entity_type' => 'third_party_payer', 'entity_id' => $thirdPartyPayer->id]) }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                        Request Credit Limit Change
                    </a>
                @endif
                <a href="{{ route('third-party-payers.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    Back to List
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Third Party Payer Information Card -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Basic Information</h3>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Payer Name</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $thirdPartyPayer->name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Type</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                            @if($thirdPartyPayer->type === 'insurance_company') bg-blue-100 text-blue-800
                                            @else bg-green-100 text-green-800 @endif">
                                            {{ ucfirst(str_replace('_', ' ', $thirdPartyPayer->type)) }}
                                        </span>
                                    </dd>
                                </div>
                                @if($thirdPartyPayer->type === 'insurance_company' && $thirdPartyPayer->insuranceCompany)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Insurance Company</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ $thirdPartyPayer->insuranceCompany->name }}</dd>
                                    </div>
                                @endif
                                @if($thirdPartyPayer->type === 'normal_client' && $thirdPartyPayer->client)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Client</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ $thirdPartyPayer->client->name }}</dd>
                                    </div>
                                @endif
                                @if($thirdPartyPayer->contact_person)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Contact Person</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ $thirdPartyPayer->contact_person }}</dd>
                                    </div>
                                @endif
                                @if($thirdPartyPayer->phone_number)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Phone Number</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ $thirdPartyPayer->phone_number }}</dd>
                                    </div>
                                @endif
                                @if($thirdPartyPayer->email)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Email</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ $thirdPartyPayer->email }}</dd>
                                    </div>
                                @endif
                                @if($thirdPartyPayer->address)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Address</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ $thirdPartyPayer->address }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Financial Information</h3>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Current Balance</dt>
                                    <dd class="mt-1 text-lg font-semibold {{ $currentBalance < 0 ? 'text-red-600' : ($currentBalance > 0 ? 'text-green-600' : 'text-gray-900') }}">
                                        UGX {{ number_format(abs($currentBalance ?? 0), 2) }}
                                        @if($currentBalance < 0)
                                            <span class="text-xs text-red-500">(Amount Owed)</span>
                                        @elseif($currentBalance > 0)
                                            <span class="text-xs text-green-500">(Credit Available)</span>
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Credit Limit</dt>
                                    <dd class="mt-1 text-lg font-semibold text-gray-900">
                                        UGX {{ number_format((($thirdPartyPayer->credit_limit ?? 0) > 0
                                            ? (float) $thirdPartyPayer->credit_limit
                                            : (float) ($thirdPartyPayer->business->max_third_party_credit_limit ?? 0)), 2) }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                                    <dd class="mt-1">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                            @if($thirdPartyPayer->status === 'active') bg-green-100 text-green-800
                                            @elseif($thirdPartyPayer->status === 'suspended') bg-red-100 text-red-800
                                            @else bg-gray-100 text-gray-800 @endif">
                                            {{ ucfirst($thirdPartyPayer->status) }}
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Business</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $thirdPartyPayer->business->name ?? 'N/A' }}</dd>
                                </div>
                                @if($thirdPartyPayer->notes)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Notes</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ $thirdPartyPayer->notes }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Balance Statement Section -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Balance Statement</h3>
                    </div>
                    
                    <!-- Summary Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Total Credits</h4>
                            <p class="text-2xl font-bold text-green-600">UGX {{ number_format($totalCredits ?? 0, 2) }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Total Debits</h4>
                            <p class="text-2xl font-bold text-red-600">UGX {{ number_format($totalDebits ?? 0, 2) }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Current Balance</h4>
                            <p class="text-2xl font-bold {{ ($currentBalance ?? 0) < 0 ? 'text-red-600' : (($currentBalance ?? 0) > 0 ? 'text-green-600' : 'text-gray-700') }}">
                                UGX {{ number_format(abs($currentBalance ?? 0), 2) }}
                            </p>
                        </div>
                    </div>
                    
                    <!-- Transaction History Table -->
                    @if(isset($balanceHistories) && $balanceHistories->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($balanceHistories as $history)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $history->created_at->format('M d, Y H:i') }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ $history->description }}
                                        @if($history->notes)
                                            <br><span class="text-xs text-gray-500">{{ $history->notes }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        @if($history->client)
                                            <span class="font-medium">{{ $history->client->name }}</span>
                                            @if($history->client->client_id)
                                                <br><span class="text-xs text-gray-500">ID: {{ $history->client->client_id }}</span>
                                            @endif
                                        @else
                                            <span class="text-gray-400">N/A</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        @if($history->invoice)
                                            <a href="{{ route('invoices.show', $history->invoice->id) }}" class="text-blue-600 hover:text-blue-800 font-medium">
                                                {{ $history->invoice->invoice_number ?? 'N/A' }}
                                            </a>
                                        @else
                                            <span class="text-gray-400">N/A</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 py-1 text-xs rounded-full {{ $history->transaction_type === 'credit' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ ucfirst($history->transaction_type) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium {{ $history->transaction_type === 'credit' ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $history->transaction_type === 'credit' ? '+' : '-' }}{{ number_format(abs($history->change_amount), 2) }} UGX
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ number_format($history->new_balance, 2) }} UGX
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $history->payment_method ? ucwords(str_replace('_', ' ', $history->payment_method)) : 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @if($history->payment_status)
                                        <span class="px-2 py-1 text-xs rounded-full {{ $history->payment_status === 'paid' ? 'bg-green-100 text-green-800' : ($history->payment_status === 'pending_payment' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                            {{ \App\Models\ThirdPartyPayerBalanceHistory::normalizePaymentStatusLabel($history->payment_status) }}
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
                    
                    <!-- Pagination -->
                    <div class="mt-4">
                        {{ $balanceHistories->links() }}
                    </div>
                    @else
                    <div class="text-center py-8">
                        <p class="text-gray-500">No transactions found. Balance history entries will appear here when invoices are created with this third-party payer.</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Credit Limit Change Requests -->
            @if($thirdPartyPayer->credit_limit !== null)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Credit Limit Change Requests</h3>
                        @php
                            $creditRequests = $thirdPartyPayer->creditLimitChangeRequests()
                                ->orderBy('created_at', 'desc')
                                ->limit(10)
                                ->get();
                        @endphp
                        @if($creditRequests->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Requested Limit</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($creditRequests as $request)
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    UGX {{ number_format($request->requested_credit_limit, 2) }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                                        @if($request->status === 'approved') bg-green-100 text-green-800
                                                        @elseif($request->status === 'rejected') bg-red-100 text-red-800
                                                        @elseif($request->status === 'authorized') bg-blue-100 text-blue-800
                                                        @elseif($request->status === 'initiated') bg-yellow-100 text-yellow-800
                                                        @else bg-gray-100 text-gray-800 @endif">
                                                        {{ ucfirst($request->status) }}
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {{ $request->created_at->format('M d, Y') }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <a href="{{ route('credit-limit-requests.show', $request) }}" class="text-blue-600 hover:text-blue-900">
                                                        View Details
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-4">
                                <a href="{{ route('credit-limit-requests.index', ['entity_type' => 'third_party_payer', 'entity_id' => $thirdPartyPayer->id]) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    View All Requests →
                                </a>
                            </div>
                        @else
                            <p class="text-gray-500 text-sm">No credit limit change requests found.</p>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Excluded Items Management -->
            @if(in_array('Edit Third Party Payers', (array) (auth()->user()->permissions ?? [])))
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Service Exclusions</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Select items that should be excluded from third-party payer terms for this specific payer. These exclusions will be applied in addition to any business-level exclusions.
                        </p>
                        
                        <form action="{{ route('third-party-payers.update-excluded-items', $thirdPartyPayer) }}" method="POST">
                            @csrf
                            @method('POST')
                            
                            <div class="mb-4">
                                <label for="excluded_items" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Excluded Items
                                </label>
                                
                                <!-- Quick Filter Buttons -->
                                <div class="mb-3 flex flex-wrap gap-2">
                                    <button type="button" class="filter-btn-tpp px-3 py-1 text-sm rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 active" data-filter="all">
                                        All Items
                                    </button>
                                    <button type="button" class="filter-btn-tpp px-3 py-1 text-sm rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600" data-filter="service">
                                        Services
                                    </button>
                                    <button type="button" class="filter-btn-tpp px-3 py-1 text-sm rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600" data-filter="good">
                                        Goods
                                    </button>
                                    <button type="button" class="filter-btn-tpp px-3 py-1 text-sm rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600" data-filter="package">
                                        Packages
                                    </button>
                                    <button type="button" class="filter-btn-tpp px-3 py-1 text-sm rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600" data-filter="bulk">
                                        Bulk Items
                                    </button>
                                </div>
                                
                                <select 
                                    name="excluded_items[]" 
                                    id="excluded_items_tpp" 
                                    multiple
                                    class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                >
                                    @foreach($items as $item)
                                        <option 
                                            value="{{ $item->id }}"
                                            data-type="{{ $item->type }}"
                                            {{ in_array($item->id, old('excluded_items', $thirdPartyPayer->excluded_items ?? [])) ? 'selected' : '' }}
                                        >
                                            {{ $item->name }}@if($item->code) ({{ $item->code }})@endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                    Update Exclusions
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    
    <style>
        .filter-btn-tpp.active {
            background-color: #2563eb !important;
            color: white !important;
            border-color: #2563eb !important;
        }
        .filter-btn-tpp.active:hover {
            background-color: #1d4ed8 !important;
        }
    </style>
    
    <!-- Select2 JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            const $select = $('#excluded_items_tpp');
            
            $select.select2({
                theme: 'bootstrap-5',
                placeholder: 'Select items to exclude from third-party payer terms',
                allowClear: true,
                width: '100%'
            });
            
            // Quick filter functionality
            $('.filter-btn-tpp').on('click', function() {
                const filter = $(this).data('filter');
                
                // Update active button
                $('.filter-btn-tpp').removeClass('active bg-blue-600 text-white').addClass('bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300');
                $(this).removeClass('bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300').addClass('active bg-blue-600 text-white');
                
                // Filter options
                if (filter === 'all') {
                    $select.find('option').prop('disabled', false);
                } else {
                    $select.find('option').each(function() {
                        const $option = $(this);
                        if ($option.data('type') === filter) {
                            $option.prop('disabled', false);
                        } else {
                            $option.prop('disabled', true);
                        }
                    });
                }
                
                // Update Select2 to reflect changes
                $select.trigger('change.select2');
                
                // Open Select2 dropdown to show filtered results
                $select.select2('open');
            });
        });
    </script>
</x-app-layout>

