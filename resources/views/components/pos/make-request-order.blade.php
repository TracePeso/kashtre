{{-- 
    Unified Make a Request/Order Component
    This component serves as a single source of truth for the Make a Request/Order section
    across both POS item selection and Service Point client details pages.
    It includes all package adjustment functionality and cart management.
--}}

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
                                            if ($item->generic_name && !empty(trim($item->generic_name))) {
                                                $descriptionParts[] = "Generic: {$item->generic_name}";
                                            }

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