<x-app-layout>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Client Details') }}
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('clients.edit', $client) }}" 
                   class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Edit Client
                </a>
                <a href="{{ route('clients.index') }}" 
                   class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Back to List
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Success Message with Third-Party Credentials -->
            @if(session('success'))
                <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-4 rounded">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <p class="text-sm text-green-700">{!! session('success') !!}</p>
                        </div>
                    </div>
                </div>
            @endif
            
            @if(session('third_party_credentials'))
                @php
                    $creds = session('third_party_credentials');
                @endphp
                <div class="mb-4 bg-blue-50 border-l-4 border-blue-400 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold text-blue-900 mb-3">🔐 Third-Party System Login Credentials</h3>
                    <div class="space-y-2">
                        <div class="flex items-center">
                            <span class="text-sm font-medium text-gray-700 w-24">Username:</span>
                            <span class="text-sm font-mono bg-white px-3 py-1 rounded border">{{ $creds['username'] }}</span>
                            <button onclick="copyToClipboard('{{ $creds['username'] }}')" class="ml-2 text-blue-600 hover:text-blue-800 text-sm">
                                📋 Copy
                            </button>
                        </div>
                        <div class="flex items-center">
                            <span class="text-sm font-medium text-gray-700 w-24">Password:</span>
                            <span class="text-sm font-mono bg-white px-3 py-1 rounded border" id="password-display" data-password="{{ $creds['password'] }}">{{ $creds['password'] }}</span>
                            <button onclick="copyToClipboard('{{ $creds['password'] }}')" class="ml-2 text-blue-600 hover:text-blue-800 text-sm">
                                📋 Copy
                            </button>
                            <button onclick="togglePassword()" class="ml-2 text-blue-600 hover:text-blue-800 text-sm">
                                👁️ Show/Hide
                            </button>
                        </div>
                        <div class="mt-3 pt-3 border-t border-blue-200">
                            <a href="{{ $creds['login_url'] }}" target="_blank" 
                               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                                🔗 Login to Third-Party System
                            </a>
                        </div>
                    </div>
                </div>
            @endif
            
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <!-- Client ID and Status -->
                    <div class="mb-6">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900">Client Information</h3>
                            <div class="flex items-center space-x-4">
                                <span class="text-sm text-gray-500">Client ID: {{ $client->client_id }}</span>
                                <span class="px-3 py-1 text-sm font-medium rounded-full 
                                    {{ $client->status === 'active' ? 'bg-green-100 text-green-800' : 
                                       ($client->status === 'inactive' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                    {{ ucfirst($client->status) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="text-md font-medium text-gray-900 mb-4">Personal Information</h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Full Name</label>
                                    <p class="text-sm text-gray-900">{{ $client->name }}</p>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Surname</label>
                                        <p class="text-sm text-gray-900">{{ $client->surname }}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">First Name</label>
                                        <p class="text-sm text-gray-900">{{ $client->first_name }}</p>
                                    </div>
                                </div>
                                @if($client->other_names)
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Other Names</label>
                                    <p class="text-sm text-gray-900">{{ $client->other_names }}</p>
                                </div>
                                @endif
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Sex</label>
                                        <p class="text-sm text-gray-900">{{ ucfirst($client->sex) }}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Date of Birth</label>
                                        <p class="text-sm text-gray-900">{{ $client->date_of_birth ? \Carbon\Carbon::parse($client->date_of_birth)->format('M d, Y') : 'N/A' }}</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Marital Status</label>
                                        <p class="text-sm text-gray-900">{{ $client->marital_status ? ucfirst($client->marital_status) : 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Occupation</label>
                                        <p class="text-sm text-gray-900">{{ $client->occupation ?: 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="text-md font-medium text-gray-900 mb-4">Contact Information</h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Phone Number</label>
                                    <p class="text-sm text-gray-900">{{ $client->phone_number }}</p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Email</label>
                                    <p class="text-sm text-gray-900">{{ $client->email ?: 'N/A' }}</p>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">County</label>
                                        <p class="text-sm text-gray-900">{{ $client->county ?: 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Village</label>
                                        <p class="text-sm text-gray-900">{{ $client->village ?: 'N/A' }}</p>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Payment Methods</label>
                                    <div class="text-sm text-gray-900">
                                        @if($client->payment_methods && count($client->payment_methods) > 0)
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($client->payment_methods as $method)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        @switch($method)
                                                            @case('packages')
                                                                📦 Packages
                                                                @break
                                                            @case('insurance')
                                                                🛡️ Insurance
                                                                @break
                                                            @case('credit_arrangement')
                                                                💳 Credit Arrangement
                                                                @break
                                                            @case('deposits')
                                                                💰 Deposits/A/c Balance
                                                                @break
                                                            @case('mobile_money')
                                                                📱 MM (Mobile Money)
                                                                @break
                                                            @case('v_card')
                                                                💳 V Card (Virtual Card)
                                                                @break
                                                            @case('p_card')
                                                                💳 P Card (Physical Card)
                                                                @break
                                                            @case('bank_transfer')
                                                                🏦 Bank Transfer
                                                                @break
                                                            @case('cash')
                                                                💵 Cash
                                                                @break
                                                            @default
                                                                {{ ucwords(str_replace('_', ' ', $method)) }}
                                                        @endswitch
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-gray-500">No payment methods selected</span>
                                        @endif
                                    </div>
                                </div>
                                @if($client->payment_phone_number)
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Payment Phone Number</label>
                                    <p class="text-sm text-gray-900">{{ $client->payment_phone_number }}</p>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Identification -->
                    <div class="bg-gray-50 p-4 rounded-lg mb-8">
                        <h4 class="text-md font-medium text-gray-900 mb-4">Identification</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-gray-500">NIN</label>
                                <p class="text-sm text-gray-900">{{ $client->nin ?: 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">TIN Number</label>
                                <p class="text-sm text-gray-900">{{ $client->tin_number ?: 'N/A' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Services and Visit Information -->
                    <div class="bg-gray-50 p-4 rounded-lg mb-8">
                        <h4 class="text-md font-medium text-gray-900 mb-4">Services & Visit Information</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-gray-500">Services Category</label>
                                <p class="text-sm text-gray-900">
                                    @if($client->services_category)
                                        {{ ucwords(str_replace('_', ' ', $client->services_category)) }}
                                    @else
                                        N/A
                                    @endif
                                </p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Visit ID</label>
                                <p class="text-sm text-gray-900">{{ $client->visit_id }}</p>
                            </div>
                        </div>

                        @if(is_array($client->visit_authorization_sessions) && count($client->visit_authorization_sessions))
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <label class="text-sm font-medium text-gray-500">Insurer visit authorization (session from vendor)</label>
                                <p class="text-xs text-gray-500 mb-2">Synced when Kashtre registers the visit with the third-party; validity follows the insurer’s authorization period (days).</p>
                                <ul class="space-y-2">
                                    @foreach($client->visit_authorization_sessions as $remoteInsurerId => $sess)
                                        <li class="bg-white rounded border border-gray-200 p-3 text-sm">
                                            <span class="text-xs text-gray-500">Remote insurer ID {{ $remoteInsurerId }}</span>
                                            <p class="font-mono text-xs mt-1 break-all text-gray-900">{{ $sess['session_code'] ?? '—' }}</p>
                                            <p class="text-gray-700 mt-1">
                                                Session valid until:
                                                {{ !empty($sess['session_expires_at']) ? \Carbon\Carbon::parse($sess['session_expires_at'])->format('Y-m-d H:i') : '—' }}
                                            </p>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>

                    <!-- Next of Kin Information -->
                    @if($client->nok_surname || $client->nok_first_name)
                    <div class="bg-gray-50 p-4 rounded-lg mb-8">
                        <h4 class="text-md font-medium text-gray-900 mb-4">Next of Kin Information</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-3">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Surname</label>
                                        <p class="text-sm text-gray-900">{{ $client->nok_surname ?: 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">First Name</label>
                                        <p class="text-sm text-gray-900">{{ $client->nok_first_name ?: 'N/A' }}</p>
                                    </div>
                                </div>
                                @if($client->nok_other_names)
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Other Names</label>
                                    <p class="text-sm text-gray-900">{{ $client->nok_other_names }}</p>
                                </div>
                                @endif
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Marital Status</label>
                                        <p class="text-sm text-gray-900">{{ $client->nok_marital_status ? ucfirst($client->nok_marital_status) : 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Occupation</label>
                                        <p class="text-sm text-gray-900">{{ $client->nok_occupation ?: 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Gender</label>
                                    <p class="text-sm text-gray-900">{{ $client->nok_sex ? ucfirst($client->nok_sex) : 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Phone Number</label>
                                    <p class="text-sm text-gray-900">{{ $client->nok_phone_number ?: 'N/A' }}</p>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">County</label>
                                        <p class="text-sm text-gray-900">{{ $client->nok_county ?: 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Village</label>
                                        <p class="text-sm text-gray-900">{{ $client->nok_village ?: 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Client Statement -->
                    <div class="bg-gray-50 p-4 rounded-lg mb-8">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="text-md font-medium text-gray-900">Client Statement</h4>
                            <a href="{{ route('balance-statement.show', $client) }}" 
                               class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                View Balance Statement
                            </a>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-blue-50 p-4 rounded-lg text-center">
                                <div class="flex justify-between items-center mb-2">
                                    <p class="text-sm text-gray-500">Current Balance</p>
                                    <button onclick="refreshClientBalance()" class="text-blue-600 hover:text-blue-800 text-sm">
                                        <i class="fas fa-sync-alt"></i> Refresh
                                    </button>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-lg font-bold text-gray-900" id="client-balance-display">
                                        <span class="text-blue-600">Available:</span> UGX {{ number_format($client->available_balance ?? 0, 2) }}
                                    </p>
                                    <p class="text-sm text-gray-500" id="client-total-balance">
                                        <span class="text-gray-600">Total:</span> UGX {{ number_format($client->total_balance ?? 0, 2) }}
                                    </p>
                                </div>
                            </div>
                            <div class="bg-yellow-50 p-4 rounded-lg text-center">
                                <p class="text-sm text-gray-500 mb-1">Total Transactions</p>
                                <p class="text-xl font-bold text-yellow-600">{{ \App\Models\BalanceHistory::where('client_id', $client->id)->count() }}</p>
                            </div>
                        </div>
                        <div class="mt-4 flex space-x-2">
                            <a href="{{ route('balance-statement.show', $client) }}" 
                               class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition-colors">
                                View Detailed Client Statement
                            </a>
                        </div>
                    </div>

                    <!-- Financial Information -->
                    <div class="bg-gray-50 p-4 rounded-lg mb-8">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="text-md font-medium text-gray-900">Financial Information</h4>
                            <a href="{{ route('balance-statement.show', $client) }}" 
                               class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                                                 View Balance Statement
                            </a>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-gray-500">Available Balance</label>
                                <p class="text-sm text-blue-600 font-semibold">UGX {{ number_format($client->available_balance ?? 0, 2) }}</p>
                                <label class="text-xs font-medium text-gray-400">Total Balance</label>
                                <p class="text-xs text-gray-600">UGX {{ number_format($client->total_balance ?? 0, 2) }}</p>
                                @if(($client->suspense_balance ?? 0) > 0)
                                    <p class="text-xs text-orange-600">({{ number_format($client->suspense_balance ?? 0, 2) }} in suspense)</p>
                                @endif
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Created Date</label>
                                <p class="text-sm text-gray-900">{{ $client->created_at ? $client->created_at->format('M d, Y \a\t g:i A') : 'N/A' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Client Invoices -->
                    <div class="bg-gray-50 p-4 rounded-lg mb-8">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="text-md font-medium text-gray-900">Client Invoices</h4>
                            <a href="{{ route('invoices.index', ['client_id' => $client->id]) }}" 
                               class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                View All Invoices
                            </a>
                        </div>
                        
                        @php
                            $clientInvoices = \App\Models\Invoice::where('client_id', $client->id)
                                ->whereNull('parent_invoice_id')
                                ->orderBy('created_at', 'desc')
                                ->limit(5)
                                ->get();
                        @endphp
                        
                        @if($clientInvoices->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-100">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($clientInvoices as $invoice)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2 whitespace-nowrap text-sm text-blue-600">
                                                <a href="{{ route('invoices.show', $invoice) }}" class="hover:text-blue-800">
                                                    {{ $invoice->invoice_number }}
                                                </a>
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                                                {{ $invoice->created_at->format('M d, Y') }}
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                                                UGX {{ number_format($invoice->total_amount, 2) }}
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $invoice->payment_status_badge }}">
                                                    {{ ucfirst($invoice->payment_status) }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-sm font-medium space-x-2">
                                                <a href="{{ route('invoices.show', $invoice) }}" class="text-blue-600 hover:text-blue-900">View</a>
                                                <a href="{{ route('invoices.print', $invoice) }}" target="_blank" class="text-green-600 hover:text-green-900">Print</a>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-8">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No invoices found</h3>
                                <p class="mt-1 text-sm text-gray-500">This client hasn't had any invoices created yet.</p>
                            </div>
                        @endif
                    </div>

                    <!-- Credit Service Exclusions (for credit-eligible clients only) -->
                    @if($client->is_credit_eligible)
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Credit Service Exclusions</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                Select items that should be excluded from credit terms for this specific client. These exclusions will be applied in addition to any business-level exclusions.
                            </p>
                            
                            <form action="{{ route('clients.update-excluded-items', $client) }}" method="POST">
                                @csrf
                                @method('POST')
                                
                                <div class="mb-4">
                                    <label for="excluded_items_client" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Excluded Items
                                    </label>
                                    
                                    <!-- Quick Filter Buttons -->
                                    <div class="mb-3 flex flex-wrap gap-2">
                                        <button type="button" class="filter-btn-client px-3 py-1 text-sm rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 active" data-filter="all">
                                            All Items
                                        </button>
                                        <button type="button" class="filter-btn-client px-3 py-1 text-sm rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600" data-filter="service">
                                            Services
                                        </button>
                                        <button type="button" class="filter-btn-client px-3 py-1 text-sm rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600" data-filter="good">
                                            Goods
                                        </button>
                                        <button type="button" class="filter-btn-client px-3 py-1 text-sm rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600" data-filter="package">
                                            Packages
                                        </button>
                                        <button type="button" class="filter-btn-client px-3 py-1 text-sm rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600" data-filter="bulk">
                                            Bulk Items
                                        </button>
                                    </div>
                                    
                                    <select 
                                        name="excluded_items[]" 
                                        id="excluded_items_client" 
                                        multiple
                                        class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                    >
                                        @foreach($items as $item)
                                            <option 
                                                value="{{ $item->id }}"
                                                data-type="{{ $item->type }}"
                                                {{ in_array($item->id, old('excluded_items', $client->excluded_items ?? [])) ? 'selected' : '' }}
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

                    <!-- Action Buttons -->
                    <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                        <a href="{{ route('clients.edit', $client) }}" 
                           class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Edit Client
                        </a>
                        <a href="{{ route('clients.index') }}" 
                           class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                            Back to List
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Show temporary success message
                const btn = event.target;
                const originalText = btn.textContent;
                btn.textContent = '✓ Copied!';
                btn.classList.add('text-green-600');
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.classList.remove('text-green-600');
                }, 2000);
            }, function(err) {
                console.error('Failed to copy: ', err);
                alert('Failed to copy to clipboard');
            });
        }

        function togglePassword() {
            const passwordDisplay = document.getElementById('password-display');
            if (passwordDisplay) {
                const actualPassword = passwordDisplay.getAttribute('data-password');
                if (passwordDisplay.textContent === actualPassword) {
                    passwordDisplay.textContent = '••••••••••••';
                } else {
                    passwordDisplay.textContent = actualPassword;
                }
            }
        }

        async function refreshClientBalance() {
            try {
                const response = await fetch('/invoices/balance-adjustment', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        client_id: {{ $client->id }},
                        total_amount: 0 // Just to get current balance
                    })
                });

                const data = await response.json();

                if (data.success) {
                    const balanceDisplay = document.getElementById('client-balance-display');
                    const totalBalanceDisplay = document.getElementById('client-total-balance');

                    const availableBalance = parseFloat(data.available_balance || data.client_balance || 0);
                    const totalBalance = parseFloat(data.total_balance || data.client_balance || 0);
                    const suspenseBalance = parseFloat(data.suspense_balance || 0);

                    // Update available balance display
                    balanceDisplay.innerHTML = `<span class="text-blue-600">Available:</span> UGX ${availableBalance.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

                    // Update total balance display
                    totalBalanceDisplay.innerHTML = `<span class="text-gray-600">Total:</span> UGX ${totalBalance.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Balance Updated',
                        text: `Current balance: UGX ${availableBalance.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`,
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to refresh balance'
                    });
                }
            } catch (error) {
                console.error('Error refreshing client balance:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to refresh balance'
                });
            }
        }

    </script>

    <!-- Select2 CSS for client exclusions -->
    @if($client->is_credit_eligible)
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    
    <style>
        .filter-btn-client.active {
            background-color: #2563eb !important;
            color: white !important;
            border-color: #2563eb !important;
        }
        .filter-btn-client.active:hover {
            background-color: #1d4ed8 !important;
        }
    </style>
    
    <!-- Select2 JS for client exclusions -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            const $select = $('#excluded_items_client');
            
            $select.select2({
                theme: 'bootstrap-5',
                placeholder: 'Select items to exclude from credit terms',
                allowClear: true,
                width: '100%'
            });
            
            // Quick filter functionality
            $('.filter-btn-client').on('click', function() {
                const filter = $(this).data('filter');
                
                // Update active button
                $('.filter-btn-client').removeClass('active bg-blue-600 text-white').addClass('bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300');
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
    @endif

    <!-- Show SweetAlert for Third-Party Credentials -->
    @if(session('third_party_credentials'))
        @php
            $creds = session('third_party_credentials');
        @endphp
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Company Registered Successfully!',
                    html: `
                        <div class="text-left space-y-4">
                            <p class="text-gray-700 font-semibold">Client ID: <span class="font-mono text-blue-600">{{ $client->client_id }}</span></p>
                            
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-4">
                                <h4 class="text-md font-semibold text-blue-900 mb-3 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3a1 1 0 001 1h1a1 1 0 100-2h-1V7zm-1 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path>
                                    </svg>
                                    Third-Party System Account Created
                                </h4>
                                <div class="space-y-3 text-sm">
                                    <div class="flex items-center justify-between">
                                        <span class="font-medium text-gray-700">Username:</span>
                                        <div class="flex items-center space-x-2">
                                            <span class="font-mono bg-white px-3 py-1 rounded border text-blue-600" id="swal-username">{{ $creds['username'] }}</span>
                                            <button onclick="copyToSwalClipboard('{{ $creds['username'] }}', 'Username')" class="text-blue-600 hover:text-blue-800 focus:outline-none" title="Copy username">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2h2"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="font-medium text-gray-700">Password:</span>
                                        <div class="flex items-center space-x-2">
                                            <span class="font-mono bg-white px-3 py-1 rounded border text-blue-600" id="swal-password" data-password="{{ $creds['password'] }}">••••••••••••</span>
                                            <button onclick="toggleSwalPassword()" class="text-blue-600 hover:text-blue-800 focus:outline-none" title="Show/Hide password">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                            </button>
                                            <button onclick="copyToSwalClipboard('{{ $creds['password'] }}', 'Password')" class="text-blue-600 hover:text-blue-800 focus:outline-none" title="Copy password">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2h2"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="pt-3 border-t border-blue-200 mt-3">
                                        <a href="{{ $creds['login_url'] }}" target="_blank" 
                                           class="inline-flex items-center justify-center w-full px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors duration-200">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                            </svg>
                                            Login to Third-Party System
                                        </a>
                                    </div>
                                    <p class="text-xs text-red-600 mt-2 font-semibold">⚠️ IMPORTANT: Please save these credentials securely!</p>
                                </div>
                            </div>
                        </div>
                    `,
                    width: '600px',
                    showConfirmButton: true,
                    confirmButtonText: 'Got it!',
                    confirmButtonColor: '#2563eb',
                    allowOutsideClick: false,
                    allowEscapeKey: true,
                });
            });

            function copyToSwalClipboard(text, type) {
                navigator.clipboard.writeText(text).then(function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Copied!',
                        text: type + ' copied to clipboard.',
                        timer: 1500,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                }, function(err) {
                    console.error('Failed to copy: ', err);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to copy ' + type.toLowerCase() + '.'
                    });
                });
            }

            function toggleSwalPassword() {
                const passwordElement = document.getElementById('swal-password');
                if (passwordElement) {
                    const actualPassword = passwordElement.getAttribute('data-password');
                    if (passwordElement.textContent === '••••••••••••') {
                        passwordElement.textContent = actualPassword;
                    } else {
                        passwordElement.textContent = '••••••••••••';
                    }
                }
            }
        </script>
    @endif
</x-app-layout>
