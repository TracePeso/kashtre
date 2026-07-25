<x-app-layout>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
                {{ __('Point of sale') }}
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                <span class="px-3 py-1 text-sm font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">
                    Active
                </span>
                <a href="/invoices" class="px-3 py-1 text-sm font-medium rounded-full bg-blue-100 text-blue-800 hover:bg-blue-200 dark:bg-blue-900/40 dark:text-blue-300 dark:hover:bg-blue-800/50 transition-colors">
                    View All Proforma Invoices
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <!-- Visit ID Check -->
            @if(empty($client->visit_id))
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Client Not Registered',
                        html: `
                            <p class="text-gray-700 mb-3">This client is not registered. You cannot perform any actions for an unregistered client.</p>
                            <p class="text-gray-600">Please register the client to continue.</p>
                        `,
                        confirmButtonText: 'OK',
                        allowOutsideClick: true,
                        allowEscapeKey: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Redirect to clients page or registration
                            window.location.href = '/clients';
                        }
                    });
                });
            </script>
            @endif

            <!-- Success Message -->
            @if(session('success'))
            <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                    </div>
                </div>
            </div>
            @endif
            <!-- Section 1: compact Client ID / Visit ID + optional extra details -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6 border border-gray-100 dark:border-gray-700" x-data="{ expanded: false }">
                <div class="p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="bg-gray-50 border border-gray-200 dark:bg-gray-800/80 dark:border-gray-600 px-2.5 py-2 rounded-md min-w-[7rem]">
                            <p class="text-[10px] text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-0.5 leading-none">Client ID</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 font-mono leading-tight">{{ $client->client_id }}</p>
                        </div>
                        <div class="bg-gray-50 border border-gray-200 dark:bg-gray-800/80 dark:border-gray-600 px-2.5 py-2 rounded-md min-w-[7rem]">
                            <p class="text-[10px] text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-0.5 leading-none">Visit ID</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 font-mono leading-tight">{{ $client->visit_id }}</p>
                        </div>
                        <div class="bg-gray-50 border border-gray-200 dark:bg-gray-800/80 dark:border-gray-600 px-2.5 py-2 rounded-md min-w-[7rem] max-w-[min(100%,14rem)] sm:max-w-xs">
                            <p class="text-[10px] text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-0.5 leading-none">Names</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 leading-tight break-words">{{ $client->name }}</p>
                        </div>
                        <div class="bg-gray-50 border border-gray-200 dark:bg-gray-800/80 dark:border-gray-600 px-2.5 py-2 rounded-md min-w-[7rem]">
                            <p class="text-[10px] text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-0.5 leading-none">Age</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 leading-tight tabular-nums">{{ $client->age !== null && $client->age !== '' ? $client->age . ' years' : 'N/A' }}</p>
                        </div>
                        <button type="button"
                                @click="expanded = !expanded"
                                :aria-expanded="expanded"
                                aria-controls="client-summary-details-expanded"
                                id="client-summary-details-toggle"
                                class="ml-auto shrink-0 inline-flex items-center justify-center rounded-md border border-gray-200 bg-gray-50 p-1.5 text-gray-600 hover:bg-gray-100 hover:border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 transition-colors"
                                :title="expanded ? 'Hide extra details' : 'Show extra details'">
                            <span class="sr-only" x-text="expanded ? 'Hide extra client details' : 'Show extra client details'"></span>
                            <svg x-show="expanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                            </svg>
                            <svg x-show="!expanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                    </div>
                    <div id="client-summary-details-expanded"
                         x-show="expanded"
                         x-transition
                         x-cloak
                         role="region"
                         aria-label="Additional client details">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 pt-2 border-t border-gray-100">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-500 mb-1">Sex</p>
                            <p class="text-lg font-semibold text-gray-900">{{ ucfirst($client->sex) }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-500 mb-1">Payment Methods</p>
                            <div class="flex flex-wrap gap-1 mb-2 payment-methods-display">
                                @if($client->payment_methods && count($client->payment_methods) > 0)
                                    @foreach($client->payment_methods as $index => $method)
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ $index + 1 }}. {{ ucwords(str_replace('_', ' ', $method)) }}
                                        </span>
                                    @endforeach
                                @else
                                    <span class="text-sm text-gray-500">No payment methods specified</span>
                                @endif
                            </div>
                            <button onclick="openPaymentMethodsModal()" class="text-xs text-blue-600 hover:text-blue-800 underline">
                                Edit Payment Methods
                            </button>
                        </div>
                        @if($client->vendors && $client->vendors->count() > 0)
                        <div class="bg-green-50 p-4 rounded-lg border-2 border-green-200">
                            <p class="text-sm text-green-700 font-medium mb-3">Third-party payment methods</p>
                            <div class="space-y-2">
                                @foreach($client->vendors->sortBy('priority')->values() as $index => $vendor)
                                    @if($vendor->vendor && $vendor->vendor->insuranceCompany)
                                        @php
                                            $isPrimary = ((int)($vendor->priority ?? 0) === 1) || $index === 0;
                                            $isSecondary = ((int)($vendor->priority ?? 0) === 2) || (!$isPrimary && $index === 1);
                                        @endphp
                                        <div class="bg-white p-3 rounded border border-green-100 flex items-center justify-between">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <p class="text-sm font-semibold text-gray-900">{{ $vendor->vendor->insuranceCompany->name }}</p>
                                                    @if($isPrimary)
                                                        <span class="inline-block text-[10px] font-semibold text-blue-800 bg-blue-100 px-2 py-0.5 rounded">Primary</span>
                                                    @elseif($isSecondary)
                                                        <span class="inline-block text-[10px] font-semibold text-purple-800 bg-purple-100 px-2 py-0.5 rounded">Secondary</span>
                                                    @endif
                                                </div>
                                                <p class="text-xs text-gray-600">Policy: {{ $vendor->policy_number ?? 'N/A' }}</p>
                                                @if($vendor->policy_verified)
                                                    <span class="inline-block text-xs text-green-700 bg-green-100 px-2 py-0.5 rounded mt-1">✓ Verified</span>
                                                @endif
                                            </div>
                                            <div class="text-right">
                                                @if($vendor->physical_insurance_card_verified)
                                                    <span class="inline-block text-xs text-blue-700 bg-blue-100 px-2 py-0.5 rounded">Card Verified</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        @endif
                        <div id="payment-phone-section" class="bg-gray-50 p-4 rounded-lg border-2 border-dashed border-blue-200 hover:border-blue-300 transition-colors" style="display: none;">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm text-gray-500 font-medium">Payment Phone Number</p>
                                <span class="text-xs text-blue-600 bg-blue-100 px-2 py-1 rounded-full">Editable</span>
                            </div>
                            <div class="space-y-2">
                                <input type="tel"
                                       id="payment-phone-edit"
                                       value="{{ $client->payment_phone_number ?? '' }}"
                                       placeholder="Enter payment phone number"
                                       class="w-full text-lg font-semibold text-gray-900 bg-white border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                <button type="button" onclick="savePaymentPhone()"
                                        class="w-full bg-blue-600 text-white px-3 py-2 rounded-md hover:bg-blue-700 transition-colors text-sm font-medium">
                                    Save Payment Phone
                                </button>
                            </div>
                        </div>
                        @if($client->insurance_company_id)
                        <div class="bg-gray-50 p-4 rounded-lg border-2 border-dashed border-blue-200 hover:border-blue-300 transition-colors">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm text-gray-500 font-medium">Services Category</p>
                                <span class="text-xs text-blue-600 bg-blue-100 px-2 py-1 rounded-full">Editable</span>
                            </div>
                            <div class="space-y-2">
                                <select id="services-category-edit"
                                        class="w-full text-lg font-semibold text-gray-900 bg-white border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                    <option value="dental" {{ ($client->services_category ?? '') === 'dental' ? 'selected' : '' }}>Dental</option>
                                    <option value="optical" {{ ($client->services_category ?? '') === 'optical' ? 'selected' : '' }}>Optical</option>
                                    <option value="outpatient" {{ ($client->services_category ?? '') === 'outpatient' ? 'selected' : '' }}>Outpatient</option>
                                    <option value="inpatient" {{ ($client->services_category ?? '') === 'inpatient' ? 'selected' : '' }}>Inpatient</option>
                                    <option value="maternity" {{ ($client->services_category ?? '') === 'maternity' ? 'selected' : '' }}>Maternity</option>
                                    <option value="funeral" {{ ($client->services_category ?? '') === 'funeral' ? 'selected' : '' }}>Funeral</option>
                                </select>
                                <button type="button" onclick="saveServicesCategory()"
                                        class="w-full bg-blue-600 text-white px-3 py-2 rounded-md hover:bg-blue-700 transition-colors text-sm font-medium">
                                    Save Services Category
                                </button>
                            </div>
                        </div>
                        @endif
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-500 mb-1">Contact Phone Number</p>
                            <p class="text-lg font-semibold text-gray-900">{{ $client->phone_number }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-500 mb-1">Email Address</p>
                            <p class="text-lg font-semibold text-gray-900">{{ $client->email ?? 'N/A' }}</p>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
            
            <!-- Payment Responsibility section removed: authorization and client portion are handled after Confirm & Save via third-party. -->
            
            <!-- Section 2: Client Statement -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Client Statement</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
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
                        @if($client->is_credit_eligible)
                            @php
                                $creditLimit = $client->max_credit ?? 0;
                                $availableBalance = $client->available_balance ?? 0;
                                $amountOwed = $availableBalance < 0 ? abs($availableBalance) : 0;
                                $creditRemaining = max(0, $creditLimit - $amountOwed);
                            @endphp
                            <div class="bg-green-50 p-4 rounded-lg text-center">
                                <p class="text-sm text-gray-500 mb-1">Credit Limit</p>
                                <p class="text-lg font-bold text-gray-700">
                                    UGX {{ number_format($creditLimit, 2) }}
                                </p>
                            </div>
                            <div class="bg-purple-50 p-4 rounded-lg text-center">
                                <p class="text-sm text-gray-500 mb-1">Credit Remaining</p>
                                <p class="text-lg font-bold {{ $creditRemaining > 0 ? 'text-green-600' : 'text-red-600' }}" id="credit-remaining-display">
                                    UGX {{ number_format($creditRemaining, 2) }}
                                </p>
                                @if($creditRemaining <= 0)
                                    <p class="text-xs text-red-500 mt-1">(Credit Limit Exceeded)</p>
                                @endif
                            </div>
                        @else
                            <div class="bg-yellow-50 p-4 rounded-lg text-center">
                                <p class="text-sm text-gray-500 mb-1">Total Transactions</p>
                                <p class="text-xl font-bold text-yellow-600">0</p>
                            </div>
                        @endif
                    </div>
                    <div class="mt-4">
                        <a href="{{ route('balance-statement.show', $client->id) }}" target="_blank" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition-colors">
                            View Client Statement
                        </a>
                    </div>
                </div>
            </div>

            <!-- Section 3: Client Notes -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Client Notes</h3>
                    <div class="border border-gray-200 rounded-lg">
                        <div class="p-4">
                            <textarea placeholder="Add notes about this client..." class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" rows="3"></textarea>
                            <div class="mt-3 flex justify-end">
                                <button class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors">
                                    Save Notes
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 4: Make a Request/Order - Professional Two Column Layout -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Make a Request/Order</h3>

                    <!-- Main POS Interface - Two Column Layout -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                        <!-- Left Column: Item Selection -->
                        <div>
                            <h4 class="text-md font-medium text-gray-900 mb-4">Select Item</h4>

                            <!-- Search Bar -->
                            <div class="mb-4">
                                <div class="relative">
                                    <input type="text" id="search-input" placeholder="Search items..."
                                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                            </div>

                            <!-- Simple Options -->
                            <div class="flex items-center space-x-4 mb-4">
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" id="show-prices" class="rounded border-gray-300">
                                    <span class="text-sm text-gray-700">Show Prices</span>
                                </label>

                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" id="show-descriptions" class="rounded border-gray-300">
                                    <span class="text-sm text-gray-700">Show Descriptions</span>
                                </label>
                            </div>

                            <!-- Items Table -->
                            <div class="border border-gray-200 rounded-lg overflow-hidden">
                                <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="flex items-center">
                                            <span class="text-sm font-medium text-gray-700">Item</span>
                                            <svg class="ml-1 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <span class="text-sm font-medium text-gray-700">Quantity To Sell</span>
                                        </div>
                                    </div>
                                </div>

                                <div id="items-container" class="divide-y divide-gray-200 max-h-96 overflow-y-auto">
                                    @forelse($items as $item)
                                    <div class="item-row px-4 py-3 hover:bg-gray-50" data-item-name="{{ strtolower($item->name) }}" data-item-display-name="{{ $item->name }}" data-item-other-names="{{ strtolower($item->other_names ?? '') }}" data-item-type="{{ $item->type ?? 'N/A' }}">
                                        <div class="grid grid-cols-2 gap-4 items-center">
                                            <div>
                                                <span class="text-sm text-gray-900">{{ $item->name }}</span>
                                                @php
                                                    // Generate dynamic description based on item properties
                                                    $description = '';
                                                    if ($item->description && !empty(trim($item->description))) {
                                                        $description = $item->description;
                                                    } else {
                                                        // Generate dynamic description based on item type and properties
                                                        $descriptionParts = [];

                                                        // Add type-specific description based on item name and type
                                                        $itemName = strtolower($item->name);

                                                        // Generate intelligent descriptions based on item name patterns
                                                        if (str_contains($itemName, 'amoxicillin')) {
                                                            $descriptionParts[] = 'Antibiotic medication for bacterial infections';
                                                        } elseif (str_contains($itemName, 'paracetamol') || str_contains($itemName, 'acetaminophen')) {
                                                            $descriptionParts[] = 'Pain relief and fever reducer';
                                                        } elseif (str_contains($itemName, 'ibuprofen')) {
                                                            $descriptionParts[] = 'Anti-inflammatory pain relief';
                                                        } elseif (str_contains($itemName, 'vitamin') || str_contains($itemName, 'supplement')) {
                                                            $descriptionParts[] = 'Nutritional supplement';
                                                        } elseif (str_contains($itemName, 'hair') || str_contains($itemName, 'shampoo') || str_contains($itemName, 'conditioner')) {
                                                            $descriptionParts[] = 'Hair care product';
                                                        } elseif (str_contains($itemName, 'treatment') || str_contains($itemName, 'therapy')) {
                                                            $descriptionParts[] = 'Therapeutic treatment service';
                                                        }

                                                        // Add item variation info if present in name
                                                        if (str_contains($itemName, 'advanced') || str_contains($itemName, 'premium') || str_contains($itemName, 'deluxe') || str_contains($itemName, 'professional') || str_contains($itemName, 'enhanced')) {
                                                            $descriptionParts[] = 'Premium quality variant';
                                                        } elseif (str_contains($itemName, 'basic') || str_contains($itemName, 'standard')) {
                                                            $descriptionParts[] = 'Standard quality variant';
                                                        }

                                                        // Add generic name if available
                                                        if ($item->generic_name && !empty(trim($item->generic_name))) {
                                                            $descriptionParts[] = "Generic: {$item->generic_name}";
                                                        }

                                                        // Add category if available
                                                        if ($item->category && !empty(trim($item->category))) {
                                                            $descriptionParts[] = "Category: {$item->category}";
                                                        }

                                                        // Add other names if available
                                                        if ($item->other_names && !empty(trim($item->other_names))) {
                                                            $descriptionParts[] = "Also known as: {$item->other_names}";
                                                        }

                                                        // Add unit information if available
                                                        if ($item->unit && !empty(trim($item->unit))) {
                                                            $descriptionParts[] = "Unit: {$item->unit}";
                                                        }

                                                        $description = implode(' • ', $descriptionParts);
                                                    }
                                                @endphp
                                                @if($description)
                                                <p class="text-xs text-gray-500 mt-1 description-display">{{ $description }}</p>
                                                @endif
                                                <p class="text-xs text-blue-600 mt-1 price-display" style="display: none;">
                                                    Price: UGX {{ number_format($item->final_price ?? 0, 2) }}
                                                    @if(isset($item->final_price) && $item->final_price != $item->default_price)
                                                        <span class="text-green-600">(Branch Price)</span>
                                                    @else
                                                        <span class="text-gray-500">(Sale price)</span>
                                                    @endif
                                                    @if($item->vat_rate && $item->vat_rate > 0)
                                                        <span class="text-orange-600">(VAT: {{ $item->vat_rate }}%)</span>
                                                    @endif
                                                </p>
                                            </div>
                                            <div>
                                                <input type="number" min="0" value="0"
                                                       class="quantity-input w-20 px-2 py-1 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                       data-item-id="{{ $item->id }}"
                                                       data-item-price="{{ $item->final_price ?? 0 }}"
                                                       data-item-name="{{ $item->name }}"
                                                       data-item-display-name="{{ $item->name }}"
                                                       data-item-other-names="{{ $item->other_names ?? '' }}"
                                                       data-item-type="{{ $item->type ?? 'N/A' }}">
                                            </div>
                                        </div>
                                    </div>
                                    @empty
                                    <div class="px-4 py-8">
                                        <p class="text-sm text-gray-500 text-center">No items available for this hospital</p>
                                    </div>
                                    @endforelse
                                </div>
                            </div>

                            <!-- Pagination -->
                            <div class="mt-4 flex items-center justify-between text-sm text-gray-500">
                                <span>Showing 1 to {{ min(5, count($items)) }} of {{ count($items) }} results</span>
                                <div class="flex items-center space-x-2">
                                    <select class="px-2 py-1 border border-gray-300 rounded-md">
                                        <option value="5">5</option>
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                    </select>
                                    <span>Per page</span>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Request/Order Summary -->
                        <div>
                            <h4 class="text-md font-medium text-gray-900 mb-4">Request/Order Summary</h4>

                            <!-- Request/Order Summary Table -->
                            <div class="border border-gray-200 rounded-lg overflow-hidden">
                                <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                    <div class="grid grid-cols-4 gap-4">
                                        <div>
                                            <span class="text-sm font-medium text-gray-700">Item</span>
                                        </div>
                                        <div class="text-center">
                                            <span class="text-sm font-medium text-gray-700">Quantity</span>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-sm font-medium text-gray-700">Price</span>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-sm font-medium text-gray-700">Action</span>
                                        </div>
                                    </div>
                                </div>

                                <div id="request-order-summary-items" class="divide-y divide-gray-200 min-h-32">
                                    <div class="px-4 py-8">
                                        <p class="text-sm text-gray-500 text-center">No items selected</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Request/Order Summary -->
                            <div class="mt-4 bg-gray-50 p-4 rounded-lg">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm text-gray-600">Unique Items:</span>
                                    <span id="total-items" class="text-sm font-medium text-gray-900">0</span>
                                </div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm text-gray-600">Total Quantity:</span>
                                    <span id="total-quantity" class="text-sm font-medium text-gray-900">0</span>
                                </div>
                                <div class="flex justify-between items-center mb-4">
                                    <span class="text-sm text-gray-600">Total Amount:</span>
                                    <span id="total-amount" class="text-lg font-bold text-gray-900">UGX 0.00</span>
                                </div>
                                <button class="w-full bg-gray-900 text-white px-4 py-2 rounded-md hover:bg-gray-800 transition-colors" onclick="showInvoicePreview()">
                                    Preview Proforma Invoice
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 5: Ordered Items (Requests/Orders) -->
            <x-pos.ordered-items
                :pendingItems="$pendingItems"
                :partiallyDoneItems="$partiallyDoneItems"
                :completedItems="collect()"
                :correctTotalAmount="$correctTotalAmount ?? 0"
                :servicePoint="$servicePoint ?? null"
                :client="$client"
            />

            <!-- Save and Exit Button -->
            <div class="flex justify-end space-x-4 mt-6">
                <button type="button" onclick="cancelAndExit()"
                        class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-colors">
                    Cancel
                </button>
                <button onclick="saveAndExit()"
                        class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition-colors">
                    Save and Exit
                </button>
            </div>
        </div>
    </div>

    <!-- Client Confirmation Modal -->
    <div id="client-confirmation-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center">
        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <!-- Header -->
            <div class="bg-gray-50 px-6 py-4 rounded-t-lg border-b">
                <h3 class="text-lg font-semibold text-gray-800 text-center">
                    {{ auth()->user()->business->name ?? 'Medical Centre' }}
                </h3>
            </div>

            <!-- Client Details -->
            <div class="px-6 py-4">
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="space-y-2">
                        <p class="text-sm text-gray-600">{{ $client->name }}</p>
                        <p class="text-sm text-gray-600">Client ID: {{ $client->client_id }}</p>
                        <p class="text-sm text-gray-600">Branch: {{ auth()->user()->currentBranch->name ?? 'N/A' }}</p>
                    </div>
                    <div class="space-y-2">
                        <p class="text-sm text-gray-600">Age: {{ $client->age ?? 'N/A' }}</p>
                        <p class="text-sm text-gray-600">Sex: {{ $client->sex ?? 'N/A' }}</p>
                        <p class="text-sm text-gray-600">Visit ID: {{ $client->visit_id }}</p>
                    </div>
                </div>

                <!-- QR Code Placeholder -->
                <div class="flex justify-end mb-4">
                    <div class="w-16 h-16 bg-white border border-gray-300 flex items-center justify-center">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=64x64&data={{ urlencode($client->client_id . '|' . $client->name) }}"
                             alt="QR Code"
                             class="w-full h-full object-contain"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="hidden w-full h-full bg-gray-100 border border-gray-300 flex items-center justify-center">
                            <span class="text-xs text-gray-500 text-center">QR<br>Code</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="bg-gray-50 px-6 py-4 rounded-b-lg flex justify-center space-x-4">
                <button onclick="printClientDetails()" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded transition duration-200">
                    Print
                </button>
                <button onclick="closeClientConfirmation()" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded transition duration-200">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Order/Request Summary Modal -->
    <div id="invoice-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <!-- Proforma Invoice Header -->
                <div class="text-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">Proforma Invoice</h2>
                    <div class="bg-blue-600 text-white py-2 px-4 rounded-lg mb-2">
                        <span class="text-lg font-semibold">Proforma Invoice</span>
                    </div>
                    <div class="bg-gray-100 py-2 px-4 rounded-lg border">
                        <span class="text-sm text-gray-600">Proforma Invoice Number:</span>
                        <span id="invoice-number-display" class="text-lg font-bold text-gray-800 ml-2">Generating...</span>
                    </div>
                </div>

                <!-- Client and Transaction Details -->
                <div class="grid grid-cols-2 gap-4 mb-6 text-sm text-gray-700">
                    <div>
                        <p><strong>Entity:</strong> {{ auth()->user()->business->name ?? 'N/A' }}</p>
                        <p><strong>Date:</strong> {{ now()->format('n/j/Y') }}</p>
                        <p><strong>Attended To By:</strong> {{ auth()->user()->name }}</p>
                    </div>
                    <div>
                        <p><strong>Client Name:</strong> {{ $client->name }}</p>
                        <p><strong>Client ID:</strong> {{ $client->client_id }}</p>
                        <p><strong>Visit ID:</strong> {{ $client->visit_id }}</p>
                        <p><strong>Branch Name:</strong> {{ auth()->user()->currentBranch->name ?? 'N/A' }}</p>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="mb-6">
                    <table class="w-full border-collapse border border-gray-300">
                        <thead>
                            <tr class="bg-blue-600 text-white">
                                <th class="border border-gray-300 px-4 py-2 text-left">Item</th>
                                <th class="border border-gray-300 px-4 py-2 text-center">Quantity</th>
                                <th class="border border-gray-300 px-4 py-2 text-right">Price</th>
                                <th class="border border-gray-300 px-4 py-2 text-right">Amount</th>

                            </tr>
                        </thead>
                        <tbody id="invoice-items-table">
                            <!-- Items will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>

                <!-- Package Adjustment Details -->
                {{-- Commented out for debugging purposes
                <div id="package-adjustment-details" class="mb-6 hidden">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Package Adjustments Applied</h3>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div id="package-adjustment-list" class="space-y-2">
                            <!-- Package adjustment details will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
                --}}

                <!-- Financial Summary -->
                <div class="text-right space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span>Subtotal 1:</span>
                        <span id="invoice-subtotal">UGX 0.00</span>
                    </div>
                    <div id="package-adjustment-row" class="flex justify-between hidden">
                        <span>Package Adjustment:</span>
                        <span id="package-adjustment-display">UGX 0.00</span>
                    </div>
                    <div id="balance-adjustment-row" class="flex justify-between hidden">
                        <span>Account Balance(A/c) Adjustment:</span>
                        <span id="balance-adjustment-display">UGX 0.00</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Subtotal 2:</span>
                        <span id="invoice-subtotal-2">UGX 0.00</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Service Charge:</span>
                        <span id="service-charge-display">UGX 0.00</span>
                    </div>
                    <div class="text-xs text-gray-500 text-right italic" id="service-charge-note">
                        No charges for this amount range
                    </div>
                    <div class="flex justify-between text-lg font-bold border-t pt-2">
                        <span>Total:</span>
                        <span id="invoice-final-total">UGX 0.00</span>
                    </div>
                </div>
                
                <!-- Payment responsibility (co-pay, deductible, co-insurance) is not shown on invoice preview;
                     third-party authorization will return client total and insurance total. -->
                
                <!-- Third-Party Payer Selection (for credit clients with insurance) -->
                @if($client->is_credit_eligible)
                <div id="third-party-payer-section" class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Select Third-Party Payer <span class="text-red-500">*</span>
                    </label>
                    <select id="third-party-payer-select" name="third_party_payer_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">-- Select Third-Party Payer --</option>
                        @foreach($thirdPartyPayers ?? [] as $payer)
                            <option value="{{ $payer->id }}">{{ $payer->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs text-gray-600">
                        <strong>Note:</strong> For credit clients with insurance, you must select a third-party payer. The third-party payer's account will be debited instead of the client's account.
                    </p>
                </div>
                @endif

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-4 mt-6">
                    <button onclick="closeInvoicePreview()" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 transition-colors">
                        Close
                    </button>
                    <button id="confirm-save-invoice-button" onclick="confirmAndSaveInvoice()" class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors">
                        Confirm & Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let cart = [];
        let serviceCharge = 0;
        let packageAdjustment = 0;
        let balanceAdjustment = 0;
        const posClientName = @json($client->name ?? '');
        const posInsuranceName = @json(optional($client->insuranceCompany)->name ?? '');

        // Payment responsibility information
        @if($client->insurance_company_id && ($client->has_deductible || $client->copay_amount || $client->coinsurance_percentage))
        const paymentResponsibility = {
            hasDeductible: {{ $client->has_deductible ? 'true' : 'false' }},
            deductibleAmount: {{ $client->deductible_amount ?? 0 }},
            deductibleUsed: 0, // TODO: Calculate from invoices/payments
            deductibleRemaining: {{ $client->deductible_amount ?? 0 }},
            copayAmount: {{ $client->copay_amount ?? 0 }},
            copayMaxLimit: {{ $client->copay_max_limit ?? 0 }},
            coinsurancePercentage: {{ $client->coinsurance_percentage ?? 0 }},
            copayContributesToDeductible: {{ $client->copay_contributes_to_deductible ? 'true' : 'false' }},
            coinsuranceContributesToDeductible: {{ $client->coinsurance_contributes_to_deductible ? 'true' : 'false' }}
        };
        
        // Calculate deductible used from client's payment history (no-op if Payment Responsibility section not on page)
        async function calculateDeductibleUsed() {
            if (!document.getElementById('deductible-card-container')) return;
            try {
                const response = await fetch(`/api/v1/clients/{{ $client->id }}/deductible-used`, {
                    method: 'GET',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data.success && data.deductible_used !== undefined) {
                        paymentResponsibility.deductibleUsed = parseFloat(data.deductible_used) || 0;
                        paymentResponsibility.deductibleRemaining = Math.max(0, paymentResponsibility.deductibleAmount - paymentResponsibility.deductibleUsed);
                        updateDeductibleDisplay();

                        console.log('Deductible updated:', {
                            used: paymentResponsibility.deductibleUsed,
                            remaining: paymentResponsibility.deductibleRemaining,
                            total: paymentResponsibility.deductibleAmount
                        });
                    }
                    } else {
                        console.error('Failed to fetch deductible used:', response.status, response.statusText);
                        // Fail closed: assume full deductible is remaining if API fails
                        paymentResponsibility.deductibleRemaining = paymentResponsibility.deductibleAmount;
                        paymentResponsibility.deductibleUsed = 0;
                    }
            } catch (error) {
                console.error('Error calculating deductible used:', error);
                // Fail closed: assume full deductible is remaining if API fails
                paymentResponsibility.deductibleRemaining = paymentResponsibility.deductibleAmount;
                paymentResponsibility.deductibleUsed = 0;
            }
        }

        // Refresh deductible status after payment (when returning from payment page)
        @if(session('success'))
        @php
            $successMessage = session('success');
            $isDeductiblePayment = stripos($successMessage, 'deductible') !== false;
            $isCopayPayment = stripos($successMessage, 'copay') !== false || stripos($successMessage, 'co-pay') !== false;
        @endphp
        @if($isDeductiblePayment)
        setTimeout(() => {
            calculateDeductibleUsed();
        }, 500);
        @endif
        @if($isCopayPayment && $client->copay_amount)
        setTimeout(() => {
            checkCopayStatus();
        }, 500);
        @endif
        @endif

        function updateDeductibleDisplay() {
            const deductibleUsedEl = document.getElementById('deductible-used');
            const deductibleRemainingEl = document.getElementById('deductible-remaining');
            const deductibleStatusBadge = document.getElementById('deductible-status-badge');
            const deductibleActionText = document.getElementById('deductible-action-text');
            const deductiblePayLink = document.getElementById('deductible-pay-link');
            const deductibleDetailsDisplay = document.getElementById('deductible-details-display');

            // Update "met" display elements
            const deductibleUsedMetEl = document.getElementById('deductible-used-met');
            const deductibleRemainingMetEl = document.getElementById('deductible-remaining-met');
            const deductibleStatusBadgeMet = document.getElementById('deductible-status-badge-met');

            if (deductibleUsedEl && deductibleRemainingEl && deductibleStatusBadge) {
                deductibleUsedEl.textContent = `UGX ${paymentResponsibility.deductibleUsed.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                deductibleRemainingEl.textContent = `UGX ${paymentResponsibility.deductibleRemaining.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

                if (paymentResponsibility.deductibleRemaining <= 0) {
                    // Deductible is met - show details display, hide payment link
                    deductibleStatusBadge.textContent = 'Met';
                    deductibleStatusBadge.className = 'px-3 py-1 text-xs font-medium rounded-full bg-green-200 text-green-800';

                    // Update met display
                    if (deductibleUsedMetEl) {
                        deductibleUsedMetEl.textContent = `UGX ${paymentResponsibility.deductibleUsed.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                    }
                    if (deductibleRemainingMetEl) {
                        deductibleRemainingMetEl.textContent = `UGX ${paymentResponsibility.deductibleRemaining.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                    }

                    // Hide payment link, show details display
                    if (deductiblePayLink) {
                        deductiblePayLink.classList.add('hidden');
                    }
                    if (deductibleDetailsDisplay) {
                        deductibleDetailsDisplay.classList.remove('hidden');
                    }
                    if (deductibleActionText) {
                        deductibleActionText.textContent = '✓ Fully Paid';
                        deductibleActionText.classList.remove('text-yellow-600');
                        deductibleActionText.classList.add('text-green-600');
                    }
                } else {
                    // Deductible not met - show payment link, hide details display
                    deductibleStatusBadge.textContent = 'Not Met';
                    deductibleStatusBadge.className = 'px-3 py-1 text-xs font-medium rounded-full bg-yellow-200 text-yellow-800';

                    // Show payment link, hide details display
                    if (deductiblePayLink) {
                        deductiblePayLink.classList.remove('hidden');
                    }
                    if (deductibleDetailsDisplay) {
                        deductibleDetailsDisplay.classList.add('hidden');
                    }
                    if (deductibleActionText) {
                        deductibleActionText.textContent = 'Click to pay →';
                        deductibleActionText.classList.remove('text-green-600');
                        deductibleActionText.classList.add('text-yellow-600');
                    }
                }
            }
        }

        function calculatePaymentResponsibility(cartTotal) {
            let copayPayment = 0;
            let deductiblePayment = 0;
            let coinsurancePayment = 0;
            const copayContributes = paymentResponsibility.copayContributesToDeductible === true;

            // Co-pay is required per visit when configured
            if (paymentResponsibility.copayAmount > 0) {
                copayPayment = paymentResponsibility.copayAmount;
                if (paymentResponsibility.copayMaxLimit > 0 && copayPayment > paymentResponsibility.copayMaxLimit) {
                    copayPayment = paymentResponsibility.copayMaxLimit;
                }
            }

            // Deductible: remaining annual deductible applied to this cart
            if (paymentResponsibility.hasDeductible && paymentResponsibility.deductibleRemaining > 0) {
                if (copayContributes) {
                    // Co-pay counts toward meeting the deductible — only the remainder is additional deductible on this visit.
                    const deductibleRemainingAfterCopay = Math.max(0, paymentResponsibility.deductibleRemaining - copayPayment);
                    deductiblePayment = Math.min(deductibleRemainingAfterCopay, cartTotal);
                } else {
                    // Co-pay and deductible are separate — stack both toward client responsibility.
                    deductiblePayment = Math.min(paymentResponsibility.deductibleRemaining, cartTotal);
                }
            }

            // Co-insurance: percentage of allowed amount after deductible (POS estimate)
            if (paymentResponsibility.coinsurancePercentage > 0 && cartTotal > 0) {
                const amountAfterDeductible = Math.max(0, cartTotal - deductiblePayment);
                coinsurancePayment = (amountAfterDeductible * paymentResponsibility.coinsurancePercentage) / 100;
            }

            return {
                copay: copayPayment,
                deductible: deductiblePayment,
                coinsurance: coinsurancePayment,
                total: copayPayment + deductiblePayment + coinsurancePayment
            };
        }

        function updatePaymentResponsibilitySummary() {
            const summaryDiv = document.getElementById('payment-responsibility-summary');
            if (!summaryDiv) return;

            const cartTotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const payments = calculatePaymentResponsibility(cartTotal);

            // Show/hide payment lines
            const copayLine = document.getElementById('copay-payment-line');
            const deductibleLine = document.getElementById('deductible-payment-line');
            const coinsuranceLine = document.getElementById('coinsurance-payment-line');

            if (payments.copay > 0) {
                document.getElementById('copay-amount-display').textContent = payments.copay.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                if (copayLine) copayLine.style.display = 'flex';
            } else {
                if (copayLine) copayLine.style.display = 'none';
            }

            if (payments.deductible > 0) {
                document.getElementById('deductible-payment-display').textContent = payments.deductible.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                if (deductibleLine) deductibleLine.style.display = 'flex';
            } else {
                if (deductibleLine) deductibleLine.style.display = 'none';
            }

            if (payments.coinsurance > 0) {
                document.getElementById('coinsurance-amount-display').textContent = payments.coinsurance.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                if (coinsuranceLine) coinsuranceLine.style.display = 'flex';
            } else {
                if (coinsuranceLine) coinsuranceLine.style.display = 'none';
            }

            // Update total
            document.getElementById('total-client-payment').textContent = payments.total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

            // Show summary if there's any payment required
            if (payments.total > 0) {
                summaryDiv.style.display = 'block';
            } else {
                summaryDiv.style.display = 'none';
            }
        }

        // Initialize deductible calculation on page load
        @if($client->has_deductible && $client->deductible_amount)
        calculateDeductibleUsed();
        @endif
        
        // Check co-pay status on page load (no-op if Payment Responsibility section not on page)
        @if($client->copay_amount)
        async function checkCopayStatus() {
            if (!document.getElementById('copay-card-container')) return;
            try {
                const response = await fetch(`/api/v1/clients/{{ $client->id }}/copay-status`, {
                    method: 'GET',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data.success && data.copay_paid !== undefined) {
                        updateCopayDisplay(data.copay_paid, data.copay_paid_amount || 0);

                        console.log('Co-pay status updated:', {
                            paid: data.copay_paid,
                            paid_amount: data.copay_paid_amount,
                            required: data.copay_amount
                        });
                    }
                } else {
                    console.error('Failed to fetch co-pay status:', response.status, response.statusText);
                }
            } catch (error) {
                console.error('Error checking co-pay status:', error);
            }
        }

        function updateCopayDisplay(isPaid, paidAmount) {
            const copayPayLink = document.getElementById('copay-pay-link');
            const copayDetailsDisplay = document.getElementById('copay-details-display');
            const copayActionText = document.getElementById('copay-action-text');
            const copayStatusBadge = document.getElementById('copay-status-badge');
            const copayPaidAmountEl = document.getElementById('copay-paid-amount');
            const copayStatusBadgePaid = document.getElementById('copay-status-badge-paid');

            if (isPaid) {
                // Co-pay is paid - show details display, hide payment link
                if (copayPayLink) {
                    copayPayLink.classList.add('hidden');
                }
                if (copayDetailsDisplay) {
                    copayDetailsDisplay.classList.remove('hidden');
                }
                if (copayPaidAmountEl) {
                    copayPaidAmountEl.textContent = `UGX ${paidAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                }
            } else {
                // Co-pay not paid - show payment link, hide details display
                if (copayPayLink) {
                    copayPayLink.classList.remove('hidden');
                }
                if (copayDetailsDisplay) {
                    copayDetailsDisplay.classList.add('hidden');
                }
                if (copayStatusBadge) {
                    copayStatusBadge.textContent = 'Required';
                    copayStatusBadge.className = 'px-3 py-1 text-xs font-medium rounded-full bg-blue-200 text-blue-800';
                }
            }
        }

        checkCopayStatus();
        @endif

        // Refresh co-pay status after payment
        @if(session('success'))
        @php
            $successMessage = session('success');
            $isCopayPayment = stripos($successMessage, 'copay') !== false || stripos($successMessage, 'co-pay') !== false;
        @endphp
        @if($isCopayPayment)
        setTimeout(() => {
            checkCopayStatus();
        }, 500);
        @endif
        @endif

        // Check payment responsibility requirements
        async function checkPaymentResponsibilityRequirements() {
            @if($client->insurance_company_id && ($client->has_deductible || $client->copay_amount))
            try {
                // Get current deductible status
                let deductibleRemaining = paymentResponsibility.deductibleRemaining;
                let copayRequired = paymentResponsibility.copayAmount > 0;

                // Check deductible
                if (paymentResponsibility.hasDeductible && deductibleRemaining > 0) {
                    return {
                        allowed: false,
                        unpaidType: 'deductible',
                        message: `
                            <div class=\"text-left\">
                                <p class=\"mb-2\">Deductible payment is required before placing orders.</p>
                                <p class=\"text-sm text-gray-600 mb-3\">
                                    <strong>Remaining Deductible:</strong> UGX ${deductibleRemaining.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                                </p>
                                <p class=\"text-sm text-gray-600\">Please pay the deductible amount to continue.</p>
                            </div>
                        `
                    };
                }

                // Check co-pay (if collection is immediate)
                @if(($collectionTiming ?? 'immediate') === 'immediate')
                if (copayRequired) {
                    // Check if co-pay has been paid for this visit
                    try {
                        const copayResponse = await fetch(`/api/v1/clients/{{ $client->id }}/copay-status`, {
                            method: 'GET',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').getAttribute('content'),
                                'Accept': 'application/json',
                            }
                        });

                        if (copayResponse.ok) {
                            const copayData = await copayResponse.json();
                            if (copayData.success && copayData.copay_required && !copayData.copay_paid) {
                                return {
                                    allowed: false,
                                    unpaidType: 'copay',
                                    message: `
                                        <div class=\"text-left\">
                                            <p class=\"mb-2\">Co-pay payment is required before placing orders.</p>
                                            <p class=\"text-sm text-gray-600 mb-3\">
                                                <strong>Co-pay Amount:</strong> UGX ${copayData.copay_amount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} per visit
                                            </p>
                                            <p class=\"text-sm text-gray-600\">Please pay the co-pay amount to continue.</p>
                                        </div>
                                    `
                                };
                            }
                        }
                    } catch (error) {
                        console.error('Error checking co-pay status:', error);
                        // If check fails, still block order to be safe
                        return {
                            allowed: false,
                            unpaidType: 'copay',
                            message: `
                                <div class=\"text-left\">
                                    <p class=\"mb-2\">Co-pay payment verification failed.</p>
                                    <p class=\"text-sm text-gray-600\">Please ensure co-pay has been paid before placing orders.</p>
                                </div>
                            `
                        };
                    }
                }
                @endif

                return {
                    allowed: true,
                    message: 'All payment requirements met.'
                };
            } catch (error) {
                console.error('Error checking payment requirements:', error);
                // FAIL CLOSED: Block order if we can't verify payment status
                // This prevents service when payment verification is unavailable
                return {
                    allowed: false,
                    unpaidType: 'verification_error',
                    message: `
                        <div class=\"text-left\">
                            <p class=\"mb-2 text-red-600 font-semibold\">⚠️ Payment Verification Unavailable</p>
                            <p class=\"text-sm text-gray-600 mb-3\">
                                Unable to verify payment status. Please ensure all required payments (deductible/co-pay) have been completed before placing orders.
                            </p>
                            <p class=\"text-sm text-gray-500\">
                                If you have already made payments, please wait a moment and try again, or contact support.
                            </p>
                        </div>
                    `
                };
            }
            @else
            return {
                allowed: true,
                message: 'No payment requirements.'
            };
            @endif
        }
        @endif

        // Inline payment flow for deductible + co-pay without leaving POS page
        @if($client->insurance_company_id && ($client->has_deductible || $client->copay_amount))
        async function payClientResponsibilitiesInline() {
            try {
                // Refresh latest deductible info
                await calculateDeductibleUsed();

                let deductibleRemaining = paymentResponsibility.hasDeductible
                    ? (paymentResponsibility.deductibleRemaining || 0)
                    : 0;

                // Fetch latest co-pay status
                let copayAmountDue = 0;
                @if(($collectionTiming ?? 'immediate') === 'immediate' && $client->copay_amount)
                try {
                    const copayResponse = await fetch(`/api/v1/clients/{{ $client->id }}/copay-status`, {
                        method: 'GET',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').getAttribute('content'),
                            'Accept': 'application/json',
                        }
                    });

                    if (copayResponse.ok) {
                        const copayData = await copayResponse.json();
                        if (copayData.success && copayData.copay_required && !copayData.copay_paid) {
                            copayAmountDue = parseFloat(copayData.copay_amount) || 0;
                        }
                    }
                } catch (error) {
                    console.error('Error fetching co-pay status for inline payment:', error);
                }
                @endif

                // If nothing is due, just show info and exit
                if (deductibleRemaining <= 0 && copayAmountDue <= 0) {
                    await Swal.fire({
                        icon: 'info',
                        title: 'No Payment Required',
                        text: 'All deductible and co-pay requirements are already met for this visit.',
                    });
                    return;
                }

                const totalClientPayment = (deductibleRemaining > 0 ? deductibleRemaining : 0) + (copayAmountDue > 0 ? copayAmountDue : 0);

                // Ask for payment method and (optional) mobile money phone
                const { value: formValues } = await Swal.fire({
                    title: 'Pay Client Responsibilities',
                    html: `
                        <div class=\"text-left space-y-3\">
                            <div>
                                <p class=\"text-sm font-semibold text-gray-800 mb-1\">Amounts to pay now</p>
                                <ul class=\"text-sm text-gray-700 list-disc ml-5\">
                                    ${deductibleRemaining > 0 ? `<li>Deductible (remaining): UGX ${deductibleRemaining.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</li>` : ''}
                                    ${copayAmountDue > 0 ? `<li>Co-pay (this visit): UGX ${copayAmountDue.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</li>` : ''}
                                </ul>
                                <p class=\"mt-2 text-sm font-bold text-gray-900\">
                                    Total Client Payment Now: UGX ${totalClientPayment.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                                </p>
                            </div>
                            <div>
                                <label class=\"block text-sm font-medium text-gray-700 mb-1\" for=\"pr-payment-method\">
                                    Payment Method <span class=\"text-red-500\">*</span>
                                </label>
                                <select id=\"pr-payment-method\" class=\"swal2-input\" style=\"width: 100%; box-sizing: border-box;\">
                                    <option value=\"cash\">Cash</option>
                                    <option value=\"mobile_money\">Mobile Money</option>
                                </select>
                            </div>
                            <div id=\"pr-payment-phone-wrapper\" style=\"display: none;\">
                                <label class=\"block text-sm font-medium text-gray-700 mb-1\" for=\"pr-payment-phone\">
                                    Payment Phone (Mobile Money)
                                </label>
                                <input id=\"pr-payment-phone\" class=\"swal2-input\" placeholder=\"e.g., +256701234567\" value=\"{{ $client->payment_phone_number ?? '' }}\" />
                                <p class=\"mt-1 text-xs text-gray-500\">
                                    Required when paying via mobile money.
                                </p>
                            </div>
                        </div>
                    `,
                    focusConfirm: false,
                    showCancelButton: true,
                    confirmButtonText: 'Process Payment',
                    cancelButtonText: 'Cancel',
                    didOpen: () => {
                        const methodSelect = Swal.getPopup().querySelector('#pr-payment-method');
                        const phoneWrapper = Swal.getPopup().querySelector('#pr-payment-phone-wrapper');
                        methodSelect.addEventListener('change', () => {
                            if (methodSelect.value === 'mobile_money') {
                                phoneWrapper.style.display = 'block';
                            } else {
                                phoneWrapper.style.display = 'none';
                            }
                        });
                    },
                    preConfirm: () => {
                        const method = Swal.getPopup().querySelector('#pr-payment-method').value;
                        const phone = Swal.getPopup().querySelector('#pr-payment-phone').value;

                        if (!method) {
                            Swal.showValidationMessage('Please select a payment method');
                            return null;
                        }
                        if (method === 'mobile_money' && !phone) {
                            Swal.showValidationMessage('Please enter a payment phone number for mobile money');
                            return null;
                        }

                        return { method, phone };
                    }
                });

                if (!formValues) {
                    // User cancelled
                    return;
                }

                const paymentMethod = formValues.method;
                const paymentPhone = formValues.phone || '';
                const csrfToken = document.querySelector('meta[name=\"csrf-token\"]').getAttribute('content');
                const url = '{{ route("payment-responsibility.process", $client) }}';

                // Helper to send one payment
                const sendPayment = async (type, amount) => {
                    const formData = new FormData();
                    formData.append('_token', csrfToken);
                    formData.append('type', type);
                    formData.append('amount', amount);
                    formData.append('payment_method', paymentMethod);
                    if (paymentPhone) {
                        formData.append('payment_phone', paymentPhone);
                    }

                    const response = await fetch(url, {
                        method: 'POST',
                        body: formData,
                    });

                    if (!response.ok) {
                        const text = await response.text();
                        throw new Error('Payment failed (' + type + '): ' + text);
                    }
                };

                // Process deductible and co-pay in one inline flow (two backend payments)
                if (deductibleRemaining > 0) {
                    await sendPayment('deductible', deductibleRemaining);
                }
                if (copayAmountDue > 0) {
                    await sendPayment('copay', copayAmountDue);
                }

                // Refresh UI status
                await calculateDeductibleUsed();
                @if($client->copay_amount)
                await checkCopayStatus();
                @endif

                await Swal.fire({
                    icon: 'success',
                    title: 'Payment Processed',
                    text: 'Client payment responsibilities have been processed. You can now confirm and save the invoice.',
                });
            } catch (error) {
                console.error('Error during inline payment responsibility flow:', error);
                await Swal.fire({
                    icon: 'error',
                    title: 'Payment Failed',
                    text: 'We were unable to process the payment responsibilities. Please try again or contact support.',
                });
            }
        }
        @endif

        // Credit information for credit clients
        @if($client->is_credit_eligible)
            @php
                $creditLimit = $client->max_credit ?? 0;
                $availableBalance = $client->available_balance ?? 0;
                $amountOwed = $availableBalance < 0 ? abs($availableBalance) : 0;
                $creditRemaining = max(0, $creditLimit - $amountOwed);
            @endphp
            const isCreditClient = true;
            const creditLimit = {{ $creditLimit }};
            const currentCreditRemaining = {{ $creditRemaining }};
        @else
            const isCreditClient = false;
            const creditLimit = 0;
            const currentCreditRemaining = 0;
        @endif
        // Flag to indicate if this client is an insurance client.
        // We use this to ensure no automatic payments are triggered
        // before the invoice is saved for insurance clients.
        const isInsuranceClient = {{ ($client->insurance_company_id || $client->vendors()->exists()) ? 'true' : 'false' }};
        
        // Add event listeners to quantity inputs
        document.addEventListener('DOMContentLoaded', function() {
            // Check initial payment methods for mobile money
            const initialPaymentMethods = @json($client->payment_methods ?? []);
            if (initialPaymentMethods.includes('mobile_money')) {
                document.getElementById('payment-phone-section').style.display = 'block';
            }

            // Handle third-party payer section visibility for credit clients with insurance
            @if($client->is_credit_eligible)
            const insuranceCheckbox = document.querySelector('input[name="payment_methods[]"][value="insurance"]');
            if (insuranceCheckbox) {
                insuranceCheckbox.addEventListener('change', function() {
                    const thirdPartyPayerSection = document.getElementById('third-party-payer-section');
                    const thirdPartyPayerSelect = document.getElementById('third-party-payer-select');

                    if (this.checked && thirdPartyPayerSection) {
                        thirdPartyPayerSection.classList.remove('hidden');
                        if (thirdPartyPayerSelect) {
                            thirdPartyPayerSelect.required = true;
                        }
                    } else if (thirdPartyPayerSection) {
                        thirdPartyPayerSection.classList.add('hidden');
                        if (thirdPartyPayerSelect) {
                            thirdPartyPayerSelect.required = false;
                            thirdPartyPayerSelect.value = '';
                        }
                    }
                });
            }
            @endif

            const quantityInputs = document.querySelectorAll('.quantity-input');
            quantityInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const itemId = this.dataset.itemId;
                    const itemName = this.dataset.itemName;
                    const itemDisplayName = this.dataset.itemDisplayName;
                    const rawPrice = this.dataset.itemPrice;
                    const itemPrice = parseFloat(rawPrice) || 0;
                    const quantity = parseInt(this.value) || 0;
                    const itemType = this.dataset.itemType || 'N/A';

                    // Debug logging
                    console.log('=== PRICING DEBUG ===');
                    console.log('Item:', itemName);
                    console.log('Raw Price (data-item-price):', rawPrice);
                    console.log('Raw Price Type:', typeof rawPrice);
                    console.log('Parsed Price:', itemPrice);
                    console.log('Parsed Price Type:', typeof itemPrice);
                    console.log('Quantity:', quantity);
                    console.log('Type:', itemType);
                    console.log('Data attributes:', {
                        itemId: this.dataset.itemId,
                        itemPrice: this.dataset.itemPrice,
                        itemName: this.dataset.itemName
                    });

                    if (quantity > 0) {
                        addToCart(itemId, itemName, itemPrice, quantity, itemType, itemDisplayName);
                        // Don't reset the input value - keep the state
                    } else if (quantity === 0) {
                        // Remove item from cart if quantity is 0
                        removeFromCartByItemId(itemId);
                    }
                });
            });

            // Add search functionality
            const searchInput = document.getElementById('search-input');
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const itemRows = document.querySelectorAll('.item-row');

                itemRows.forEach(row => {
                    const itemName = row.dataset.itemName;
                    const itemOtherNames = row.dataset.itemOtherNames;

                    // Search in both item name and other names
                    if (itemName.includes(searchTerm) || itemOtherNames.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });

            // Add price and description display toggle functionality
            const showPricesCheckbox = document.getElementById('show-prices');
            const showDescriptionsCheckbox = document.getElementById('show-descriptions');
            const priceElements = document.querySelectorAll('.price-display');
            const descriptionElements = document.querySelectorAll('.description-display');

            // Handle prices checkbox
            if (showPricesCheckbox) {
            showPricesCheckbox.addEventListener('change', function() {
                    console.log('Show prices checkbox changed:', this.checked);
                priceElements.forEach(element => {
                    element.style.display = this.checked ? 'block' : 'none';
                });
            });

                // Initialize price display state
                priceElements.forEach(element => {
                    element.style.display = showPricesCheckbox.checked ? 'block' : 'none';
                });
            } else {
                console.error('Show prices checkbox not found!');
            }

            // Handle descriptions checkbox
            if (showDescriptionsCheckbox) {
                showDescriptionsCheckbox.addEventListener('change', function() {
                    console.log('Show descriptions checkbox changed:', this.checked);
                    descriptionElements.forEach(element => {
                        element.style.display = this.checked ? 'block' : 'none';
                    });
                });

                // Initialize description display state (hidden by default)
                descriptionElements.forEach(element => {
                    element.style.display = showDescriptionsCheckbox.checked ? 'block' : 'none';
                });
            } else {
                console.error('Show descriptions checkbox not found!');
            }

        });

        // Show client confirmation modal on page load
        document.addEventListener('DOMContentLoaded', function() {
            showClientConfirmation();
        });

        function showClientConfirmation() {
            document.getElementById('client-confirmation-modal').classList.remove('hidden');
        }

        function closeClientConfirmation() {
            document.getElementById('client-confirmation-modal').classList.add('hidden');
        }

        function printClientDetails() {
            // Create a print-friendly version of client details
            const printWindow = window.open('', '_blank');
            const modalContent = document.querySelector('#client-confirmation-modal .relative').cloneNode(true);

            // Remove action buttons from print version
            const actionButtons = modalContent.querySelector('.bg-gray-100');
            if (actionButtons) {
                actionButtons.remove();
            }

            // Add print-specific styling
            const printStyle = document.createElement('style');
            printStyle.textContent = `
                body { font-family: Arial, sans-serif; }
                .bg-blue-600 { background-color: #2563eb !important; color: white !important; }
                .text-blue-600 { color: #2563eb !important; }
                .border { border: 1px solid #d1d5db !important; }
                .p-4 { padding: 1rem !important; }
                .mb-4 { margin-bottom: 1rem !important; }
                .text-center { text-align: center !important; }
                .font-bold { font-weight: bold !important; }
                .text-sm { font-size: 0.875rem !important; }
                .text-gray-600 { color: #4b5563 !important; }
            `;

            printWindow.document.head.appendChild(printStyle);
            printWindow.document.body.appendChild(modalContent);

            printWindow.document.title = 'Client Details - Aziz';
            printWindow.print();
        }

        function addToCart(itemId, itemName, itemPrice, quantity, itemType, displayName) {
            // Update payment responsibility when cart changes
            setTimeout(() => {
                @if($client->insurance_company_id && ($client->has_deductible || $client->copay_amount || $client->coinsurance_percentage))
                updatePaymentResponsibilitySummary();
                @endif
            }, 100);
            // Ensure proper number types
            const price = parseFloat(itemPrice) || 0;
            const qty = parseInt(quantity) || 0;

            // Check if item already exists in cart
            const existingItem = cart.find(item => item.id === itemId);
            if (existingItem) {
                existingItem.quantity = qty; // Update quantity instead of adding
            } else {
                cart.push({
                    id: itemId,
                    name: itemName,
                    displayName: displayName,
                    price: price,
                    quantity: qty,
                    type: itemType || 'N/A'
                });
            }

            updateRequestOrderSummaryDisplay();
        }

        function removeFromCartByItemId(itemId) {
            cart = cart.filter(item => item.id !== itemId);
            updateRequestOrderSummaryDisplay();
        }

        function updateRequestOrderSummaryDisplay() {
            // Update payment responsibility when cart display updates
            @if($client->insurance_company_id && ($client->has_deductible || $client->copay_amount || $client->coinsurance_percentage))
            updatePaymentResponsibilitySummary();
            @endif
            const requestOrderSummaryContainer = document.getElementById('request-order-summary-items');
            const totalItemsSpan = document.getElementById('total-items');
            const totalQuantitySpan = document.getElementById('total-quantity');
            const totalAmountSpan = document.getElementById('total-amount');

            if (cart.length === 0) {
                requestOrderSummaryContainer.innerHTML = '<div class="px-4 py-8"><p class="text-sm text-gray-500 text-center">No items selected</p></div>';
                totalItemsSpan.textContent = '0';
                totalQuantitySpan.textContent = '0';
                totalAmountSpan.textContent = 'UGX 0.00';
                return;
            }

            let requestOrderSummaryHTML = '';
            let totalItems = 0;
            let totalQuantity = 0;
            let totalAmount = 0;

            cart.forEach((item, index) => {
                const itemTotal = parseFloat(item.price || 0) * parseInt(item.quantity || 0);
                totalItems += 1; // Count unique items
                totalQuantity += parseInt(item.quantity || 0); // Sum of all quantities
                totalAmount += itemTotal;

                requestOrderSummaryHTML += `
                    <div class="px-4 py-3">
                        <div class="grid grid-cols-4 gap-4 items-center">
                            <div>
                                <span class="text-sm text-gray-900 font-medium">${item.displayName || item.name}</span>
                            </div>
                            <div class="text-center">
                                <span class="text-sm text-gray-900">${item.quantity}</span>
                            </div>
                            <div class="text-right">
                                <span class="text-sm text-blue-600">UGX ${(item.price || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                            </div>
                            <div class="text-right">
                                <button class="text-red-500 hover:text-red-700 text-sm" onclick="removeFromCart(${index})">
                                    Remove
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });

            requestOrderSummaryContainer.innerHTML = requestOrderSummaryHTML;
            totalItemsSpan.textContent = totalItems; // This now shows total quantity of all items
            totalQuantitySpan.textContent = totalQuantity; // This now shows total quantity of all items
            totalAmountSpan.textContent = `UGX ${parseFloat(totalAmount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

            // Update credit remaining display if client is credit eligible
            if (isCreditClient) {
                updateCreditRemainingDisplay(totalAmount);
            }
        }

        function updateCreditRemainingDisplay(cartTotal) {
            if (!isCreditClient) return;

            const creditRemainingDisplay = document.getElementById('credit-remaining-display');
            if (!creditRemainingDisplay) return;

            // Calculate estimated new credit remaining (approximate, before service charges and adjustments)
            // This gives a rough indication, final validation happens on purchase
            const currentAmountOwed = Math.max(0, -parseFloat({{ $client->available_balance ?? 0 }}));
            const estimatedNewOwed = currentAmountOwed + cartTotal;
            const estimatedCreditRemaining = Math.max(0, creditLimit - estimatedNewOwed);

            // Update display
            creditRemainingDisplay.textContent = `UGX ${estimatedCreditRemaining.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

            // Update color based on remaining credit
            if (estimatedCreditRemaining <= 0) {
                creditRemainingDisplay.className = 'text-lg font-bold text-red-600';
                // Show warning message
                const warningMsg = creditRemainingDisplay.parentElement.querySelector('.text-xs.text-red-500');
                if (!warningMsg) {
                    const warning = document.createElement('p');
                    warning.className = 'text-xs text-red-500 mt-1';
                    warning.textContent = '(Credit Limit Will Be Exceeded)';
                    creditRemainingDisplay.parentElement.appendChild(warning);
                }
            } else {
                creditRemainingDisplay.className = 'text-lg font-bold text-green-600';
                // Remove warning if exists
                const warningMsg = creditRemainingDisplay.parentElement.querySelector('.text-xs.text-red-500');
                if (warningMsg && warningMsg.textContent.includes('Will Be Exceeded')) {
                    warningMsg.remove();
                }
            }
        }

        function removeFromCart(index) {
            const removedItem = cart[index];
            cart.splice(index, 1);
            // Update payment responsibility when cart changes
            setTimeout(() => {
                @if($client->insurance_company_id && ($client->has_deductible || $client->copay_amount || $client->coinsurance_percentage))
                updatePaymentResponsibilitySummary();
                @endif
            }, 100);

            // Reset the corresponding quantity input to 0
            const quantityInput = document.querySelector(`input[data-item-id="${removedItem.id}"]`);
            if (quantityInput) {
                quantityInput.value = 0;
            }

            updateRequestOrderSummaryDisplay();
        }

        // Client confirmation functions - defined globally
        function showClientConfirmation() {
            document.getElementById('client-confirmation-modal').classList.remove('hidden');
        }

        function closeClientConfirmation() {
            document.getElementById('client-confirmation-modal').classList.add('hidden');
        }

        function printClientDetails() {
            // Create a print-friendly version of client details
            const printWindow = window.open('', '_blank');
            const modalContent = document.querySelector('#client-confirmation-modal .relative').cloneNode(true);

            // Remove action buttons from print version
            const actionButtons = modalContent.querySelector('.bg-gray-50.px-6.py-4.rounded-b-lg');
            if (actionButtons) {
                actionButtons.remove();
            }

            // Add print styles
            const printStyles = `
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    .bg-gray-50 { background-color: #f9fafb; }
                    .text-gray-800 { color: #1f2937; }
                    .text-gray-600 { color: #4b5563; }
                    .text-sm { font-size: 14px; }
                    .text-lg { font-size: 18px; }
                    .font-semibold { font-weight: 600; }
                    .text-center { text-align: center; }
                    .grid { display: grid; }
                    .grid-cols-2 { grid-template-columns: repeat(2, 1fr); }
                    .gap-4 { gap: 16px; }
                    .space-y-2 > * + * { margin-top: 8px; }
                    .px-6 { padding-left: 24px; padding-right: 24px; }
                    .py-4 { padding-top: 16px; padding-bottom: 16px; }
                    .mb-4 { margin-bottom: 16px; }
                    .rounded-lg { border-radius: 8px; }
                    .border-b { border-bottom: 1px solid #e5e7eb; }
                    .w-16 { width: 64px; }
                    .h-16 { height: 64px; }
                    .bg-gray-200 { background-color: #e5e7eb; }
                    .border { border: 1px solid #d1d5db; }
                    .flex { display: flex; }
                    .justify-end { justify-content: flex-end; }
                    .items-center { align-items: center; }
                    .justify-center { justify-content: center; }
                    .text-xs { font-size: 12px; }
                    .text-gray-500 { color: #6b7280; }
                    @media print {
                        body { margin: 0; }
                        .no-print { display: none; }
                    }
                </style>
            `;

            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Client Details - ${new Date().toLocaleDateString()}</title>
                ${printStyles}
                </head>
                <body>
                    ${modalContent.outerHTML}
                </body>
                </html>
            `);

            printWindow.document.close();
            printWindow.focus();

            // Wait for content to load then print
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 500);
        }

        async function showInvoicePreview() {
            if (cart.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Empty Cart',
                    text: 'Please add items to cart before previewing invoice'
                });
                return;
            }

            // Generate invoice number
            try {
                const response = await fetch('/invoices/generate-invoice-number', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        business_id: {{ auth()->user()->business->id }}
                    })
                });

                const data = await response.json();
                if (data.invoice_number) {
                    document.getElementById('invoice-number-display').textContent = data.invoice_number;
                } else {
                    document.getElementById('invoice-number-display').textContent = 'Error generating invoice number';
                }
            } catch (error) {
                console.error('Error generating invoice number:', error);
                document.getElementById('invoice-number-display').textContent = 'Error generating invoice number';
            }

            // Populate invoice items table
            const invoiceTable = document.getElementById('invoice-items-table');
            let tableHTML = '';
            let subtotal = 0;

            cart.forEach(item => {
                const itemTotal = (item.price || 0) * (item.quantity || 0);
                subtotal += itemTotal;

                // Don't show tracking numbers for packages in cart (not yet purchased)
                let trackingNumber = 'N/A';
                if (item.type === 'package') {
                    trackingNumber = 'Pending';
                }

                tableHTML += `
                    <tr class="bg-white">
                        <td class="border border-gray-300 px-4 py-2">${item.displayName || item.name}</td>
                        <td class="border border-gray-300 px-4 py-2 text-center">${item.quantity}</td>
                        <td class="border border-gray-300 px-4 py-2 text-right">UGX ${(item.price || 0).toLocaleString()}</td>
                        <td class="border border-gray-300 px-4 py-2 text-right">UGX ${itemTotal.toLocaleString()}</td>

                    </tr>
                `;
            });

            invoiceTable.innerHTML = tableHTML;

            // Calculate package adjustment
            const packageAdjustmentData = await calculatePackageAdjustment();
            const packageAdjustment = packageAdjustmentData.total_adjustment;

            // Show package adjustment details if any adjustments were made
            // Commented out for debugging purposes - package adjustment details section removed
            /*
            if (packageAdjustmentData.details && packageAdjustmentData.details.length > 0) {
                const adjustmentDetailsContainer = document.getElementById('package-adjustment-details');
                const adjustmentList = document.getElementById('package-adjustment-list');

                let detailsHTML = '';
                packageAdjustmentData.details.forEach(detail => {
                    // Use the actual tracking number from the API response
                    const packageTrackingNumber = detail.tracking_number || `PKG-${detail.package_tracking_id}-${Date.now()}`;

                    detailsHTML += `
                        <div class="flex justify-between items-center text-sm">
                            <div>
                                <span class="font-medium text-gray-800">${detail.item_name}</span>
                                <span class="text-gray-600"> (${detail.quantity_adjusted} × UGX ${(detail.adjustment_amount / detail.quantity_adjusted).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})})</span>
                            </div>
                            <div class="text-right">
                                <div class="font-medium text-green-800">-UGX ${detail.adjustment_amount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                                <div class="text-xs text-gray-500">From: ${detail.package_name}</div>
                                <div class="text-xs text-blue-600 font-mono">${packageTrackingNumber}</div>
                            </div>
                        </div>
                    `;
                });

                adjustmentList.innerHTML = detailsHTML;
                adjustmentDetailsContainer.classList.remove('hidden');
            } else {
                document.getElementById('package-adjustment-details').classList.add('hidden');
            }
            */

            // Package Tracking Numbers section removed - was for testing purposes only

            // Calculate balance adjustment first (needed for service charge calculation)
            const balanceAdjustmentData = await calculateBalanceAdjustment(subtotal);
            const balanceAdjustment = balanceAdjustmentData.balance_adjustment;

            // Calculate totals according to correct formula:
            // Subtotal 1 = Sum of all items (already calculated as 'subtotal')
            // Subtotal 2 = Subtotal 1 - Package Adjustment - Account Balance Adjustment
            // Total = Subtotal 2 + Service Charge
            // Service Charge is calculated based on Subtotal 2

            const subtotal1 = parseFloat(subtotal);
            let subtotal2 = subtotal1 - parseFloat(packageAdjustment) - parseFloat(balanceAdjustment);

            // Ensure subtotal2 never goes below 0
            if (subtotal2 < 0) {
                subtotal2 = 0;
            }

            const serviceChargeData = await calculateServiceCharge(subtotal2);
            const serviceCharge = serviceChargeData.amount;
            let finalTotal = subtotal2 + parseFloat(serviceCharge);

            // Ensure final total never goes below 0
            if (finalTotal < 0) {
                finalTotal = 0;
            }

            // Update invoice summary
            document.getElementById('invoice-subtotal').textContent = `UGX ${subtotal1.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

            // Show/hide Package Adjustment based on value
            const packageAdjustmentRow = document.getElementById('package-adjustment-row');
            const packageAdjustmentValue = parseFloat(packageAdjustment);
            if (packageAdjustmentValue !== 0) {
                document.getElementById('package-adjustment-display').textContent = `UGX ${packageAdjustmentValue.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                packageAdjustmentRow.classList.remove('hidden');
            } else {
                packageAdjustmentRow.classList.add('hidden');
            }

            // Show/hide Account Balance Adjustment based on value
            const balanceAdjustmentRow = document.getElementById('balance-adjustment-row');
            const balanceAdjustmentValue = parseFloat(balanceAdjustment);
            if (balanceAdjustmentValue !== 0) {
                document.getElementById('balance-adjustment-display').textContent = `UGX ${balanceAdjustmentValue.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                balanceAdjustmentRow.classList.remove('hidden');
            } else {
                balanceAdjustmentRow.classList.add('hidden');
            }

            document.getElementById('invoice-subtotal-2').textContent = `UGX ${subtotal2.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

            // Display service charge and handle note visibility
            const serviceChargeElement = document.getElementById('service-charge-display');
            const serviceChargeNote = document.getElementById('service-charge-note');

            // Always show the service charge amount
            serviceChargeElement.textContent = `UGX ${parseFloat(serviceCharge).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

            // Show/hide note based on whether service charge ranges exist
            if (serviceChargeData.hasRanges) {
                // Service charge ranges are configured, hide the note
                serviceChargeNote.style.display = 'none';
            } else {
                // No service charge ranges configured, show the note
                serviceChargeNote.style.display = 'block';
            }

            document.getElementById('invoice-final-total').textContent = `UGX ${finalTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

            // Show/hide third-party payer section for credit clients with insurance
            @if($client->is_credit_eligible)
            const thirdPartyPayerSection = document.getElementById('third-party-payer-section');
            const insuranceCheckbox = document.querySelector('input[name="payment_methods[]"][value="insurance"]');
            if (insuranceCheckbox && insuranceCheckbox.checked && thirdPartyPayerSection) {
                thirdPartyPayerSection.classList.remove('hidden');
                // Make the select required
                document.getElementById('third-party-payer-select').required = true;
            } else if (thirdPartyPayerSection) {
                thirdPartyPayerSection.classList.add('hidden');
                document.getElementById('third-party-payer-select').required = false;
            }
            @endif

            // Show modal
            document.getElementById('invoice-modal').classList.remove('hidden');
        }

        function closeInvoicePreview() {
            document.getElementById('invoice-modal').classList.add('hidden');

            // Reset service charge display to default state
            const serviceChargeElement = document.getElementById('service-charge-display');
            const serviceChargeNote = document.getElementById('service-charge-note');
            if (serviceChargeElement && serviceChargeNote) {
                serviceChargeElement.textContent = 'UGX 0.00';
                serviceChargeNote.style.display = 'none'; // Keep the note hidden
            }

            // Reset package tracking numbers
            if (window.packageTrackingNumbers) {
                window.packageTrackingNumbers.clear();
            }


        }

        async function confirmAndSaveInvoice() {
            if (cart.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Empty Cart',
                    text: 'Please add items to cart before saving invoice'
                });
                return;
            }

            // Determine if this invoice only contains deposit items
            const isDepositOnlyInvoice = cart.length > 0 && cart.every(item => {
                const rawName = (item.displayName || item.name || '').toString().trim().toLowerCase();
                return rawName === 'deposit';
            });

            const hasDeposit = cart.some(item => {
                const rawName = (item.displayName || item.name || '').toString().trim().toLowerCase();
                return rawName === 'deposit';
            });

            if (hasDeposit && !isDepositOnlyInvoice) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Deposit Invoice',
                    text: 'A deposit must be the only item on the invoice. Please remove other items.',
                });
                return;
            }

            const serviceChargeNote = document.getElementById('service-charge-note');
            const isServiceChargeNotConfigured = serviceChargeNote
                ? window.getComputedStyle(serviceChargeNote).display !== 'none'
                : false;

            // Confirm with SweetAlert2
            const result = await Swal.fire({
                title: 'Confirm Proforma Invoice',
                text: 'Are you sure you want to save this proforma invoice?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, save it!',
                cancelButtonText: 'Cancel'
            });

            if (!result.isConfirmed) {
                return;
            }
            
            // Show loading state - find the button by ID since event is not available
            const button = document.getElementById('confirm-save-invoice-button');
            const originalText = button ? button.textContent : 'Save';
            if (button) {
                button.textContent = 'Saving...';
                button.disabled = true;
            }

            try {
                // Calculate totals
                let subtotal = 0;
                cart.forEach(item => {
                    subtotal += parseFloat(item.price || 0) * parseInt(item.quantity || 0);
                });

                const packageAdjustmentData = await calculatePackageAdjustment();
                const packageAdjustment = parseFloat(packageAdjustmentData.total_adjustment) || 0;
                let adjustedSubtotal = parseFloat(subtotal) - parseFloat(packageAdjustment);

                // Ensure adjustedSubtotal never goes below 0
                if (adjustedSubtotal < 0) {
                    adjustedSubtotal = 0;
                }

                const serviceChargeData = await calculateServiceCharge(adjustedSubtotal);
                const serviceCharge = serviceChargeData.amount;

                const subtotalWithServiceCharge = parseFloat(adjustedSubtotal) + parseFloat(serviceCharge);

                // Calculate balance adjustment
                const balanceAdjustmentData = await calculateBalanceAdjustment(subtotalWithServiceCharge);
                const balanceAdjustment = parseFloat(balanceAdjustmentData.balance_adjustment) || 0;
                let totalAmount = parseFloat(subtotalWithServiceCharge) - parseFloat(balanceAdjustment);

                // Ensure totalAmount never goes below 0
                if (totalAmount < 0) {
                    totalAmount = 0;
                }

                // Check credit limit for credit clients
                if (isCreditClient && totalAmount > 0) {
                    // For credit clients, check if purchase would exceed credit limit
                    // The totalAmount represents what they will owe after this purchase
                    // We need to check: current amount owed + totalAmount <= creditLimit
                    const currentAmountOwed = Math.max(0, -parseFloat({{ $client->available_balance ?? 0 }}));
                    const newAmountOwed = currentAmountOwed + totalAmount;

                    if (newAmountOwed > creditLimit) {
                        const excess = newAmountOwed - creditLimit;
                        Swal.fire({
                            icon: 'error',
                            title: 'Credit Limit Exceeded',
                            html: `
                                <div class="text-left">
                                    <p class="mb-2">This purchase would exceed the client's credit limit.</p>
                                    <div class="bg-gray-50 p-3 rounded mt-3">
                                        <p class="text-sm"><strong>Credit Limit:</strong> UGX ${creditLimit.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                                        <p class="text-sm"><strong>Current Credit Remaining:</strong> UGX ${currentCreditRemaining.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                                        <p class="text-sm"><strong>Purchase Amount:</strong> UGX ${totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                                        <p class="text-sm text-red-600"><strong>Excess Amount:</strong> UGX ${excess.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                                    </div>
                                    <p class="text-sm text-gray-600 mt-3">Please reduce the purchase amount or request a credit limit increase.</p>
                                </div>
                            `,
                            confirmButtonText: 'OK',
                            width: '500px'
                        });
                        if (button) {
                            button.textContent = originalText;
                            button.disabled = false;
                        }
                        return;
                    }
                }

                const serviceChargeNumeric = parseFloat(serviceCharge) || 0;
                const requiresServiceCharge = !isDepositOnlyInvoice && packageAdjustment <= 0 && totalAmount > 0;

                if (isDepositOnlyInvoice && serviceChargeNumeric <= 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Service Charge Required',
                        text: 'Deposit invoices must include a service charge. Please configure the service charge and try again.',
                        confirmButtonText: 'OK'
                    });
                    if (button) {
                        button.textContent = originalText;
                        button.disabled = false;
                    }
                    return;
                }

                if (requiresServiceCharge && (isServiceChargeNotConfigured || serviceChargeNumeric <= 0)) {
                    const errorTitle = isServiceChargeNotConfigured ? 'Service Charges Not Configured' : 'Service Charge Required';
                    const errorMessage = isServiceChargeNotConfigured
                        ? 'Service charges not configured. Please contact support.'
                        : 'Service charge not configured. Please contact support.';

                    Swal.fire({
                        icon: 'error',
                        title: errorTitle,
                        text: errorMessage,
                        confirmButtonText: 'OK'
                    });
                    if (button) {
                        button.textContent = originalText;
                        button.disabled = false;
                    }
                    return;
                }

                console.log('Calculated values:', {
                    subtotal: subtotal,
                    packageAdjustment: packageAdjustment,
                    serviceCharge: serviceCharge,
                    serviceChargeDisplay: parseFloat(serviceCharge) > 0 ? `UGX ${parseFloat(serviceCharge).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}` : 'UGX 0.00 (No charges for this amount range)',
                    adjustedSubtotal: adjustedSubtotal,
                    subtotalWithServiceCharge: subtotalWithServiceCharge,
                    balanceAdjustment: balanceAdjustment,
                    totalAmount: totalAmount,
                    totalAmountType: typeof totalAmount,
                    isNaN: isNaN(totalAmount)
                });

                // Get payment phone and methods
                const paymentPhone = document.getElementById('payment-phone-edit')?.value || '';
                const paymentMethods = Array.from(document.querySelectorAll('input[name="payment_methods[]"]:checked'))
                    .map(cb => cb.value);

                // Validate third-party payer selection for credit clients with insurance
                @if($client->is_credit_eligible)
                if (paymentMethods.includes('insurance')) {
                    const thirdPartyPayerId = document.getElementById('third-party-payer-select')?.value;
                    if (!thirdPartyPayerId) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Third-Party Payer Required',
                            text: 'Please select a third-party payer for insurance payments. The third-party payer\'s account will be debited instead of the client\'s account.',
                            confirmButtonText: 'OK'
                        });
                        if (button) {
                            button.textContent = originalText;
                            button.disabled = false;
                        }
                        return;
                    }
                }
                @endif

                // Check if mobile money is selected and process payment
                let paymentResult = null;
                let amountPaid = 0;
                
                // Temporary local bypass: mark mobile money as paid immediately so save
                // can drive queue creation without waiting on Yo.
                if (!isInsuranceClient && paymentMethods.includes('mobile_money') && paymentPhone && totalAmount > 0) {
                    console.log('=== BYPASSING MOBILE MONEY PAYMENT ===');
                    console.log('Skipping Yo mobile money prompt and marking as paid:', {
                        totalAmount,
                        paymentPhone,
                        totalAmountType: typeof totalAmount,
                        isNaN: isNaN(totalAmount),
                        parseFloatResult: parseFloat(totalAmount)
                    });
                    paymentResult = {
                        success: true,
                        transaction_id: 'BYPASS-' + Date.now(),
                    };
                    amountPaid = totalAmount;
                } else if (!isInsuranceClient && paymentMethods.includes('mobile_money') && totalAmount === 0) {
                    console.log('=== SKIPPING MOBILE MONEY PAYMENT - ZERO AMOUNT ===');
                    console.log('Mobile money payment skipped because total amount is 0:', {
                        totalAmount: totalAmount,
                        paymentMethods: paymentMethods,
                        paymentPhone: paymentPhone
                    });
                }

                // Prepare cart items with total_amount for each item
                const itemsWithTotals = cart.map(item => ({
                    ...item,
                    total_amount: parseFloat(item.price || 0) * parseInt(item.quantity || 0)
                }));
                // Get the invoice number from the display (preview); server may assign a different number if stale.
                let invoiceNumber = document.getElementById('invoice-number-display').textContent;
                // Validate invoice number
                if (!invoiceNumber || invoiceNumber === 'Generating...' || invoiceNumber === 'Error generating invoice number') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Proforma Invoice Number',
                        text: 'Please wait for the proforma invoice number to be generated before saving.'
                    });
                    return;
                }

                // Get third-party payer ID if insurance is selected
                @if($client->is_credit_eligible)
                const thirdPartyPayerId = paymentMethods.includes('insurance')
                    ? (document.getElementById('third-party-payer-select')?.value || null)
                    : null;
                @else
                const thirdPartyPayerId = null;
                @endif

                // Prepare invoice data with all required fields
                const invoiceData = {
                    invoice_number: invoiceNumber,
                    client_id: {{ $client->id }},
                    business_id: {{ auth()->user()->business_id }},
                    branch_id: {{ auth()->user()->currentBranch->id ?? 'null' }},
                    created_by: {{ auth()->id() }},
                    client_name: '{{ $client->name }}',
                    client_phone: '{{ $client->phone_number }}',
                    payment_phone: paymentPhone,
                    third_party_payer_id: thirdPartyPayerId,
                    visit_id: '{{ $client->visit_id }}',
                    items: itemsWithTotals,
                    subtotal: parseFloat(subtotal),
                    package_adjustment: parseFloat(packageAdjustment),
                    account_balance_adjustment: parseFloat(balanceAdjustment),
                    service_charge: parseFloat(serviceCharge),
                    total_amount: parseFloat(totalAmount),
                    amount_paid: parseFloat(amountPaid),
                    balance_due: parseFloat(totalAmount - amountPaid),
                    payment_methods: paymentMethods,
                    payment_status: amountPaid >= totalAmount ? 'paid' : 'pending',
                    status: 'confirmed',
                    notes: ''
                };
                @if($client->insurance_company_id)
                if (typeof paymentResponsibility !== 'undefined' && paymentResponsibility.deductibleRemaining !== undefined) {
                    invoiceData.deductible_remaining = parseFloat(paymentResponsibility.deductibleRemaining) || 0;
                }
                @endif
                
                console.log('Proforma Invoice data being sent:', invoiceData);
                
                // Spinner while saving. Insurance-only: show "requesting authorization" (backend calls third-party before returning).
                const isInsurancePayment = paymentMethods.includes('insurance');
                Swal.fire({
                    title: isInsurancePayment ? 'Saving invoice & requesting authorization' : 'Saving invoice',
                    html: `
                        <div class="text-center">
                            <div class="mb-4">
                                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
                            </div>
                            <p class="text-gray-600">${isInsurancePayment ? 'Waiting for feedback from insurer...' : 'Please wait.'}</p>
                        </div>
                    `,
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    allowEscapeKey: false
                });
                
                // Save invoice (backend calls third-party for authorization before returning)
                const response = await fetch('/invoices', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(invoiceData)
                });

                const data = await response.json();
                
                console.log('=== POS INVOICE SAVE RESPONSE ===', data);
                console.log('=== POS INVOICE AUTH FLAGS ===', {
                    requires_insurance_client_payment: data.requires_insurance_client_payment ?? null,
                    client_total: data.client_total ?? null,
                    insurance_total: data.insurance_total ?? null,
                    has_insurance_authorization: !!data.insurance_authorization,
                    has_snapshot: !!(data.invoice && data.invoice.insurance_authorization_snapshot),
                });
                
                if (data.success) {
                    if (data.invoice && data.invoice.invoice_number) {
                        invoiceNumber = data.invoice.invoice_number;
                    }
                    const previewNumEl = document.getElementById('invoice-number-display');
                    if (previewNumEl && data.next_invoice_number) {
                        previewNumEl.textContent = data.next_invoice_number;
                    }

                    // If insurance authorization failed (HTTP error from third-party)
                    if (data.insurance_authorization_failed && data.insurance_authorization_error) {
                        await Swal.fire({
                            icon: 'error',
                            title: 'Authorization failed',
                            html: `
                                <div class="text-left">
                                    <p class="mb-2">The insurer could not authorize this invoice.</p>
                                    <p class="text-sm text-gray-700"><strong>Reason:</strong> ${data.insurance_authorization_error}</p>
                                    <p class="text-xs text-gray-500 mt-2">The proforma invoice has still been saved in Kashtre.</p>
                                </div>
                            `,
                            confirmButtonText: 'OK',
                            allowOutsideClick: false
                        });
                        finishInvoiceSuccess(data, invoiceNumber, button, originalText, { completeInsuranceDelivery: false });
                        return;
                    }

                    const authStatus = data.authorization_status || 'auto_approved';

                    // Auto-rejected by insurer thresholds
                    if (authStatus === 'auto_rejected') {
                        const escapeHtml = (value) => String(value ?? '')
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;')
                            .replace(/'/g, '&#039;');

                        const toReasonItems = (value) => {
                            if (!value) return [];
                            return String(value)
                                .split(/\r?\n/)
                                .flatMap(line => line.split(/(?<=\.)\s+/))
                                .map(part => part.trim())
                                .filter(Boolean);
                        };

                        const summarizeReason = (reason) => {
                            const lower = String(reason || '').toLowerCase();
                            if (lower.includes('exceeds remaining') || lower.includes('remaining') && lower.includes('benefit')) {
                                return 'Policy benefit limit reached for this visit.';
                            }
                            if (lower.includes('credit limit exceeded')) {
                                return 'Insurer credit limit exceeded.';
                            }
                            if (lower.includes('grace period') || lower.includes('grace ends')) {
                                return 'Policy is currently in grace period.';
                            }
                            if (lower.includes('insurance portion capped')) {
                                return 'Insurance portion capped by remaining benefit.';
                            }
                            if (lower.includes('client pays additional')) {
                                return '';
                            }
                            return String(reason || '').trim();
                        };

                        const reasons = [];
                        if (data.insurance_authorization && data.insurance_authorization.message) {
                            reasons.push(data.insurance_authorization.message);
                        }
                        if (Array.isArray(data.warnings) && data.warnings.length) {
                            reasons.push(...data.warnings);
                        }
                        const reasonItems = reasons.flatMap(toReasonItems);
                        const uniqueReasonItems = [...new Set(reasonItems)];
                        const summaryItems = [...new Set(uniqueReasonItems.map(summarizeReason).filter(Boolean))].slice(0, 4);
                        const fmt = (n) => (parseFloat(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        const invoiceTotal = data.invoice && data.invoice.total_amount != null ? parseFloat(data.invoice.total_amount) : 0;
                        const insurerAmount = data.insurance_total != null ? parseFloat(data.insurance_total) : 0;
                        const clientDue = data.client_total != null ? parseFloat(data.client_total) : invoiceTotal;
                        const reasonsHtml = uniqueReasonItems.length
                            ? `
                                <div class="mt-3 rounded-md border border-slate-200 bg-slate-50 p-3">
                                    <p class="text-xs font-semibold tracking-wide text-slate-700 uppercase">Why this was rejected</p>
                                    <ul class="mt-2 space-y-1 text-sm text-slate-700 list-disc pl-5">
                                        ${summaryItems.map(item => `<li>${escapeHtml(item)}</li>`).join('')}
                                    </ul>
                                    <details class="mt-2">
                                        <summary class="text-xs text-slate-500 cursor-pointer">View full insurer response</summary>
                                        <ul class="mt-1 space-y-1 text-xs text-slate-500 list-disc pl-5">
                                            ${uniqueReasonItems.map(item => `<li>${escapeHtml(item)}</li>`).join('')}
                                        </ul>
                                    </details>
                                </div>
                            `
                            : '';
                        await Swal.fire({
                            icon: 'error',
                            title: 'Authorization rejected',
                            html: `
                                <div class="text-left text-sm">
                                    <p class="mb-2 text-slate-800">The insurer has <strong>rejected</strong> this authorization request.</p>
                                    <p class="text-slate-700">Invoice saved successfully. Insurance has been removed as a payment method for this invoice.</p>
                                    <p class="mt-2 text-slate-700"><strong>Next step:</strong> collect payment from the client using the button below.</p>
                                    <p class="mt-1 text-xs text-slate-500">No client payment has been collected yet.</p>
                                    <div class="mt-3 rounded-md border border-slate-200 bg-white p-3 text-sm">
                                        <p class="text-xs font-semibold tracking-wide text-slate-700 uppercase">Final billing outcome</p>
                                        <div class="mt-2 space-y-1">
                                            <p class="flex justify-between text-slate-700"><span>Invoice total</span><span>UGX ${fmt(invoiceTotal)}</span></p>
                                            <p class="flex justify-between text-slate-700"><span>Insurer pays</span><span>UGX ${fmt(insurerAmount)}</span></p>
                                            <p class="flex justify-between font-semibold text-slate-900 border-t border-slate-100 pt-2 mt-2"><span>Client pays now</span><span>UGX ${fmt(clientDue)}</span></p>
                                        </div>
                                    </div>
                                    ${reasonsHtml}
                                </div>
                            `,
                            showCancelButton: true,
                            confirmButtonText: 'Collect client payment',
                            cancelButtonText: 'Close',
                            confirmButtonColor: '#2563eb',
                            allowOutsideClick: false
                        }).then((result) => {
                            if (result.isConfirmed) {
                                showCollectClientModal(data, paymentPhone, data, invoiceNumber, button, originalText);
                            } else {
                                finishInvoiceSuccess(data, invoiceNumber, button, originalText);
                            }
                        });
                        return;
                    }

                    // Pending manual review
                    if (authStatus === 'pending_review') {
                        const fmt = (n) => (parseFloat(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        const breakdown = data.breakdown || {};
                        const invoiceTotal = data.invoice && data.invoice.total_amount != null ? parseFloat(data.invoice.total_amount) : null;
                        const warningsHtml = (data.warnings && data.warnings.length)
                            ? '<div class="bg-amber-50 border border-amber-200 rounded p-2 mt-2"><p class="text-xs text-amber-800">' + data.warnings.join('</p><p class="text-xs text-amber-800">') + '</p></div>'
                            : '';

                        const breakdownRows = [];
                        if (breakdown.deductible != null && parseFloat(breakdown.deductible) > 0)
                            breakdownRows.push('<tr><td class="text-left text-gray-600">Deductible</td><td class="text-right">UGX ' + fmt(breakdown.deductible) + '</td></tr>');
                        if (breakdown.copay != null && parseFloat(breakdown.copay) > 0)
                            breakdownRows.push('<tr><td class="text-left text-gray-600">Co-pay</td><td class="text-right">UGX ' + fmt(breakdown.copay) + '</td></tr>');
                        if (breakdown.coinsurance != null && parseFloat(breakdown.coinsurance) > 0)
                            breakdownRows.push('<tr><td class="text-left text-gray-600">Co-insurance</td><td class="text-right">UGX ' + fmt(breakdown.coinsurance) + '</td></tr>');
                        const breakdownHtml = breakdownRows.length ? '<table class="w-full text-sm my-2 border-t border-b border-slate-200"><tbody>' + breakdownRows.join('') + '</tbody></table>' : '';

                        await Swal.fire({
                            icon: 'warning',
                            title: 'Pending approval',
                            html: `
                                <div class="text-left text-sm">
                                    <div class="bg-orange-50 border border-orange-300 rounded-lg p-3 mb-3">
                                        <p class="font-semibold text-orange-800 mb-1">Awaiting insurer approval</p>
                                        <p class="text-orange-700 text-xs">Insurance portion of UGX ${fmt(data.insurance_total)} needs manual approval.</p>
                                    </div>
                                    ${breakdownHtml}
                                    <div class="space-y-1 mt-2">
                                        ${invoiceTotal != null ? '<p class="flex justify-between text-slate-700"><span>Invoice total</span><span>UGX ' + fmt(invoiceTotal) + '</span></p>' : ''}
                                        <p class="flex justify-between text-slate-700"><span>Insurance portion</span><span class="text-orange-600 font-medium">UGX ${fmt(data.insurance_total)} <span class="text-xs">(pending)</span></span></p>
                                        <p class="flex justify-between text-slate-700"><span>Client portion</span><span>UGX ${fmt(computeInsuranceClientAmountDue(data))}</span></p>
                                    </div>
                                    ${warningsHtml}
                                    <p class="text-xs text-gray-500 mt-3">Invoice saved. You can collect the client portion while approval is pending.</p>
                                </div>
                            `,
                            showCancelButton: true,
                            confirmButtonText: 'Collect client portion now',
                            cancelButtonText: 'OK, wait for review',
                            confirmButtonColor: '#2563eb',
                            allowOutsideClick: false
                        }).then((result) => {
                            if (result.isConfirmed) {
                                showCollectClientModal(data, paymentPhone, data, invoiceNumber, button, originalText);
                            } else {
                                finishInvoiceSuccess(data, invoiceNumber, button, originalText);
                            }
                        });
                        return;
                    }

                    // Auto-approved — For insurance clients: no payment prompt (collect separately)
                    // For cash/direct pay clients: show payment prompt immediately
                    const clientTotalDue = computeInsuranceClientAmountDue(data);
                    const isMultiVendor = data.is_multi_vendor === true;
                    const isInsuranceAuth = !!data.insurance_authorization;
                    const fmt = (n) => (parseFloat(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                    if (isInsuranceAuth) {
                        // Insurance client: show authorization success then collect client portion
                        const vendorBreakdownHtml = buildVendorCascadeSummaryHtml(data.insurance_authorization, data.invoice, data);

                        // If client portion > 0 and payment method is available: collect now
                        if (clientTotalDue > 0) {
                            // Show authorization then proceed to collection
                            const authorizationSuccessResult = await Swal.fire({
                                icon: 'success',
                                title: 'Invoice Authorized',
                                html: `
                                    <div class="text-left text-sm">
                                        <p class="text-slate-700 mb-3">Authorization has been processed successfully.</p>
                                        ${vendorBreakdownHtml}
                                        <div class="mt-3 rounded-lg border border-blue-200 bg-blue-50 p-3">
                                            <p class="text-xs font-semibold text-blue-700 uppercase mb-2">Client Portion to Collect</p>
                                            <p class="text-lg font-bold text-blue-900">UGX ${fmt(clientTotalDue)}</p>
                                        </div>
                                    </div>
                                `,
                                showCancelButton: true,
                                confirmButtonText: 'Collect Payment',
                                cancelButtonText: 'Cancel',
                                confirmButtonColor: '#2563eb',
                                allowOutsideClick: false
                            });

                            if (authorizationSuccessResult.isDismissed) {
                                finishInvoiceSuccess(data, invoiceNumber, button, originalText, { completeInsuranceDelivery: false });
                                return;
                            }

                            // Now collect the client portion via phone/mobile money
                            showCollectClientModal(data, paymentPhone, data, invoiceNumber, button, originalText);
                        } else {
                            // No client portion due - just show success
                            await Swal.fire({
                                icon: 'success',
                                title: 'Invoice Authorized',
                                html: `
                                    <div class="text-left text-sm">
                                        <p class="text-slate-700 mb-3">Authorization has been processed successfully.</p>
                                        ${vendorBreakdownHtml}
                                        <div class="mt-3 rounded-lg border border-green-200 bg-green-50 p-3">
                                            <p class="text-xs font-semibold text-green-700 uppercase mb-2">Insurance Covers Full Amount</p>
                                            <p class="text-sm text-green-700">No client payment required.</p>
                                        </div>
                                    </div>
                                `,
                                showCancelButton: true,
                                confirmButtonText: 'Continue',
                                cancelButtonText: 'Cancel',
                                confirmButtonColor: '#10b981',
                                allowOutsideClick: false
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    finishInvoiceSuccess(data, invoiceNumber, button, originalText, { completeInsuranceDelivery: true });
                                }
                            });
                        }
                    } else if (clientTotalDue > 0) {
                        // Non-insurance client with amount due: show payment prompt
                        showCollectClientModal(data, paymentPhone, data, invoiceNumber, button, originalText);
                    } else {
                        finishInvoiceSuccess(data, invoiceNumber, button, originalText);
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Error saving invoice: ' + (data.message || 'Unknown error')
                    });
                }

            } catch (error) {
                console.error('Error saving invoice:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Error saving invoice. Please try again.'
                });
            } finally {
                if (button) {
                    button.textContent = originalText;
                    button.disabled = false;
                }
            }
        }

        function escapeHtmlInsuranceUi(str) {
            if (str == null) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        /** Paid-by cell: full insurer name, or per-insurer amounts when partial % cascades (e.g. 50% AAR + 50% Earth). */
        function formatInsuranceLinePaidByHtml(row, fmt) {
            const primaryName = row.primary_insurer || '';
            const secondaryName = row.secondary_insurer || '';
            const coveredAmt = parseFloat(row.covered_amount);
            const secondaryAmt = parseFloat(row.secondary_covered_amount);
            const clientPortion = parseFloat(row.client_portion);
            const lineTotal = parseFloat(row.line_total);

            if (secondaryName && !isNaN(secondaryAmt) && secondaryAmt > 0.001 && primaryName) {
                let primaryPay = !isNaN(coveredAmt) && coveredAmt > 0.001
                    ? coveredAmt
                    : (isNaN(lineTotal) ? 0 : Math.max(0, lineTotal - secondaryAmt));
                primaryPay = Math.round(primaryPay * 100) / 100;
                const pct = row.coverage_percent != null ? parseFloat(row.coverage_percent) : null;
                const pctHint = pct != null && pct > 0 && pct < 100
                    ? ` <span class="text-slate-400">(${pct}%)</span>`
                    : '';
                return `<div class="space-y-0.5 leading-snug">
                    <div>${escapeHtmlInsuranceUi(primaryName)}${pctHint}: <span class="font-mono text-slate-900">UGX ${fmt(primaryPay)}</span></div>
                    <div>${escapeHtmlInsuranceUi(secondaryName)}: <span class="font-mono text-slate-900">UGX ${fmt(secondaryAmt)}</span></div>
                </div>`;
            }

            if (primaryName && !isNaN(coveredAmt) && coveredAmt > 0.001 && !isNaN(clientPortion) && clientPortion > 0.001) {
                const pct = row.coverage_percent != null ? parseFloat(row.coverage_percent) : null;
                const pctHint = pct != null && pct > 0 && pct < 100
                    ? ` <span class="text-slate-400">(${pct}%)</span>`
                    : '';
                return `<div class="space-y-0.5 leading-snug">
                    <div>${escapeHtmlInsuranceUi(primaryName)}${pctHint}: <span class="font-mono text-slate-900">UGX ${fmt(coveredAmt)}</span></div>
                    <div class="text-slate-600">Client: <span class="font-mono">UGX ${fmt(clientPortion)}</span></div>
                </div>`;
            }

            return escapeHtmlInsuranceUi(row.attribution_label || row.primary_insurer || 'Client');
        }

        /** Brief multi-vendor summary: one small table + one-line payer totals + invoice/service fee. */
        function buildVendorCascadeSummaryHtml(insuranceAuth, invoice, responseExtra) {
            const fmt = (n) => (parseFloat(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const vendorBreakdown = insuranceAuth?.vendors ? insuranceAuth.vendors : [];
            const lineRows = Array.isArray(insuranceAuth?.cascade_line_items) ? insuranceAuth.cascade_line_items : [];
            let html = '';

            if (lineRows.length) {
                html += `<div class="mt-2 rounded border border-slate-200 overflow-hidden">
                    <div class="max-h-40 overflow-y-auto">
                    <table class="w-full text-[11px]">
                    <thead><tr class="bg-slate-50 text-slate-600"><th class="px-2 py-1 text-left font-medium">Item</th><th class="px-2 py-1 text-right font-medium">Qty</th><th class="px-2 py-1 text-right font-medium">Amount</th><th class="px-2 py-1 text-left font-medium">Paid by</th></tr></thead>
                    <tbody>`;
                lineRows.forEach((row) => {
                    const paidBy = formatInsuranceLinePaidByHtml(row, fmt);
                    const nm = escapeHtmlInsuranceUi(row.name || 'Line');
                    const cd = row.code ? ` <span class="text-slate-400">(${escapeHtmlInsuranceUi(row.code)})</span>` : '';
                    const qtyDisp = escapeHtmlInsuranceUi(row.quantity != null ? String(row.quantity) : '');
                    const lineAmt = `UGX ${fmt(row.line_total)}`;
                    html += `<tr class="border-t border-slate-100"><td class="px-2 py-1 text-slate-700">${nm}${cd}</td><td class="px-2 py-1 text-right text-slate-600">${qtyDisp}</td><td class="px-2 py-1 text-right text-slate-800">${lineAmt}</td><td class="px-2 py-1 text-slate-700">${paidBy}</td></tr>`;
                });
                html += `</tbody></table></div></div>`;
            }

            html += buildInsuranceAuthorizationPricingHtml(insuranceAuth, invoice, vendorBreakdown);

            if (vendorBreakdown.length) {
                const payerLine = vendorBreakdown.map((v) => {
                    const name = escapeHtmlInsuranceUi(v.vendor_name || 'Vendor');
                    const lbl = v.portion_label ? ` <span class="text-slate-500 font-semibold">[${escapeHtmlInsuranceUi(String(v.portion_label))}]</span>` : '';
                    const pays = fmt(v.insurance_total);
                    return `${name}${lbl}: UGX ${pays}`;
                }).join(' · ');
                html += `<p class="mt-2 text-[11px] text-slate-600 leading-snug">${payerLine}</p>`;
            }

            const portions = responseExtra?.vendor_portion_invoices;
            if (Array.isArray(portions) && portions.length) {
                let traceSum = 0;
                const lines = portions.map((p) => {
                    const lab = escapeHtmlInsuranceUi(p.vendor_portion_label || '');
                    const num = escapeHtmlInsuranceUi(p.invoice_number || '');
                    const amt = parseFloat(p.total_amount) || 0;
                    traceSum += amt;
                    return `<li class="flex justify-between gap-2"><span><span class="font-bold text-slate-700">${lab}</span> ${num}</span><span class="font-mono">UGX ${fmt(amt)}</span></li>`;
                }).join('');
                const invTot = parseFloat(invoice?.total_amount ?? responseExtra?.invoice?.total_amount ?? NaN);
                const traceFoot = !isNaN(invTot) && invTot > 0
                    ? `<p class="flex justify-between gap-2 mt-1.5 pt-1 border-t border-slate-200 font-medium"><span>Trace total</span><span class="font-mono">UGX ${fmt(traceSum)}</span></p>`
                    : '';
                html += `<div class="mt-3 rounded border border-slate-200 bg-white p-2.5 text-[11px] text-slate-800"><p class="font-semibold text-slate-700 mb-1.5">Trace invoices</p><ul class="space-y-1">${lines}</ul>${traceFoot}<p class="text-slate-500 mt-1 text-[10px]">Follow-up copies only.</p></div>`;
            }

            return html;
        }

        /** Assign full clinic service charge to one insurer (first in cascade priority with an insurance share). */
        function allocateServiceChargeAmongVendors(vendors, serviceCharge) {
            const sc = parseFloat(serviceCharge) || 0;
            if (!Array.isArray(vendors) || vendors.length === 0 || sc <= 0.001) {
                return [];
            }
            const eligible = vendors
                .filter((v) => {
                    const st = String(v.authorization_status || '').toLowerCase();
                    return !['failed', 'skipped'].includes(st);
                })
                .sort((a, b) => (parseFloat(a.priority) || 999) - (parseFloat(b.priority) || 999));
            if (!eligible.length) {
                return [];
            }
            let holder = eligible.find((v) => (parseFloat(v.insurance_total) || 0) > 0.001);
            if (!holder) {
                holder = eligible[0];
            }
            return [{ vendor: holder, share: Math.round(sc * 100) / 100 }];
        }

        /** Subtotal, clinic service fee (who pays which share), invoice total. */
        function buildInsuranceAuthorizationPricingHtml(insuranceAuth, invoice, vendorsForSc) {
            const fmt = (n) => (parseFloat(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const sub = parseFloat(insuranceAuth?.subtotal ?? invoice?.subtotal ?? NaN);
            const sc = parseFloat(insuranceAuth?.service_charge ?? invoice?.service_charge ?? NaN);
            const tot = parseFloat(insuranceAuth?.total_amount ?? invoice?.total_amount ?? NaN);
            const hasSub = !isNaN(sub) && sub > 0;
            const hasSc = !isNaN(sc) && sc > 0;
            const hasTot = !isNaN(tot) && tot > 0;
            if (!hasSub && !hasSc && !hasTot) {
                return '';
            }
            const vendorList = Array.isArray(vendorsForSc) && vendorsForSc.length ? vendorsForSc : (insuranceAuth?.vendors || []);
            const scShares = hasSc ? allocateServiceChargeAmongVendors(vendorList, sc) : [];

            let out = '<div class="mt-3 rounded border border-slate-200 bg-slate-50 p-2.5 text-[11px] text-slate-800">';
            out += '<p class="font-semibold text-slate-800 mb-1.5 text-xs">Invoice summary</p>';
            if (hasSub) {
                out += `<p class="flex justify-between gap-2"><span>Clinical subtotal</span><span class="font-mono whitespace-nowrap">UGX ${fmt(sub)}</span></p>`;
            }
            if (hasSc && scShares.length) {
                const scRow = scShares[0];
                const v = scRow.vendor;
                const name = escapeHtmlInsuranceUi(v.vendor_name || 'Insurer');
                const lbl = v.portion_label ? ` [${escapeHtmlInsuranceUi(String(v.portion_label))}]` : '';
                out += `<p class="flex justify-between gap-2 mt-1"><span>Service charge — ${name}${lbl}</span><span class="font-mono whitespace-nowrap">UGX ${fmt(scRow.share)}</span></p>`;
            } else if (hasSc) {
                out += `<p class="flex justify-between gap-2"><span>Service charge</span><span class="font-mono whitespace-nowrap">UGX ${fmt(sc)}</span></p>`;
            }
            if (hasTot) {
                out += `<p class="flex justify-between gap-2 font-semibold border-t border-slate-200 mt-1.5 pt-1.5"><span>Invoice total</span><span class="font-mono whitespace-nowrap">UGX ${fmt(tot)}</span></p>`;
            }
            out += '</div>';
            return out;
        }

        /**
         * Client amount to collect after insurance authorization.
         * Uses invoice_total - insurance_total when both exist (matches vendor ledger and backend normalization).
         */
        function computeInsuranceClientAmountDue(data) {
            const inv = data.invoice && data.invoice.total_amount != null ? parseFloat(data.invoice.total_amount) : NaN;
            const ins = data.insurance_total != null ? parseFloat(data.insurance_total) : NaN;
            const api = data.client_total != null ? parseFloat(data.client_total) : NaN;
            if (!isNaN(inv) && !isNaN(ins)) {
                const fromSplit = Math.max(0, Math.round((inv - ins) * 100) / 100);
                if (!isNaN(api) && Math.abs(api - fromSplit) > 0.02) {
                    console.warn('[Kashtre] client_total differs from invoice - insurance_total; using split', {
                        client_total: api, invoice_total: inv, insurance_total: ins, fromSplit
                    });
                }
                return fromSplit;
            }
            if (!isNaN(api) && api >= 0) return Math.round(api * 100) / 100;
            const b = data.breakdown || {};
            let sum = 0;
            ['deductible', 'copay', 'coinsurance', 'excluded'].forEach((k) => {
                const v = parseFloat(b[k]);
                if (!isNaN(v) && v > 0) sum += v;
            });
            return Math.round(sum * 100) / 100;
        }

        function showCollectClientModal(displayData, paymentPhone, dataForSuccess, invoiceNumber, button, originalText) {
            const data = displayData;
            const breakdown = data.breakdown || {};
            const policyOpts = data.policy_options || {};
            const fmt = (n) => (parseFloat(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const clientTotalDue = computeInsuranceClientAmountDue(data);
            const policyRows = [];
            if (policyOpts.has_deductible && policyOpts.deductible_amount != null && parseFloat(policyOpts.deductible_amount) > 0) {
                policyRows.push(`<div class="bg-amber-50 border border-amber-200 rounded-lg p-2 mb-2"><span class="text-sm font-medium text-amber-800">Deductible</span><p class="text-sm text-amber-900">UGX ${fmt(policyOpts.deductible_amount)}</p></div>`);
            }
            if (policyOpts.copay_amount != null && parseFloat(policyOpts.copay_amount) > 0) {
                let copayHtml = `<div class="bg-blue-50 border border-blue-200 rounded-lg p-2 mb-2"><span class="text-sm font-medium text-blue-800">Co-payment</span><p class="text-sm text-blue-900">UGX ${fmt(policyOpts.copay_amount)} per visit`;
                if (policyOpts.copay_max_limit != null && parseFloat(policyOpts.copay_max_limit) > 0) {
                    copayHtml += `</p><p class="text-xs text-blue-700">Max Limit: UGX ${fmt(policyOpts.copay_max_limit)}</p>`;
                } else { copayHtml += '</p>'; }
                copayHtml += '</div>';
                policyRows.push(copayHtml);
            }
            if (policyOpts.coinsurance_percentage != null && parseFloat(policyOpts.coinsurance_percentage) > 0) {
                policyRows.push(`<div class="bg-indigo-50 border border-indigo-200 rounded-lg p-2 mb-2"><span class="text-sm font-medium text-indigo-800">Coinsurance</span><p class="text-sm text-indigo-900">${fmt(policyOpts.coinsurance_percentage)}%</p></div>`);
            }
            const policyOptionsHtml = policyRows.length
                ? `<div class="mb-3"><p class="text-xs font-semibold text-gray-600 mb-2">Policy options (what this client has to pay)</p><div class="grid grid-cols-1 gap-2">${policyRows.join('')}</div></div>`
                : '';
            const breakdownRows = [];
            if (breakdown.deductible != null && parseFloat(breakdown.deductible) > 0) {
                breakdownRows.push(`<tr><td class="text-left text-gray-600">Deductible</td><td class="text-right">UGX ${fmt(breakdown.deductible)}</td></tr>`);
            }
            if (breakdown.copay != null && parseFloat(breakdown.copay) > 0) {
                breakdownRows.push(`<tr><td class="text-left text-gray-600">Co-pay</td><td class="text-right">UGX ${fmt(breakdown.copay)}</td></tr>`);
            }
            if (breakdown.coinsurance != null && parseFloat(breakdown.coinsurance) > 0) {
                breakdownRows.push(`<tr><td class="text-left text-gray-600">Co-insurance</td><td class="text-right">UGX ${fmt(breakdown.coinsurance)}</td></tr>`);
            }
            if (breakdown.excluded != null && parseFloat(breakdown.excluded) > 0) {
                breakdownRows.push(`<tr><td class="text-left text-gray-600">Excluded (not covered by insurance)</td><td class="text-right">UGX ${fmt(breakdown.excluded)}</td></tr>`);
            }
            const invoiceTotal = data.invoice && data.invoice.total_amount != null ? parseFloat(data.invoice.total_amount) : null;
            const insurancePortion = parseFloat(data.insurance_total || 0) || 0;
            const todayStr = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
            const vendorAllocations = Array.isArray(data?.insurance_authorization?.vendors)
                ? data.insurance_authorization.vendors
                : (Array.isArray(data?.vendors) ? data.vendors : []);
            const vendorAllocationRows = vendorAllocations
                .filter(v => parseFloat(v.client_portion_allocated || 0) > 0)
                .map(v => '<tr><td class="text-left text-gray-600">Client allocation (' + ((v.vendor_name || 'Vendor').replace(/</g, '&lt;').replace(/>/g, '&gt;')) + ')</td><td class="text-right">UGX ' + fmt(v.client_portion_allocated) + '</td></tr>');
            const receiptBreakdownRows = breakdownRows.length
                ? breakdownRows.join('')
                : (vendorAllocationRows.length ? vendorAllocationRows.join('') : '<tr><td class="text-left text-gray-600">Client portion</td><td class="text-right">UGX ' + fmt(clientTotalDue) + '</td></tr>');
            const receiptHtml = '<div id="insurance-client-portion-receipt" class="receipt-box text-center text-sm" style="max-width:320px;margin:0 auto;font-family:ui-sans-serif,sans-serif;">' +
                '<div class="border-b border-slate-200 pb-2 mb-3"><p class="font-semibold text-slate-800 text-base tracking-wide">Client portion receipt</p><p class="text-slate-500 text-xs mt-1">Amount due from client</p></div>' +
                '<div class="text-left space-y-0.5 text-slate-600 text-xs mb-2">' + (posClientName ? '<p>Client <strong class="text-slate-800">' + (posClientName.replace(/</g, '&lt;').replace(/>/g, '&gt;')) + '</strong></p>' : '') + (posInsuranceName ? '<p>Insurance <strong class="text-slate-800">' + (posInsuranceName.replace(/</g, '&lt;').replace(/>/g, '&gt;')) + '</strong></p>' : '') + '<p>Invoice <strong class="text-slate-800">' + invoiceNumber + '</strong></p><p>Date ' + todayStr + '</p></div>' +
                '<table class="w-full text-sm my-3 border-t border-b border-slate-200"><tbody>' + receiptBreakdownRows + '</tbody></table>' +
                (Array.isArray(breakdown.excluded_items) && breakdown.excluded_items.length
                    ? '<div class="text-left text-xs text-slate-600 mb-2"><p class="font-semibold mb-1">Not covered by insurance:</p><ul class="list-disc list-inside space-y-0.5">' +
                        breakdown.excluded_items.map(function (ex) {
                            const label = ex.name || ex.code || 'Excluded item';
                            const amount = ex.amount != null ? ' – UGX ' + fmt(ex.amount) : '';
                            return '<li>' + label.replace(/</g, '&lt;').replace(/>/g, '&gt;') + amount + '</li>';
                        }).join('') +
                      '</ul></div>'
                    : ''
                ) +
                '<div class="text-left space-y-1 mt-2 text-sm">' +
                (invoiceTotal != null ? '<p class="flex justify-between text-slate-700"><span>Invoice total</span><span>UGX ' + fmt(invoiceTotal) + '</span></p>' : '') +
                (insurancePortion > 0 ? '<p class="flex justify-between text-slate-700"><span>Insurance portion</span><span>UGX ' + fmt(insurancePortion) + '</span></p>' : '') +
                '<p class="flex justify-between font-semibold text-slate-900 mt-2 pt-2 border-t border-slate-100"><span>Amount to collect</span><span>UGX ' + fmt(clientTotalDue) + '</span></p>' +
                '<p class="text-slate-500 text-xs mt-2">Phone ' + (paymentPhone || '—') + '</p></div></div>';
            const printReceipt = function() {
                const printWin = window.open('', '_blank', 'width=400,height=600');
                if (!printWin) return;
                const docFormat = window.KashtreDocumentFormat;
                const headerBlock = docFormat
                    ? docFormat.headerHtml('Client portion receipt', invoiceNumber)
                    : '<div class="receipt-title">Client portion receipt</div><p class="sub">Amount due from client</p>';
                const bodyBlock =
                    (posClientName ? '<p>Client <strong>' + (posClientName.replace(/</g, '&lt;').replace(/>/g, '&gt;')) + '</strong></p>' : '') +
                    (posInsuranceName ? '<p>Insurance <strong>' + (posInsuranceName.replace(/</g, '&lt;').replace(/>/g, '&gt;')) + '</strong></p>' : '') +
                    (!docFormat ? '<p>Invoice <strong>' + invoiceNumber + '</strong></p><p>Date ' + todayStr + '</p>' : '') +
                    '<table><tbody>' + receiptBreakdownRows + '</tbody></table>' +
                    '<p class="total">Amount to collect: UGX ' + fmt(clientTotalDue) + '</p><p style="font-size:12px;color:#64748b">Phone ' + (paymentPhone || '—') + '</p>';
                const footerBlock = docFormat
                    ? docFormat.footerHtml(['Client portion receipt', 'Amount due from client'])
                    : '<p style="font-size:8px;color:#9ca3af;margin-top:12px;">Powered by Kashtre</p>';
                const printContent = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Client portion – ' + invoiceNumber + '</title>' +
                    '<style>body{font-family:ui-sans-serif,sans-serif;font-size:14px;padding:24px;max-width:360px;margin:0 auto;color:#334155}.receipt-title{font-weight:600;font-size:1rem;border-bottom:1px solid #e2e8f0;padding-bottom:8px;margin-bottom:8px}.sub{font-size:12px;color:#64748b;margin-bottom:12px}table{width:100%;border-collapse:collapse;margin:12px 0}td{padding:4px 0}td:last-child{text-align:right}.total{font-weight:600;margin-top:12px;padding-top:8px;border-top:1px solid #f1f5f9}@media print{body{padding:0}}</style></head><body>' +
                    headerBlock + bodyBlock + footerBlock + '</body></html>';
                printWin.document.write(printContent);
                printWin.document.close();
                printWin.focus();
                setTimeout(function() { printWin.print(); printWin.close(); }, 250);
            };
            Swal.fire({
                icon: 'info',
                title: 'Client portion',
                html: receiptHtml,
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonText: 'Collect payment (Mobile Money)',
                denyButtonText: 'Refresh',
                cancelButtonText: 'Cancel',
                allowOutsideClick: false,
                allowEscapeKey: true,
                didOpen: function() {
                    const actions = document.querySelector('.swal2-actions');
                    if (actions && !document.getElementById('btn-print-receipt')) {
                        const printBtn = document.createElement('button');
                        printBtn.type = 'button';
                        printBtn.id = 'btn-print-receipt';
                        printBtn.className = 'swal2-styled swal2-default-outline';
                        printBtn.textContent = 'Print';
                        printBtn.style.marginRight = '8px';
                        printBtn.onclick = printReceipt;
                        actions.insertBefore(printBtn, actions.firstChild);
                    }
                }
            }).then(async (result) => {
                if (result.isDismissed) {
                    if (button) { button.textContent = originalText; button.disabled = false; }
                    return;
                }
                if (result.isDenied) {
                    Swal.fire({
                        title: 'Please wait',
                        text: 'Updating...',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });
                    try {
                        const refRes = await fetch('/invoices/' + dataForSuccess.invoice.id + '/insurance-authorization', {
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
                        });
                        const refData = await refRes.json();
                        if (!refData.success) {
                            Swal.fire({ icon: 'error', title: 'Update failed', text: refData.message || 'Could not load the latest details.' });
                            return;
                        }
                        const merged = { ...refData, invoice: refData.invoice || dataForSuccess.invoice };
                        showCollectClientModal(merged, paymentPhone, dataForSuccess, invoiceNumber, button, originalText);
                    } catch (err) {
                        console.error(err);
                        Swal.fire({ icon: 'error', title: 'Something went wrong', text: 'Please try again.' });
                    }
                    return;
                }
                Swal.fire({
                    title: 'Processing...',
                    text: 'Sending payment prompt to client.',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });
                const payResult = await processMobileMoneyPayment(clientTotalDue, paymentPhone);
                if (payResult && payResult.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Payment prompt sent',
                        text: 'Client should approve on their phone. Items will be sent to service points after payment is confirmed (usually within a minute).'
                    });
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Payment prompt failed',
                        text: payResult && payResult.message ? payResult.message : 'Could not send prompt. Please collect payment manually.'
                    });
                }
                // Do not queue service delivery until Yo confirms (payments:check-status cron).
                finishInvoiceSuccess(dataForSuccess, invoiceNumber, button, originalText, { completeInsuranceDelivery: false });
            });
        }

        async function finishInvoiceSuccess(data, invoiceNumber, button, originalText, options = {}) {
            const shouldQueueInsuranceDelivery = options.completeInsuranceDelivery !== false
                && (data.service_queue_deferred === true || data.insurance_authorization?.service_queue_deferred === true);

            if (shouldQueueInsuranceDelivery && data?.invoice?.id) {
                try {
                    const queueRes = await fetch(`/invoices/${data.invoice.id}/complete-insurance-service-delivery`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                        },
                    });
                    const queueData = await queueRes.json();
                    if (!queueRes.ok || !queueData.success) {
                        console.warn('[Kashtre] Service queue after authorization failed', queueData);
                        await Swal.fire({
                            icon: 'warning',
                            title: 'Items not queued',
                            text: queueData.message || 'Could not send items to service points. You can refresh the page and try again.',
                        });
                    }
                } catch (queueErr) {
                    console.error('[Kashtre] Service queue after authorization failed', queueErr);
                }
            }

            if (button) {
                button.textContent = originalText;
                button.disabled = false;
            }
            cart = [];
            updateRequestOrderSummaryDisplay();
            closeInvoicePreview();
            Swal.fire({
                icon: 'success',
                title: 'Proforma Invoice Saved!',
                html: `
                    <div class="text-center">
                        <p class="mb-2">Proforma invoice has been saved successfully.</p>
                        <p class="text-sm text-gray-600">Proforma Invoice Number: <strong>${invoiceNumber}</strong></p>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'View Proforma Invoice',
                cancelButtonText: 'Print Proforma Invoice',
                showDenyButton: true,
                denyButtonText: 'Stay Here'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `/invoices/${data.invoice.id}`;
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    const printWindow = window.open(`/invoices/${data.invoice.id}/print`, '_blank');
                    setTimeout(() => {
                        Swal.fire({
                            icon: 'info',
                            title: 'Print Window Opened',
                            html: `<div class="text-center"><p class="mb-2">Proforma invoice print window has been opened.</p><p class="text-sm text-gray-600">Proforma Invoice Number: <strong>${invoiceNumber}</strong></p></div>`,
                            timer: 3000,
                            showConfirmButton: false
                        });
                    }, 1000);
                } else if (result.isDenied) {
                    window.location.reload();
                }
            });
        }
        



        // Payment Methods Modal Functions
        function openPaymentMethodsModal() {
            document.getElementById('payment-methods-modal').classList.remove('hidden');
        }

        function closePaymentMethodsModal() {
            document.getElementById('payment-methods-modal').classList.add('hidden');
        }

        function savePaymentMethods() {
            const selectedMethods = [];
            const checkboxes = document.querySelectorAll('input[name="payment_methods[]"]:checked');

            checkboxes.forEach(checkbox => {
                selectedMethods.push(checkbox.value);
            });

            // Send AJAX request to update payment methods
            fetch(`/clients/{{ $client->id }}/update-payment-methods`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    payment_methods: selectedMethods
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the display
                    updatePaymentMethodsDisplay(selectedMethods);
                    closePaymentMethodsModal();

                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Payment methods updated successfully!',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Error updating payment methods: ' + data.message
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Error updating payment methods'
                });
            });
        }

        function updatePaymentMethodsDisplay(methods) {
            const container = document.querySelector('.payment-methods-display');
            if (methods.length > 0) {
                container.innerHTML = methods.map((method, index) =>
                    `<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        ${index + 1}. ${method.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                    </span>`
                ).join('');
            } else {
                container.innerHTML = '<span class="text-sm text-gray-500">No payment methods specified</span>';
            }

            // Show/hide payment phone section based on mobile money selection
            const paymentPhoneSection = document.getElementById('payment-phone-section');
            if (methods.includes('mobile_money')) {
                paymentPhoneSection.style.display = 'block';
            } else {
                paymentPhoneSection.style.display = 'none';
            }
        }

        async function processMobileMoneyPayment(amount, phoneNumber) {
            // TODO: REVERT IN PROD — API call bypassed for local testing (auto-succeeds without YoAPI)
            return {
                success: true,
                transaction_id: 'LOCAL-' + Date.now(),
                amount: amount,
                phone: phoneNumber,
                status: 'success',
                message: 'Payment processed successfully (local bypass)'
            };

            /* PRODUCTION CODE — uncomment and remove the bypass above when deploying
            try {
                // Prepare payment data
                const paymentData = {
                    amount: amount,
                    phone_number: phoneNumber,
                    client_id: {{ $client->id }},
                    business_id: {{ auth()->user()->business_id }},
                    items: cart,
                    invoice_number: document.getElementById('invoice-number-display').textContent
                };

                // Send payment request to backend
                const response = await fetch('/invoices/mobile-money-payment', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(paymentData)
                });

                const result = await response.json();

                if (result.success) {
                    return {
                        success: true,
                        transaction_id: result.transaction_id,
                        amount: amount,
                        phone: phoneNumber,
                        status: result.status || 'success',
                        message: result.message || 'Payment processed successfully',
                        description: result.description
                    };
                } else {
                    return {
                        success: false,
                        message: result.message || 'Payment failed',
                        error: result.error
                    };
                }
            } catch (error) {
                console.error('Mobile money payment error:', error);
                return {
                    success: false,
                    message: 'Error processing mobile money payment',
                    error: error.message
                };
            }
            */
        }

        async function calculateServiceCharge(subtotal) {
            console.log('=== CALCULATE SERVICE CHARGE START ===');
            console.log('calculateServiceCharge called with subtotal:', subtotal);
            try {
                const response = await fetch('/invoices/service-charge', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        subtotal: subtotal,
                        business_id: {{ auth()->user()->business_id }},
                        branch_id: {{ auth()->user()->currentBranch->id ?? 'null' }}
                    })
                });

                const data = await response.json();
                console.log('Service charge API response:', data);
                if (data.success) {
                    const serviceCharge = parseFloat(data.service_charge) || 0;
                    const hasServiceChargeRanges = data.has_service_charge_ranges || false;
                    console.log('Service charge calculated:', serviceCharge, 'Has ranges:', hasServiceChargeRanges);
                    return {
                        amount: serviceCharge,
                        hasRanges: hasServiceChargeRanges
                    };
                } else {
                    console.error('Service charge calculation error:', data.message);
                    return { amount: 0, hasRanges: false };
                }
            } catch (error) {
                console.error('Error calculating service charge:', error);
                return { amount: 0, hasRanges: false };
            }
        }

        async function calculatePackageAdjustment() {
            try {
                console.log('Cart data being sent to package adjustment:', cart);
                const response = await fetch('/invoices/package-adjustment', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        client_id: {{ $client->id }},
                        business_id: {{ auth()->user()->business_id }},
                        branch_id: {{ $client->branch_id }},
                        items: cart
                    })
                });

                console.log('Package adjustment response status:', response.status);
                const data = await response.json();
                console.log('Package adjustment response data:', data);

                if (data.success) {
                    console.log('Package adjustment successful:', data.total_adjustment);
                    return {
                        total_adjustment: parseFloat(data.total_adjustment) || 0,
                        details: data.details || []
                    };
                } else {
                    console.error('Package adjustment calculation error:', data.message);
                    return { total_adjustment: 0, details: [] };
                }
            } catch (error) {
                console.error('Error calculating package adjustment:', error);
                return { total_adjustment: 0, details: [] };
            }
        }

        async function calculateBalanceAdjustment(totalAmount) {
            try {
                const response = await fetch('/invoices/balance-adjustment', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        client_id: {{ $client->id }},
                        total_amount: totalAmount
                    })
                });

                const data = await response.json();
                if (data.success) {
                    return {
                        balance_adjustment: parseFloat(data.balance_adjustment) || 0,
                        client_balance: parseFloat(data.client_balance) || 0,
                        remaining_balance: parseFloat(data.remaining_balance) || 0
                    };
                } else {
                    console.error('Balance adjustment calculation error:', data.message);
                    return { balance_adjustment: 0, client_balance: 0, remaining_balance: 0 };
                }
            } catch (error) {
                console.error('Error calculating balance adjustment:', error);
                return { balance_adjustment: 0, client_balance: 0, remaining_balance: 0 };
            }
        }

        async function refreshClientBalance() {
            try {
                const response = await fetch('/invoices/balance-adjustment', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
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

                    const formattedAvailableBalance = `Available: UGX ${availableBalance.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                    const formattedTotalBalance = `Total: UGX ${totalBalance.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

                    balanceDisplay.innerHTML = `<span class="text-blue-600">Available:</span> UGX ${availableBalance.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

                    let totalBalanceText = `<span class="text-gray-600">Total:</span> UGX ${totalBalance.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                    if (suspenseBalance > 0) {
                        totalBalanceText += ` <span class="text-orange-600">(${suspenseBalance.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} in suspense)</span>`;
                    }
                    totalBalanceDisplay.innerHTML = totalBalanceText;

                    // Update balance in invoice preview if it's open
                    const invoicePreviewBalance = document.querySelector('#invoice-preview-modal .text-blue-600.font-semibold');
                    if (invoicePreviewBalance) {
                        invoicePreviewBalance.textContent = `UGX ${availableBalance.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                    }

                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Balance Updated',
                        text: `Current balance: ${formattedBalance}`,
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    console.error('Error refreshing balance:', data.message);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to refresh balance'
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

        function savePaymentPhone() {
            const phoneInput = document.getElementById('payment-phone-edit');
            const phoneNumber = phoneInput.value.trim();
            const button = event.target;
            const originalText = button.textContent;

            // Show loading state
            button.textContent = 'Saving...';
            button.disabled = true;

            fetch(`/clients/{{ $client->id }}/update-payment-phone`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    payment_phone_number: phoneNumber
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Payment phone number updated successfully!',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Error updating payment phone number: ' + data.message
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Error updating payment phone number'
                });
            })
            .finally(() => {
                // Restore button state
                button.textContent = originalText;
                button.disabled = false;
            });
        }
        
        function saveServicesCategory() {
            const select = document.getElementById('services-category-edit');
            const category = select.value;
            const button = event.target;
            const originalText = button.textContent;

            button.textContent = 'Saving...';
            button.disabled = true;

            fetch(`/clients/{{ $client->id }}/update-services-category`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ services_category: category })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Updated',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Failed to update services category' });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to update services category' });
            })
            .finally(() => {
                button.textContent = originalText;
                button.disabled = false;
            });
        }

        // Generate unique package tracking numbers
        function generatePackageTrackingNumber(itemId, itemName) {
            // Create a timestamp-based tracking number
            const timestamp = Date.now();
            const randomSuffix = Math.floor(Math.random() * 1000).toString().padStart(3, '0');

            // Format: PKG-YYYYMMDD-HHMMSS-RRR (Package-YearMonthDay-HourMinuteSecond-Random)
            const date = new Date(timestamp);
            const year = date.getFullYear();
            const month = (date.getMonth() + 1).toString().padStart(2, '0');
            const day = date.getDate().toString().padStart(2, '0');
            const hours = date.getHours().toString().padStart(2, '0');
            const minutes = date.getMinutes().toString().padStart(2, '0');
            const seconds = date.getSeconds().toString().padStart(2, '0');

            const trackingNumber = `PKG-${year}${month}${day}-${hours}${minutes}${seconds}-${randomSuffix}`;

            // Store the tracking number for this package (for later use if needed)
            if (!window.packageTrackingNumbers) {
                window.packageTrackingNumbers = new Map();
            }
            window.packageTrackingNumbers.set(itemId, trackingNumber);

            return trackingNumber;
        }

        function cancelAndExit() {
            @if($servicePoint)
            fetch('{{ route('calling.announce') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    client_id: {{ $client->id }},
                    client_name: '{{ addslashes($client->name) }}',
                    service_point_id: {{ $servicePoint->id }},
                    type: 'stop-serving'
                })
            }).then(() => {
                window.location.href = '{{ route('service-points.show', $servicePoint) }}';
            }).catch(() => {
                window.location.href = '{{ route('service-points.show', $servicePoint) }}';
            });
            @else
            window.location.href = '{{ route('clients.index') }}';
            @endif
        }

        function saveAndExit() {
            // Log the save and exit action
            console.log('=== POS ITEM SELECTION - SAVE AND EXIT TRIGGERED ===', {
                client_id: {{ $client->id }},
                client_name: '{{ $client->name }}',
                page: 'POS Item Selection',
                action: 'Save and Exit',
                cart_items: cart,
                cart_count: cart.length,
                package_adjustment: packageAdjustment,
                total_package_adjustment: packageAdjustment.total_adjustment || 0,
                timestamp: new Date().toISOString()
            });

            // Show simple confirmation dialog
            Swal.fire({
                title: 'Save Changes?',
                text: 'Are you sure you want to save the selected statuses?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Save',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Get form data
                    const form = document.getElementById('itemStatusForm');
                    if (!form) {
                        Swal.fire('Error', 'Form not found', 'error');
                        return;
                    }

                    const formData = new FormData(form);

                    // Debug: Log form data
                    console.log('Form data being sent:', formData);
                    for (let [key, value] of formData.entries()) {
                        console.log('Form field:', key, '=', value);
                    }

                    // Show loading
                    Swal.fire({
                        title: 'Saving...',
                        text: 'Please wait',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Send request
                    @if($servicePoint)
                    const url = '{{ route("service-points.update-statuses-and-process-money", [$servicePoint, $client->id]) }}';
                    @else
                    const url = '{{ route("service-points.update-statuses-and-process-money", [0, $client->id]) }}';
                    @endif

                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        },
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Clear this client from the TV display
                            try {
                                fetch('{{ route("calling.announce") }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                    },
                                    body: JSON.stringify({
                                        client_id: {{ $client->id }},
                                        client_name: '{{ addslashes($client->name) }}',
                                        service_point_id: {{ $servicePoint->id ?? 'null' }},
                                        type: 'stop-serving'
                                    })
                                });
                            } catch (e) {
                                console.error('Failed to stop serving on display:', e);
                            }

                            Swal.fire({
                                title: 'Success!',
                                text: 'Changes saved successfully',
                                icon: 'success',
                                confirmButtonColor: '#10b981'
                            }).then(() => {
                                window.location.href = '{{ route("clients.index") }}';
                            });
                        } else {
                            Swal.fire('Error', data.message || 'Failed to save changes', 'error');
                        }
                    })
                    .catch(error => {
                        Swal.fire('Error', 'Something went wrong', 'error');
                    });
                }
            });
        }

        // Export package tracking numbers to CSV

        function showAdmitModal(redirectTo = '{{ route('pos.item-selection', $client) }}') {
            Swal.fire({
                title: 'Admit Patient',
                html: `
                    <p class="text-gray-700 mb-4">Select admission type(s). You can select both options:</p>
                    <div class="text-left space-y-3 mb-4">
                        <label class="flex items-start p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" id="enable_credit" class="mt-1 mr-3" {{ $client->is_credit_eligible ? 'checked' : '' }}>
                            <div>
                                <span class="font-medium">Enable Credit Services</span>
                                <p class="text-sm text-gray-600 mt-1">Allows client to access services on credit. Visit ID will have <strong>/C</strong> suffix.</p>
                            </div>
                        </label>
                        <label class="flex items-start p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" id="enable_long_stay" class="mt-1 mr-3">
                            <div>
                                <span class="font-medium">Enable Long-Stay / Inpatient</span>
                                <p class="text-sm text-gray-600 mt-1">Visit ID will stay active until manually discharged. Visit ID will have <strong>/M</strong> suffix.</p>
                            </div>
                        </label>
                    </div>
                    <div id="credit-limit-section" class="mt-4 p-3 bg-yellow-50 rounded-lg border border-yellow-200" style="display: none;">
                        <label for="max_credit" class="block text-sm font-medium text-gray-700 mb-2">
                            Maximum Credit Limit (UGX) <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="number"
                            id="max_credit"
                            name="max_credit"
                            step="0.01"
                            min="0"
                            value="{{ $business->max_first_party_credit_limit ?? 0 }}"
                            placeholder="Enter credit limit"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                        <p class="text-xs text-gray-600 mt-1">
                            Default: {{ $business->max_first_party_credit_limit ? number_format($business->max_first_party_credit_limit, 2) . ' UGX (Business First Party Credit Limit)' : 'Not set - please configure in Business Settings' }}
                        </p>
                    </div>
                    <div id="visit-id-preview" class="text-center p-3 bg-blue-50 rounded-lg border border-blue-200 mt-4">
                        <p class="text-sm text-gray-600 mb-1">Visit ID Preview:</p>
                        <p class="text-lg font-bold text-blue-700" id="preview-visit-id">{{ $client->visit_id }}</p>
                    </div>
                `,
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Admit',
                cancelButtonText: 'Cancel',
                didOpen: () => {
                    // Update preview when checkboxes change
                    const creditCheckbox = document.getElementById('enable_credit');
                    const longStayCheckbox = document.getElementById('enable_long_stay');
                    const previewElement = document.getElementById('preview-visit-id');
                    const creditLimitSection = document.getElementById('credit-limit-section');
                    const creditLimitInput = document.getElementById('max_credit');
                    const baseVisitId = '{{ preg_replace('/\/(C\/M|C|M)$/', '', $client->visit_id) }}';
                    const defaultCreditLimit = {{ $business->max_first_party_credit_limit ?? 0 }};

                    function updatePreview() {
                        const hasCredit = creditCheckbox.checked;
                        const hasLongStay = longStayCheckbox.checked;

                        // Show/hide credit limit section
                        if (hasCredit) {
                            creditLimitSection.style.display = 'block';
                            // Set default if empty
                            if (!creditLimitInput.value || parseFloat(creditLimitInput.value) === 0) {
                                creditLimitInput.value = defaultCreditLimit;
                            }
                        } else {
                            creditLimitSection.style.display = 'none';
                        }

                        let suffix = '';
                        if (hasCredit && hasLongStay) {
                            suffix = '/C/M';
                        } else if (hasCredit) {
                            suffix = '/C';
                        } else if (hasLongStay) {
                            suffix = '/M';
                        }

                        previewElement.textContent = baseVisitId + suffix;
                    }

                    creditCheckbox.addEventListener('change', updatePreview);
                    longStayCheckbox.addEventListener('change', updatePreview);
                    updatePreview(); // Initial update
                },
                preConfirm: () => {
                    const enableCredit = document.getElementById('enable_credit').checked;
                    const enableLongStay = document.getElementById('enable_long_stay').checked;

                    if (!enableCredit && !enableLongStay) {
                        Swal.showValidationMessage('Please select at least one option: Credit Services, Long-Stay, or both.');
                        return false;
                    }

                    // Validate credit limit if credit is enabled
                    if (enableCredit) {
                        const creditLimit = parseFloat(document.getElementById('max_credit').value);
                        if (!creditLimit || creditLimit <= 0) {
                            Swal.showValidationMessage('Please enter a valid credit limit greater than 0.');
                            return false;
                        }
                    }

                    const result = {
                        enable_credit: enableCredit,
                        enable_long_stay: enableLongStay
                    };

                    if (enableCredit) {
                        result.max_credit = document.getElementById('max_credit').value;
                    }

                    return result;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Submit the form
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '{{ route('clients.admit', $client) }}';

                    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = '_token';
                    csrfInput.value = csrfToken;
                    form.appendChild(csrfInput);

                    const redirectInput = document.createElement('input');
                    redirectInput.type = 'hidden';
                    redirectInput.name = 'redirect_to';
                    redirectInput.value = redirectTo;
                    form.appendChild(redirectInput);

                    if (result.value.enable_credit) {
                        const creditInput = document.createElement('input');
                        creditInput.type = 'hidden';
                        creditInput.name = 'enable_credit';
                        creditInput.value = '1';
                        form.appendChild(creditInput);
                    }

                    if (result.value.enable_long_stay) {
                        const longStayInput = document.createElement('input');
                        longStayInput.type = 'hidden';
                        longStayInput.name = 'enable_long_stay';
                        longStayInput.value = '1';
                        form.appendChild(longStayInput);
                    }

                    if (result.value.enable_credit && result.value.max_credit) {
                        const maxCreditInput = document.createElement('input');
                        maxCreditInput.type = 'hidden';
                        maxCreditInput.name = 'max_credit';
                        maxCreditInput.value = result.value.max_credit;
                        form.appendChild(maxCreditInput);
                    }

                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        async function admitClientFromServicePoint(admitUrl, redirectTo, queueItemId) {
            // Confirm with SweetAlert2
            const result = await Swal.fire({
                title: 'Admit Patient?',
                text: 'Are you sure you want to admit this patient? This will process this item and update the patient status.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#16a34a',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, admit patient',
                cancelButtonText: 'Cancel'
            });

            if (!result.isConfirmed) {
                return;
            }

            // Get business settings for admission
            @php
                $business = $client->business ?? \App\Models\Business::find($client->business_id);
            @endphp
            const admitEnableCredit = {{ ($business->admit_enable_credit ?? false) ? 'true' : 'false' }};
            const admitEnableLongStay = {{ ($business->admit_enable_long_stay ?? false) ? 'true' : 'false' }};
            const defaultMaxCredit = {{ $business->max_first_party_credit_limit ?? 0 }};

            // Create and submit the admit form directly using business settings
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = admitUrl;

            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);

            const redirectInput = document.createElement('input');
            redirectInput.type = 'hidden';
            redirectInput.name = 'redirect_to';
            redirectInput.value = redirectTo;
            form.appendChild(redirectInput);

            // Add queue item ID to process only this specific item
            if (queueItemId) {
                const queueItemInput = document.createElement('input');
                queueItemInput.type = 'hidden';
                queueItemInput.name = 'queue_item_id';
                queueItemInput.value = queueItemId;
                form.appendChild(queueItemInput);
            }

            if (admitEnableCredit) {
                const creditInput = document.createElement('input');
                creditInput.type = 'hidden';
                creditInput.name = 'enable_credit';
                creditInput.value = '1';
                form.appendChild(creditInput);

                if (defaultMaxCredit > 0) {
                    const maxCreditInput = document.createElement('input');
                    maxCreditInput.type = 'hidden';
                    maxCreditInput.name = 'max_credit';
                    maxCreditInput.value = defaultMaxCredit;
                    form.appendChild(maxCreditInput);
                }
            }

            if (admitEnableLongStay) {
                const longStayInput = document.createElement('input');
                longStayInput.type = 'hidden';
                longStayInput.name = 'enable_long_stay';
                longStayInput.value = '1';
                form.appendChild(longStayInput);
            }

            document.body.appendChild(form);
            form.submit();
        }

        function confirmDischarge() {
            const dischargeRemoveCredit = {{ $business->discharge_remove_credit ? 'true' : 'false' }};
            const dischargeRemoveLongStay = {{ ($business->discharge_remove_long_stay ?? true) ? 'true' : 'false' }};

            let dischargeText = 'This will:';
            const changes = [];

            if (dischargeRemoveLongStay) {
                changes.push('Remove long-stay status (<strong>/M</strong> suffix)');
            }
            if (dischargeRemoveCredit) {
                changes.push('Remove credit services (<strong>/C</strong> suffix)');
            }

            if (changes.length > 0) {
                dischargeText += '<ul class="text-left mt-2 space-y-1">';
                changes.forEach(change => {
                    dischargeText += '<li>• ' + change + '</li>';
                });
                dischargeText += '</ul>';
            }

            dischargeText += '<p class="mt-3">The visit ID will be regenerated and made available for reissuance.</p>';

            Swal.fire({
                title: 'Discharge Patient?',
                html: dischargeText,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Discharge',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('discharge-form').submit();
                }
            });
        }
    </script>

    <!-- Payment Methods Modal -->
    <div id="payment-methods-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-2xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Edit Payment Methods</h3>
                    <button onclick="closePaymentMethodsModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="mb-4">
                    <p class="text-sm text-gray-600 mb-3">Select payment methods in order of preference:</p>

                    <div class="space-y-3">
                        <label class="flex items-center">
                            <input type="checkbox" name="payment_methods[]" value="insurance"
                                   {{ in_array('insurance', $client->payment_methods ?? []) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <span class="ml-3 text-sm font-medium text-gray-700">🏥 Insurance</span>
                        </label>

                        <label class="flex items-center">
                            <input type="checkbox" name="payment_methods[]" value="credit_arrangement_institutions"
                                   {{ in_array('credit_arrangement_institutions', $client->payment_methods ?? []) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <span class="ml-3 text-sm font-medium text-gray-700">🏦 Credit Arrangement Institutions</span>
                        </label>

                        <label class="flex items-center">
                            <input type="checkbox" name="payment_methods[]" value="mobile_money"
                                   {{ in_array('mobile_money', $client->payment_methods ?? []) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <span class="ml-3 text-sm font-medium text-gray-700">📱 Mobile Money</span>
                        </label>

                        <label class="flex items-center">
                            <input type="checkbox" name="payment_methods[]" value="v_card"
                                   {{ in_array('v_card', $client->payment_methods ?? []) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <span class="ml-3 text-sm font-medium text-gray-700">💳 V Card (Virtual Card)</span>
                        </label>

                        <label class="flex items-center">
                            <input type="checkbox" name="payment_methods[]" value="p_card"
                                   {{ in_array('p_card', $client->payment_methods ?? []) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <span class="ml-3 text-sm font-medium text-gray-700">💳 P Card (Physical Card)</span>
                        </label>

                        <label class="flex items-center">
                            <input type="checkbox" name="payment_methods[]" value="bank_transfer"
                                   {{ in_array('bank_transfer', $client->payment_methods ?? []) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <span class="ml-3 text-sm font-medium text-gray-700">🏦 Bank Transfer</span>
                        </label>

                        <label class="flex items-center">
                            <input type="checkbox" name="payment_methods[]" value="cash"
                                   {{ in_array('cash', $client->payment_methods ?? []) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <span class="ml-3 text-sm font-medium text-gray-700">💵 Cash (if enabled)</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button onclick="closePaymentMethodsModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 transition-colors">
                        Cancel
                    </button>
                    <button onclick="savePaymentMethods()" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors">
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    @include('partials.document-format-script', ['business' => auth()->user()?->business])

    @stack('scripts')
</x-app-layout>
