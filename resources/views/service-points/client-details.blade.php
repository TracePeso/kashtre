<x-app-layout>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
                {{ __('Service point') }}
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
            
            <!-- Section 1: Client Summary Details -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6" x-data="{ expanded: true }">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Client Summary Details</h3>
                        <button @click="expanded = !expanded" class="text-gray-500 hover:text-gray-700 transition-colors">
                            <svg x-show="expanded" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                            </svg>
                            <svg x-show="!expanded" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                    </div>
                    <div x-show="expanded" x-transition>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-500 mb-1">Names</p>
                            <p class="text-lg font-semibold text-gray-900">{{ $client->name }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-500 mb-1">Age</p>
                            <p class="text-lg font-semibold text-gray-900">{{ $client->age ?? 'N/A' }} years</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-500 mb-1">Sex</p>
                            <p class="text-lg font-semibold text-gray-900">{{ ucfirst($client->sex) }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-500 mb-1">Client ID</p>
                            <p class="text-lg font-semibold text-gray-900">{{ $client->client_id }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-500 mb-1">Visit ID</p>
                            <p class="text-lg font-semibold text-gray-900">{{ $client->visit_id }}</p>
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
            
            <!-- Section 2: Client Statement -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Client Statement</h3>
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
                            <p class="text-xl font-bold text-yellow-600">0</p>
                        </div>
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
                                                        <span class="text-gray-500">(Default Price)</span>
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
                <a href="{{ route('service-points.show', $servicePoint) }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-colors">
                    Cancel
                </a>
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
                        <p class="text-sm text-gray-600">Branch: {{ auth()->user()->currentBranch?->name ?? 'N/A' }}</p>
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
                
                <!-- Action Buttons -->
                <div class="flex justify-end space-x-4 mt-6">
                    <button onclick="closeInvoicePreview()" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 transition-colors">
                        Close
                    </button>
                    <button onclick="confirmAndSaveInvoice()" class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors">
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
        
        // Add event listeners to quantity inputs
        document.addEventListener('DOMContentLoaded', function() {
            // Check initial payment methods for mobile money
            const initialPaymentMethods = @json($client->payment_methods ?? []);
            if (initialPaymentMethods.includes('mobile_money')) {
                document.getElementById('payment-phone-section').style.display = 'block';
            }
            
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
                    console.log('Item:', itemName, 'Raw Price:', rawPrice, 'Parsed Price:', itemPrice, 'Quantity:', quantity, 'Type:', itemType);
                    
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
        }
        
        function removeFromCart(index) {
            const removedItem = cart[index];
            cart.splice(index, 1);
            
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
            
            // CRITICAL: Check for warnings FIRST - if found, show alert and STOP everything
            if (packageAdjustmentData && packageAdjustmentData.max_qty_warnings && packageAdjustmentData.max_qty_warnings.length > 0) {
                const warning = packageAdjustmentData.max_qty_warnings[0];
                
                await Swal.fire({
                    icon: 'warning',
                    title: 'Maximum Total Quantity Exceeded',
                    html: `
                        <p><strong>${warning.package_name}</strong></p>
                        <p>${warning.message}</p>
                        <p class="mt-3">Please update the Maximum Total Quantity before proceeding.</p>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Update Settings',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33'
                });
                
                // CRITICAL: Return immediately - do NOT continue
                return;
            }
            
            // Only continue here if NO warnings
            packageAdjustment = packageAdjustmentData ? (packageAdjustmentData.total_adjustment || 0) : 0;
            
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
            balanceAdjustment = balanceAdjustmentData.balance_adjustment;
            
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
            serviceCharge = serviceChargeData.amount;
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
            console.log('🔵 confirmAndSaveInvoice() CALLED');
            
            if (cart.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Empty Cart',
                    text: 'Please add items to cart before saving invoice'
                });
                return;
            }

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

            // FIRST: Check for max_qty warnings BEFORE showing confirmation dialog
            console.log('🔵 Starting max_qty check...');
            try {
                // Calculate totals
                let subtotal = 0;
                cart.forEach(item => {
                    subtotal += parseFloat(item.price || 0) * parseInt(item.quantity || 0);
                });
                
                console.log('🔵 Calling calculatePackageAdjustment()...');
                const packageAdjustmentData = await calculatePackageAdjustment();
                console.log('🔵=== MAX_QTY CHECK IN confirmAndSaveInvoice ===');
                console.log('🔵 packageAdjustmentData:', JSON.stringify(packageAdjustmentData, null, 2));
                console.log('🔵 Has max_qty_warnings property?', 'max_qty_warnings' in (packageAdjustmentData || {}));
                console.log('🔵 max_qty_warnings value:', packageAdjustmentData?.max_qty_warnings);
                console.log('🔵 Is array?', Array.isArray(packageAdjustmentData?.max_qty_warnings));
                console.log('🔵 Length:', packageAdjustmentData?.max_qty_warnings?.length);
                
                // CRITICAL: Check for warnings - if found, show alert and STOP save
                if (packageAdjustmentData && packageAdjustmentData.max_qty_warnings && packageAdjustmentData.max_qty_warnings.length > 0) {
                    const warning = packageAdjustmentData.max_qty_warnings[0];
                    
                    await Swal.fire({
                        icon: 'warning',
                        title: 'Maximum Total Quantity Exceeded',
                        html: `
                            <p><strong>${warning.package_name}</strong></p>
                            <p>${warning.message}</p>
                            <p class="mt-3">Please update the Maximum Total Quantity before proceeding.</p>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Update Settings',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33'
                    });
                    
                    // STOP - do NOT continue
                    return;
                }
            } catch (error) {
                console.error('Error checking package adjustment:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while checking package adjustments. Please try again.'
                });
                return;
            }

            // Only show confirmation dialog if no warnings
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
            
            // Show loading state
            const button = event.target;
            const originalText = button.textContent;
            button.textContent = 'Saving...';
            button.disabled = true;
            
            try {
                // Calculate totals again (for final save)
                let subtotal = 0;
                cart.forEach(item => {
                    subtotal += parseFloat(item.price || 0) * parseInt(item.quantity || 0);
                });
                
                const packageAdjustmentData = await calculatePackageAdjustment();
                
                // CRITICAL: Double-check for warnings before saving
                if (packageAdjustmentData && packageAdjustmentData.max_qty_warnings && packageAdjustmentData.max_qty_warnings.length > 0) {
                    button.textContent = originalText;
                    button.disabled = false;
                    
                    const warning = packageAdjustmentData.max_qty_warnings[0];
                    
                    await Swal.fire({
                        icon: 'error',
                        title: 'Cannot Save Invoice',
                        html: `
                            <p><strong>${warning.package_name}</strong></p>
                            <p>${warning.message}</p>
                            <p class="mt-3">Please update the Maximum Total Quantity before saving.</p>
                        `,
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#d33'
                    });
                    
                    // STOP - do NOT save
                    return;
                }
                
                packageAdjustment = parseFloat(packageAdjustmentData.total_adjustment) || 0;
                let adjustedSubtotal = parseFloat(subtotal) - parseFloat(packageAdjustment);
                
                // Ensure adjustedSubtotal never goes below 0
                if (adjustedSubtotal < 0) {
                    adjustedSubtotal = 0;
                }
                
                const serviceChargeData = await calculateServiceCharge(adjustedSubtotal);
                serviceCharge = serviceChargeData.amount;
                
                const subtotalWithServiceCharge = parseFloat(adjustedSubtotal) + parseFloat(serviceCharge);
                
                // Calculate balance adjustment
                const balanceAdjustmentData = await calculateBalanceAdjustment(subtotalWithServiceCharge);
                balanceAdjustment = parseFloat(balanceAdjustmentData.balance_adjustment) || 0;
                let totalAmount = parseFloat(subtotalWithServiceCharge) - parseFloat(balanceAdjustment);
                
                // Ensure totalAmount never goes below 0
                if (totalAmount < 0) {
                    totalAmount = 0;
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
                    button.textContent = originalText;
                    button.disabled = false;
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
                    button.textContent = originalText;
                    button.disabled = false;
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
                
                // Check if mobile money is selected and process payment
                let paymentResult = null;
                let amountPaid = 0;
                
                // Only process mobile money payment if total amount > 0
                if (paymentMethods.includes('mobile_money') && paymentPhone && totalAmount > 0) {
                    console.log('=== PROCESSING MOBILE MONEY PAYMENT ===');
                    console.log('Processing mobile money payment:', { 
                        totalAmount, 
                        paymentPhone,
                        totalAmountType: typeof totalAmount,
                        isNaN: isNaN(totalAmount),
                        parseFloatResult: parseFloat(totalAmount)
                    });
                    
                    // Show payment processing dialog
                    Swal.fire({
                        title: 'Processing Mobile Money Payment',
                        html: `
                            <div class="text-center">
                                <div class="mb-4">
                                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
                                </div>
                                <p class="text-lg font-semibold">UGX ${parseFloat(totalAmount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                                <p class="text-sm text-gray-600">To: ${paymentPhone}</p>
                                <p class="text-sm text-gray-500 mt-2">Please wait while we process your payment...</p>
                            </div>
                        `,
                        showConfirmButton: false,
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    });
                    
                    // Process mobile money payment
                    paymentResult = await processMobileMoneyPayment(totalAmount, paymentPhone);
                    
                    if (paymentResult.success) {
                        amountPaid = totalAmount;
                        Swal.fire({
                            icon: 'success',
                            title: 'Payment Successful!',
                            html: `
                                <div class="text-center">
                                    <p class="text-lg font-semibold text-green-600">UGX ${parseFloat(totalAmount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                                    <p class="text-sm text-gray-600">Transaction ID: ${paymentResult.transaction_id}</p>
                                    <p class="text-sm text-gray-500">Paid via Mobile Money</p>
                                </div>
                            `,
                            timer: 3000,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Payment Failed',
                            text: 'Mobile money payment could not be processed. Please try again.',
                            confirmButtonText: 'OK'
                        });
                        return;
                    }
                } else if (paymentMethods.includes('mobile_money') && totalAmount === 0) {
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
                
                // Get the invoice number from the display
                const invoiceNumber = document.getElementById('invoice-number-display').textContent;
                
                // Validate invoice number
                if (!invoiceNumber || invoiceNumber === 'Generating...' || invoiceNumber === 'Error generating invoice number') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Proforma Invoice Number',
                        text: 'Please wait for the proforma invoice number to be generated before saving.'
                    });
                    return;
                }
                
                // Prepare invoice data with all required fields
                const invoiceData = {
                    invoice_number: invoiceNumber,
                    client_id: {{ $client->id }},
                    business_id: {{ auth()->user()->business_id }},
                    branch_id: {{ auth()->user()->currentBranch?->id ?? 'null' }},
                    created_by: {{ auth()->id() }},
                    client_name: '{{ $client->name }}',
                    client_phone: '{{ $client->phone_number }}',
                    payment_phone: paymentPhone,
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
                
                console.log('Proforma Invoice data being sent:', invoiceData);
                
                // Save invoice
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
                
                if (data.success) {
                    // Clear cart
                    cart = [];
                    updateRequestOrderSummaryDisplay();
                    
                    // Close order/request summary
                    closeInvoicePreview();
                    
                    // Show success with options
                    const result = await Swal.fire({
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
                    });
                    
                    if (result.isConfirmed) {
                        // View invoice
                        window.location.href = `/invoices/${data.invoice.id}`;
                    } else if (result.dismiss === Swal.DismissReason.cancel) {
                        // Print invoice
                        const printWindow = window.open(`/invoices/${data.invoice.id}/print`, '_blank');
                        
                        // Show print confirmation
                        setTimeout(() => {
                            Swal.fire({
                                icon: 'info',
                                title: 'Print Window Opened',
                                html: `
                                    <div class="text-center">
                                        <p class="mb-2">Proforma invoice print window has been opened.</p>
                                        <p class="text-sm text-gray-600">Proforma Invoice Number: <strong>${invoiceNumber}</strong></p>
                                        <p class="text-xs text-gray-500 mt-2">Please check the new tab/window for printing options.</p>
                                    </div>
                                `,
                                timer: 3000,
                                showConfirmButton: false
                            });
                        }, 1000);
                    } else if (result.isDenied) {
                        // Stay Here - refresh the page to show updated package tracking
                        window.location.reload();
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
                // Restore button state
                button.textContent = originalText;
                button.disabled = false;
            }
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
                        branch_id: {{ auth()->user()->currentBranch?->id ?? 'null' }}
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
                
                const data = await response.json();
                console.log('Package adjustment response:', data);
                console.log('Response has max_qty_warnings?', !!data.max_qty_warnings);
                console.log('max_qty_warnings value:', data.max_qty_warnings);
                console.log('max_qty_warnings type:', typeof data.max_qty_warnings);
                console.log('max_qty_warnings is array?', Array.isArray(data.max_qty_warnings));
                console.log('max_qty_warnings length:', data.max_qty_warnings?.length);
                
                if (data.success) {
                    // Return the adjustment data
                    return {
                        total_adjustment: parseFloat(data.total_adjustment) || 0,
                        details: data.details || [],
                        max_qty_warnings: data.max_qty_warnings || []
                    };
                } else {
                    console.error('Package adjustment calculation error:', data.message);
                    return { total_adjustment: 0, details: [] };
                }
            } catch (error) {
                console.error('Error calculating package adjustment:', error);
                // Re-throw max_qty errors so they can be caught by the calling function
                if (error.message && error.message.includes('MAX_QTY_EXCEEDED')) {
                    console.log('Re-throwing MAX_QTY_EXCEEDED error');
                    throw error;
                }
                return { total_adjustment: 0, details: [] };
            }
                } else {
                    console.error('Package adjustment calculation error:', data.message);
                    return { total_adjustment: 0, details: [], max_qty_warnings: data.max_qty_warnings || [] };
                }
            } catch (error) {
                console.error('Error calculating package adjustment:', error);
                return { total_adjustment: 0, details: [], max_qty_warnings: [] };
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
            const timestamp = Date.now();
            const randomSuffix = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
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
        
        function saveAndExit() {
            // Log the save and exit action
            console.log('=== SERVICE POINTS CLIENT DETAILS - SAVE AND EXIT TRIGGERED ===', {
                client_id: {{ $client->id }},
                client_name: '{{ $client->name }}',
                page: 'Service Points Client Details',
                action: 'Save and Exit',
                timestamp: new Date().toISOString()
            });
            
            // Debug: Check if packageAdjustment is defined
            console.log('packageAdjustment value:', packageAdjustment);
            console.log('packageAdjustment type:', typeof packageAdjustment);

            // Show simple confirmation dialog
            Swal.fire({
                title: 'Save Proforma Invoice?',
                text: 'Are you sure you want to save this proforma invoice?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Save',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Call the existing saveInvoice function
                    saveInvoice();
                }
            });
        }
        
        // Export package tracking numbers to CSV
        
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
    
    @stack('scripts')
</x-app-layout>
