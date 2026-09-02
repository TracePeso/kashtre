<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4">Edit Item</h2>

                @if($errors->any())
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <ul class="list-disc list-inside">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('items.update', $item) }}">
                    @csrf
                    @method('PATCH')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @if($canSelectBusiness)
                        <!-- Business Selection (only for business_id == 1) -->
                        <div>
                            <label for="business_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Business <span class="text-red-500">*</span></label>
                            <select name="business_id" id="business_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                                @foreach($businesses as $business)
                                    <option value="{{ $business->id }}" {{ old('business_id', $item->business_id) == $business->id ? 'selected' : '' }}>{{ $business->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @else
                        <!-- Hidden business field for non-admin users -->
                        <input type="hidden" name="business_id" value="{{ $item->business_id }}">
                        @endif

                        <!-- Name -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" id="name" value="{{ old('name', $item->name) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required placeholder="Enter item name">
                        </div>

                        <div>
                            <label for="generic_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Generic Name</label>
                            <input type="text" name="generic_name" id="generic_name" value="{{ old('generic_name', $item->generic_name) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="e.g. Paracetamol">
                        </div>

                        <div>
                            <label for="strength" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Strength</label>
                            <input type="text" name="strength" id="strength" value="{{ old('strength', $item->strength) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="e.g. 500mg">
                        </div>

                        <!-- Type -->
                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Type <span class="text-red-500">*</span></label>
                            <select name="type" id="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                                <option value="service" {{ old('type', $item->type) == 'service' ? 'selected' : '' }}>Service</option>
                                <option value="good" {{ old('type', $item->type) == 'good' ? 'selected' : '' }}>Good</option>
                                <option value="package" {{ old('type', $item->type) == 'package' ? 'selected' : '' }}>Package</option>
                                <option value="bulk" {{ old('type', $item->type) == 'bulk' ? 'selected' : '' }}>Bulk</option>
                            </select>
                        </div>

                        <!-- Category (importance) — goods only -->
                        <div class="good-only inventory-good-fields">
                            <label for="importance_category" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Category <span class="text-red-500">*</span></label>
                            <select name="importance_category" id="importance_category" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                                <option value="" disabled {{ old('importance_category', $item->importance_category) ? '' : 'selected' }}>Select category</option>
                                @foreach($importanceOptions as $value => $label)
                                    <option value="{{ $value }}" {{ old('importance_category', $item->importance_category) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('importance_category')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <!-- Code -->
                        <div>
                            <label for="code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Code</label>
                            <div class="mt-1 relative">
                                <input type="text" name="code" id="code" value="{{ old('code', $item->code) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white bg-gray-100 dark:bg-gray-800" placeholder="Auto-generated code" readonly>
                                <button type="button" id="generate_code_btn" class="absolute inset-y-0 right-0 px-3 flex items-center bg-gray-100 hover:bg-gray-200 dark:bg-gray-600 dark:hover:bg-gray-500 border-l border-gray-300 dark:border-gray-500 rounded-r-md">
                                    <svg class="h-4 w-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                </button>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">Click the refresh button to generate a new code</p>
                        </div>

                        <!-- Group -->
                        <div class="service-good-only">
                            <label for="group_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Group <span class="text-red-500">*</span></label>
                            <select name="group_id" id="group_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                                <option value="" disabled>Select group</option>
                                @foreach($groups as $group)
                                    <option value="{{ $group->id }}" {{ old('group_id', $item->group_id) == $group->id ? 'selected' : '' }}>
                                        {{ $group->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Subgroup -->
                        <div class="service-good-only">
                            <label for="subgroup_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Subgroup <span class="text-red-500">*</span></label>
                            <select name="subgroup_id" id="subgroup_id" data-required="true" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                                <option value="" disabled>Select subgroup</option>
                                @foreach($groups as $group)
                                    <option value="{{ $group->id }}" {{ old('subgroup_id', $item->subgroup_id) == $group->id ? 'selected' : '' }}>
                                        {{ $group->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Department -->
                        <div class="service-good-only">
                            <label for="department_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Department <span class="text-red-500">*</span></label>
                            <select name="department_id" id="department_id" data-required="true" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                                <option value="" disabled>Select department</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->id }}" {{ old('department_id', $item->department_id) == $department->id ? 'selected' : '' }}>
                                        {{ $department->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Unit of Measure -->
                        <div class="service-good-only">
                            <label for="uom_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Sale unit <span class="text-red-500">*</span></label>
                            <select name="uom_id" id="uom_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                                <option value="" disabled>Select sale unit</option>
                                @foreach($itemUnits as $itemUnit)
                                    <option value="{{ $itemUnit->id }}" {{ old('uom_id', $item->uom_id) == $itemUnit->id ? 'selected' : '' }}>
                                        {{ $itemUnit->name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Unit in which the business sells or issues this item.</p>
                        </div>

                        <div class="good-only inventory-good-fields">
                            <label for="order_unit_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Order unit</label>
                            <select name="order_unit_id" id="order_unit_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="">Same as sale unit</option>
                                @foreach($itemUnits as $itemUnit)
                                    <option value="{{ $itemUnit->id }}" {{ old('order_unit_id', $item->order_unit_id) == $itemUnit->id ? 'selected' : '' }}>
                                        {{ $itemUnit->name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Supplier quote unit. The delivery unit is chosen per receipt.</p>
                        </div>

                        <div class="good-only inventory-good-fields">
                            <label for="suom_per_ouom" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Sale units per order unit</label>
                            <input type="number" name="suom_per_ouom" id="suom_per_ouom" step="0.0001" min="0.0001"
                                   value="{{ old('suom_per_ouom', $item->suom_per_ouom) }}"
                                   placeholder="e.g. 100"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <p class="mt-1 text-xs text-gray-500">How many sale units are in one order unit (e.g. 100 tablets per box).</p>
                        </div>

                        <!-- Branch Service Points Section -->
                        <div class="md:col-span-2 service-good-only">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Branch Service Points</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Select service points from different branches for this item</p>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                @foreach($branches as $branch)
                                <div class="border rounded-lg p-4 bg-gray-50 dark:bg-gray-700">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ $branch->name }}
                                    </label>
                                    <div class="space-y-2">
                                        @php
                                            $branchServicePoints = $servicePoints->where('branch_id', $branch->id);
                                            $selectedServicePoint = $item->branchServicePoints()
                                                ->where('branch_id', $branch->id)
                                                ->first();
                                        @endphp
                                        @if($branchServicePoints->count() > 0)
                                            <select name="branch_service_points[{{ $branch->id }}]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                                <option value="">Select service point</option>
                                                @foreach($branchServicePoints as $servicePoint)
                                                    <option value="{{ $servicePoint->id }}" {{ $selectedServicePoint && $selectedServicePoint->service_point_id == $servicePoint->id ? 'selected' : '' }}>
                                                        {{ $servicePoint->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @else
                                            <p class="text-sm text-gray-500 dark:text-gray-400">No service points available for this branch</p>
                                        @endif
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Other Name -->
                        <div class="md:col-span-2">
                            <label for="other_names" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Other Name <span class="text-red-500">*</span></label>
                            <input type="text" name="other_names" id="other_names" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="Enter other name" value="{{ old('other_names', $item->other_names) }}">
                        </div>

                        <!-- Description -->
                        <div class="md:col-span-2">
                            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                            <textarea name="description" id="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="Enter description">{{ old('description', $item->description) }}</textarea>
                        </div>
                    </div>

                    <!-- Branch Pricing Type Selection -->
                    @if(count($branches) > 0)
                    <div class="mt-8">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Branch Pricing</h3>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
                            <div class="space-y-4">
                                <label class="flex items-center">
                                    <input type="radio" name="pricing_type" value="default" id="default_pricing" 
                                           {{ old('pricing_type', $item->branchPrices->count() == 0 ? 'default' : 'custom') == 'default' ? 'checked' : '' }} 
                                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                                    <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Use sale price for all branches</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="pricing_type" value="custom" id="custom_pricing" 
                                           {{ old('pricing_type', $item->branchPrices->count() > 0 ? 'custom' : '') == 'custom' ? 'checked' : '' }} 
                                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                                    <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Set different prices for each branch</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Branch Pricing Section -->
                    <div class="mt-8 branch-pricing-section" id="branch_pricing_section" style="display: {{ $item->branchPrices->count() > 0 ? 'block' : 'none' }};">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Branch-Specific Pricing</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($branches as $branch)
                            @php
                                $branchPrice = $item->branchPrices->where('branch_id', $branch->id)->first();
                            @endphp
                            <div class="border rounded-lg p-4 bg-gray-50 dark:bg-gray-700">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                    {{ $branch->name }}
                                </label>
                                <input type="hidden"
                                       name="branch_prices[{{ $loop->index }}][branch_id]"
                                       value="{{ $branch->id }}">
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Sale price</label>
                                        <input type="number"
                                               name="branch_prices[{{ $loop->index }}][price]"
                                               step="0.01"
                                               min="0"
                                               value="{{ old('branch_prices.'.$loop->index.'.price', $branchPrice?->price) }}"
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                               placeholder="0.00">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Purchase price</label>
                                        <input type="number"
                                               name="branch_prices[{{ $loop->index }}][purchase_price]"
                                               step="0.01"
                                               min="0"
                                               value="{{ old('branch_prices.'.$loop->index.'.purchase_price', $branchPrice?->purchase_price) }}"
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                               placeholder="0.00">
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <!-- Package Items Section -->
                    <div class="mt-8 package-items-section" id="package_items_section" style="display: {{ $item->type === 'package' ? 'block' : 'none' }};">
                                                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Constituent Items</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Select items to include in this package with their maximum quantities and validity periods</p>
                        
                        <div id="package_items_container">
                            @if($item->packageItems->count() > 0)
                                @foreach($item->packageItems as $index => $packageItem)
                                <div class="package-item-entry border rounded-lg p-4 bg-gray-50 dark:bg-gray-700 mb-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Item</label>
                                            <select name="package_items[{{ $index }}][included_item_id]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                                <option value="">Select Item</option>
                                                @foreach($availableItems as $availableItem)
                                                    <option value="{{ $availableItem->id }}" {{ $packageItem->included_item_id == $availableItem->id ? 'selected' : '' }}>{{ $availableItem->name }} ({{ $availableItem->code }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Max Quantity</label>
                                            <input type="number" name="package_items[{{ $index }}][max_quantity]" min="1" value="{{ $packageItem->max_quantity }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        </div>
                                    </div>
                                    <button type="button" class="mt-2 text-red-600 hover:text-red-800 text-sm remove-package-item" {{ $index == 0 ? 'style="display: none;"' : '' }}>Remove Item</button>
                                </div>
                                @endforeach
                            @else
                                <div class="package-item-entry border rounded-lg p-4 bg-gray-50 dark:bg-gray-700 mb-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Item</label>
                                            <select name="package_items[0][included_item_id]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                                <option value="">Select Item</option>
                                                @foreach($availableItems as $availableItem)
                                                    <option value="{{ $availableItem->id }}">{{ $availableItem->name }} ({{ $availableItem->code }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Max Quantity</label>
                                            <input type="number" name="package_items[0][max_quantity]" min="1" value="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        </div>
                                    </div>
                                    <button type="button" class="mt-2 text-red-600 hover:text-red-800 text-sm remove-package-item" style="display: none;">Remove Item</button>
                                </div>
                            @endif
                        </div>
                        <button type="button" id="add_package_item" class="mt-2 inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-600 dark:text-white dark:border-gray-500 dark:hover:bg-gray-500">
                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Add Package Item
                        </button>
                    </div>

                    <!-- Bulk Items Section -->
                    <div class="mt-8 bulk-items-section" id="bulk_items_section" style="display: {{ $item->type === 'bulk' ? 'block' : 'none' }};">
                                                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Constituent Items</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Select items to include in this bulk with their fixed quantities</p>
                        
                        <div id="bulk_items_container">
                            @if($item->bulkItems->count() > 0)
                                @foreach($item->bulkItems as $index => $bulkItem)
                                <div class="bulk-item-entry border rounded-lg p-4 bg-gray-50 dark:bg-gray-700 mb-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Item</label>
                                            <select name="bulk_items[{{ $index }}][included_item_id]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                                <option value="">Select Item</option>
                                                @foreach($availableItems as $availableItem)
                                                    <option value="{{ $availableItem->id }}" {{ $bulkItem->included_item_id == $availableItem->id ? 'selected' : '' }}>{{ $availableItem->name }} ({{ $availableItem->code }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Fixed Quantity</label>
                                            <input type="number" name="bulk_items[{{ $index }}][fixed_quantity]" min="1" value="{{ $bulkItem->fixed_quantity }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        </div>
                                    </div>
                                    <button type="button" class="mt-2 text-red-600 hover:text-red-800 text-sm remove-bulk-item" {{ $index == 0 ? 'style="display: none;"' : '' }}>Remove Item</button>
                                </div>
                                @endforeach
                            @else
                                <div class="bulk-item-entry border rounded-lg p-4 bg-gray-50 dark:bg-gray-700 mb-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Item</label>
                                            <select name="bulk_items[0][included_item_id]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                                <option value="">Select Item</option>
                                                @foreach($availableItems as $availableItem)
                                                    <option value="{{ $availableItem->id }}">{{ $availableItem->name }} ({{ $availableItem->code }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Fixed Quantity</label>
                                            <input type="number" name="bulk_items[0][fixed_quantity]" min="1" value="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        </div>
                                    </div>
                                    <button type="button" class="mt-2 text-red-600 hover:text-red-800 text-sm remove-bulk-item" style="display: none;">Remove Item</button>
                                </div>
                            @endif
                        </div>
                        <button type="button" id="add_bulk_item" class="mt-2 inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-600 dark:text-white dark:border-gray-500 dark:hover:bg-gray-500">
                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Add Bulk Item
                        </button>
                    </div>

                    <!-- Sale Price -->
                    <div class="mt-8">
                        <label for="default_price" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Sale price <span class="text-red-500">*</span></label>
                        <input type="number" name="default_price" id="default_price" step="0.01" min="0" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required placeholder="0.00" value="{{ old('default_price', $item->default_price) }}">
                    </div>

                    <div class="mt-6">
                        <label for="purchase_price" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Purchase price <span class="text-red-500">*</span></label>
                        <input type="number" name="purchase_price" id="purchase_price" step="0.01" min="0"
                               value="{{ old('purchase_price', $item->purchase_price) }}"
                               placeholder="0.00"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                               required>
                    </div>

                    <!-- VAT Rate -->
                    <div class="mt-6">
                        <label for="vat_rate" class="block text-sm font-medium text-gray-700 dark:text-gray-300">VAT Rate (%)</label>
                        <input type="number" name="vat_rate" id="vat_rate" step="0.01" min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="0.00" value="{{ old('vat_rate', $item->vat_rate ?? 0) }}">
                        <p class="mt-1 text-sm text-gray-500">Enter VAT rate as percentage (e.g., 18.00 for 18%)</p>
                    </div>

                    <!-- Hospital Share -->
                    <div class="mt-6 service-good-only">
                        <label for="hospital_share" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Company/Entity Share (%) <span class="text-red-500">*</span></label>
                        <input type="number" name="hospital_share" id="hospital_share" min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required value="{{ old('hospital_share', $item->hospital_share) }}" placeholder="100">
                        <p class="mt-1 text-sm text-gray-500">If less than 100%, a Destination Account must be selected</p>
                    </div>

                    <!-- Contractor (shown only when hospital share < 100%) -->
                    <div id="contractor_div" class="service-good-only" @if(old('hospital_share', $item->hospital_share) == 100) style="display: none;" @endif>
                        <label for="contractor_account_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Destination Account <span class="text-red-500">*</span></label>
                        <select name="contractor_account_id" id="contractor_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <option value="">Select destination account</option>
                            @foreach($contractors as $contractor)
                                <option value="{{ $contractor->id }}" {{ old('contractor_account_id', $item->contractor_account_id) == $contractor->id ? 'selected' : '' }}>
                                    {{ $contractor->name }} ({{ $contractor->business->name }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Validity Days (for package items) -->
                    <div id="validity_days_div" @if($item->type !== 'package') style="display: none;" @endif>
                        <label for="validity_days" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Validity Period (Days) <span class="text-red-500">*</span></label>
                        <input type="number" name="validity_days" id="validity_days" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="{{ old('validity_days', $item->validity_days) }}" placeholder="30">
                        <p class="mt-1 text-sm text-gray-500">Number of days the package is valid after purchase</p>
                    </div>

                    <!-- Max Qty (for package items) -->
                    <div id="max_qty_div" @if($item->type !== 'package') style="display: none;" @endif>
                        <label for="max_qty" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Maximum Total Quantity <span class="text-red-500">*</span></label>
                        <input type="number" name="max_qty" id="max_qty" min="1" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="{{ old('max_qty', $item->max_qty ?? 1) }}" placeholder="Enter maximum quantity">
                        <p class="mt-1 text-sm text-gray-500">Maximum total combined quantity that can be consumed across all package usages.</p>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <a href="{{ route('items.index') }}" class="mr-4 inline-flex items-center px-4 py-2 bg-gray-300 text-gray-800 text-sm font-semibold rounded-md hover:bg-gray-400 transition duration-150">
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-[#011478] text-white text-sm font-semibold rounded-md hover:bg-[#011478]/90 transition duration-150">
                            Update Item
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const hospitalShare = document.getElementById('hospital_share');
            const contractorDiv = document.getElementById('contractor_div');
            const contractorSelect = document.getElementById('contractor_account_id');
            const businessSelect = document.getElementById('business_id');
            const defaultPricing = document.getElementById('default_pricing');
            const customPricing = document.getElementById('custom_pricing');
            const branchPricingSection = document.getElementById('branch_pricing_section');
            const codeInput = document.getElementById('code');
            const generateCodeBtn = document.getElementById('generate_code_btn');
            const typeSelect = document.getElementById('type');
            const packageItemsSection = document.getElementById('package_items_section');
            const bulkItemsSection = document.getElementById('bulk_items_section');
            const validityDaysDiv = document.getElementById('validity_days_div');
            const maxQtyDiv = document.getElementById('max_qty_div');
            const addPackageItemBtn = document.getElementById('add_package_item');
            const addBulkItemBtn = document.getElementById('add_bulk_item');

            function toggleContractor() {
                if (hospitalShare.value !== '100') {
                    contractorDiv.style.display = 'block';
                    contractorSelect.required = true;
                } else {
                    contractorDiv.style.display = 'none';
                    contractorSelect.required = false;
                    contractorSelect.value = '';
                }
            }

            function toggleBranchPricing() {
                if (customPricing && customPricing.checked) {
                    branchPricingSection.style.display = 'block';
                } else if (defaultPricing && defaultPricing.checked) {
                    branchPricingSection.style.display = 'none';
                    // Clear values when hiding
                    const branchPriceInputs = branchPricingSection.querySelectorAll('input[name*="[price]"]');
                    branchPriceInputs.forEach(input => {
                        input.value = '';
                    });
                }
            }

            function togglePackageAndBulkSections() {
                // Get the selected type from the select, or from the selected option, or from the initial item type
                let selectedType = '';
                if (typeSelect) {
                    selectedType = typeSelect.value || (typeSelect.options[typeSelect.selectedIndex] && typeSelect.options[typeSelect.selectedIndex].value);
                }
                // Fallback to initial item type if select value is empty
                if (!selectedType) {
                    selectedType = '{{ $item->type }}';
                }
                const validityDaysInput = document.getElementById('validity_days');
                const validityDaysDiv = document.getElementById('validity_days_div');
                const hospitalShareDiv = document.getElementById('hospital_share') ? document.getElementById('hospital_share').closest('div') : null;
                const contractorDiv = document.getElementById('contractor_div');
                
                // Get service/good only elements
                const serviceGoodOnlyElements = document.querySelectorAll('.service-good-only');
                const goodOnlyElements = document.querySelectorAll('.good-only');
                
                // Hide both sections initially
                if (packageItemsSection) packageItemsSection.style.display = 'none';
                if (bulkItemsSection) bulkItemsSection.style.display = 'none';
                if (validityDaysDiv) validityDaysDiv.style.display = 'none';
                // Simple logic: hide maxQtyDiv if type is not package
                if (maxQtyDiv && selectedType !== 'package') {
                maxQtyDiv.style.display = 'none';
                }
                
                // Reset validity days requirement
                if (validityDaysInput) {
                    validityDaysInput.required = false;
                }
                
                // Show/hide fields based on type
                if (selectedType === 'package' || selectedType === 'bulk') {
                    // Hide hospital share and contractor fields
                    hospitalShareDiv.style.display = 'none';
                    contractorDiv.style.display = 'none';
                    
                    // Hide service/good specific fields
                    serviceGoodOnlyElements.forEach(element => {
                        element.style.display = 'none';
                        const inputs = element.querySelectorAll('input, select');
                        inputs.forEach(input => {
                            input.required = false;
                        });
                    });
                    goodOnlyElements.forEach(element => {
                        element.style.display = 'none';
                    });

                    const importanceSelect = document.getElementById('importance_category');
                    if (importanceSelect) {
                        importanceSelect.required = false;
                        importanceSelect.value = '';
                    }
                    
                    // Set hospital share to 100 for packages and bulk
                    document.getElementById('hospital_share').value = '100';
                    document.getElementById('hospital_share').required = false;
                } else {
                    // Show hospital share for services and goods
                    hospitalShareDiv.style.display = 'block';
                    document.getElementById('hospital_share').required = true;
                    
                    // Show service/good specific fields
                    serviceGoodOnlyElements.forEach(element => {
                        element.style.display = 'block';
                        const requiredInputs = element.querySelectorAll('input[data-required="true"], select[data-required="true"]');
                        requiredInputs.forEach(input => {
                            input.required = true;
                        });
                    });

                    goodOnlyElements.forEach(element => {
                        element.style.display = selectedType === 'good' ? 'block' : 'none';
                    });

                    const importanceSelect = document.getElementById('importance_category');
                    if (importanceSelect) {
                        importanceSelect.required = selectedType === 'good';
                        if (selectedType !== 'good') {
                            importanceSelect.value = '';
                        }
                    }

                    // Re-trigger contractor toggle
                    toggleContractor();
                }
                
                // Show appropriate section based on type
                if (selectedType === 'package') {
                    if (packageItemsSection) packageItemsSection.style.display = 'block';
                    if (validityDaysDiv) validityDaysDiv.style.display = 'block';
                    if (maxQtyDiv) maxQtyDiv.style.display = 'block';
                    // Make validity days required for packages
                    if (validityDaysInput) {
                        validityDaysInput.required = true;
                    }
                } else if (selectedType === 'bulk') {
                    if (bulkItemsSection) bulkItemsSection.style.display = 'block';
                }
            }

            function updateFilteredData() {
                if (!businessSelect) return;
                
                const businessId = businessSelect.value;
                if (!businessId) return;

                fetch(`{{ route('items.filtered-data') }}?business_id=${businessId}`)
                    .then(response => response.json())
                    .then(data => {
                        // Update groups
                        updateSelect('group_id', data.groups);
                        updateSelect('subgroup_id', data.groups);
                        
                        // Update departments
                        updateSelect('department_id', data.departments);
                        
                        // Update item units
                        updateSelect('uom_id', data.itemUnits);
                        updateImportanceCategories(data.importanceCategories || []);
                        
                        // Update service points - show all service points grouped by branch
                        updateServicePointsByBranch(data.servicePoints);
                        
                        // Update contractors
                        updateSelect('contractor_account_id', data.contractors);
                    })
                    .catch(error => {
                        console.error('Error fetching filtered data:', error);
                    });
            }

            function updateSelect(selectId, data) {
                const select = document.getElementById(selectId);
                if (!select) return;
                
                // Store current value
                const currentValue = select.value;
                
                // Clear existing options except the first one
                const firstOption = select.querySelector('option');
                select.innerHTML = '';
                if (firstOption) {
                    select.appendChild(firstOption);
                }
                
                // Add data options
                data.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.id;
                    option.textContent = item.name;
                    select.appendChild(option);
                });

                // Restore value if it still exists in new options
                if (currentValue && data.some(item => item.id == currentValue)) {
                    select.value = currentValue;
                } else {
                    select.value = '';
                }
            }

            function updateImportanceCategories(categories) {
                const select = document.getElementById('importance_category');
                if (!select) return;

                const currentValue = select.value;
                select.innerHTML = '<option value="" disabled selected>Select category</option>';

                categories.forEach(category => {
                    const option = document.createElement('option');
                    option.value = category.slug;
                    option.textContent = category.name;
                    select.appendChild(option);
                });

                if (currentValue && categories.some(category => category.slug === currentValue)) {
                    select.value = currentValue;
                } else {
                    select.value = '';
                }
            }

            function updateServicePointsByBranch(servicePointsByBranch) {
                const branchServicePointsSection = document.querySelector('.md\\:col-span-2');
                if (!branchServicePointsSection) return;

                // Find the branch service points section
                const servicePointsSection = branchServicePointsSection.querySelector('.grid');
                if (!servicePointsSection) return;

                // Clear existing content
                servicePointsSection.innerHTML = '';

                // Create new content for each branch
                Object.keys(servicePointsByBranch).forEach(branchId => {
                    const servicePoints = servicePointsByBranch[branchId];
                    if (servicePoints.length === 0) return;

                    const branch = servicePoints[0].branch;
                    const branchDiv = document.createElement('div');
                    branchDiv.className = 'border rounded-lg p-4 bg-gray-50 dark:bg-gray-700';
                    
                    let branchHtml = `
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            ${branch.name}
                        </label>
                        <div class="space-y-2">
                            <select name="branch_service_points[${branchId}]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="">Select service point</option>
                    `;

                    servicePoints.forEach(servicePoint => {
                        branchHtml += `
                            <option value="${servicePoint.id}">${servicePoint.name}</option>
                        `;
                    });

                    branchHtml += `
                            </select>
                        </div>
                    `;
                    branchDiv.innerHTML = branchHtml;
                    servicePointsSection.appendChild(branchDiv);
                });
            }

            // Event listeners
            hospitalShare.addEventListener('input', toggleContractor);
            if (businessSelect) {
                businessSelect.addEventListener('change', updateFilteredData);
            }
            if (defaultPricing) {
                defaultPricing.addEventListener('change', toggleBranchPricing);
            }
            if (customPricing) {
                customPricing.addEventListener('change', toggleBranchPricing);
            }
            if (typeSelect) {
                typeSelect.addEventListener('change', togglePackageAndBulkSections);
            }

            // Code generation functionality
            if (generateCodeBtn) {
                generateCodeBtn.addEventListener('click', function() {
                    fetch('{{ route("items.generate-code") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.code) {
                            codeInput.value = data.code;
                        }
                    })
                    .catch(error => {
                        console.error('Error generating code:', error);
                    });
                });
            }

            // Package items functionality
            if (addPackageItemBtn) {
                addPackageItemBtn.addEventListener('click', function() {
                    const container = document.getElementById('package_items_container');
                    const itemCount = container.children.length;
                    
                    const newItem = document.createElement('div');
                    newItem.className = 'package-item-entry border rounded-lg p-4 bg-gray-50 dark:bg-gray-700 mb-4';
                    newItem.innerHTML = `
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Item</label>
                                <select name="package_items[${itemCount}][included_item_id]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    <option value="">Select Item</option>
                                    @foreach($availableItems as $availableItem)
                                        <option value="{{ $availableItem->id }}">{{ $availableItem->name }} ({{ $availableItem->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Max Quantity</label>
                                <input type="number" name="package_items[${itemCount}][max_quantity]" min="1" value="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                        </div>
                        <button type="button" class="mt-2 text-red-600 hover:text-red-800 text-sm remove-package-item">Remove Item</button>
                    `;
                    
                    container.appendChild(newItem);
                    
                    // Show remove buttons for all items except the first one
                    const removeButtons = container.querySelectorAll('.remove-package-item');
                    removeButtons.forEach((btn, index) => {
                        btn.style.display = index === 0 ? 'none' : 'block';
                    });
                });
            }

            // Bulk items functionality
            if (addBulkItemBtn) {
                addBulkItemBtn.addEventListener('click', function() {
                    const container = document.getElementById('bulk_items_container');
                    const itemCount = container.children.length;
                    
                    const newItem = document.createElement('div');
                    newItem.className = 'bulk-item-entry border rounded-lg p-4 bg-gray-50 dark:bg-gray-700 mb-4';
                    newItem.innerHTML = `
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Item</label>
                                <select name="bulk_items[${itemCount}][included_item_id]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    <option value="">Select Item</option>
                                    @foreach($availableItems as $availableItem)
                                        <option value="{{ $availableItem->id }}">{{ $availableItem->name }} ({{ $availableItem->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Fixed Quantity</label>
                                <input type="number" name="bulk_items[${itemCount}][fixed_quantity]" min="1" value="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                        </div>
                        <button type="button" class="mt-2 text-red-600 hover:text-red-800 text-sm remove-bulk-item">Remove Item</button>
                    `;
                    
                    container.appendChild(newItem);
                    
                    // Show remove buttons for all items except the first one
                    const removeButtons = container.querySelectorAll('.remove-bulk-item');
                    removeButtons.forEach((btn, index) => {
                        btn.style.display = index === 0 ? 'none' : 'block';
                    });
                });
            }

            // Remove item functionality
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-package-item')) {
                    const container = document.getElementById('package_items_container');
                    e.target.closest('.package-item-entry').remove();
                    
                    // Re-index remaining items
                    const items = container.querySelectorAll('.package-item-entry');
                    items.forEach((item, index) => {
                        const select = item.querySelector('select[name*="[included_item_id]"]');
                        const input = item.querySelector('input[name*="[max_quantity]"]');
                        const removeBtn = item.querySelector('.remove-package-item');
                        
                        select.name = `package_items[${index}][included_item_id]`;
                        input.name = `package_items[${index}][max_quantity]`;
                        
                        // Show/hide remove button
                        removeBtn.style.display = index === 0 ? 'none' : 'block';
                    });
                }
                
                if (e.target.classList.contains('remove-bulk-item')) {
                    const container = document.getElementById('bulk_items_container');
                    e.target.closest('.bulk-item-entry').remove();
                    
                    // Re-index remaining items
                    const items = container.querySelectorAll('.bulk-item-entry');
                    items.forEach((item, index) => {
                        const select = item.querySelector('select[name*="[included_item_id]"]');
                        const input = item.querySelector('input[name*="[fixed_quantity]"]');
                        const removeBtn = item.querySelector('.remove-bulk-item');
                        
                        select.name = `bulk_items[${index}][included_item_id]`;
                        input.name = `bulk_items[${index}][fixed_quantity]`;
                        
                        // Show/hide remove button
                        removeBtn.style.display = index === 0 ? 'none' : 'block';
                    });
                }
            });

            // Initialize on page load
            toggleContractor();
            toggleBranchPricing();
            // Ensure maxQtyDiv is visible if item type is package (before running toggle function)
            const initialItemType = '{{ $item->type }}';
            if (initialItemType === 'package' && maxQtyDiv) {
                maxQtyDiv.style.display = 'block';
            }
            togglePackageAndBulkSections();
        });
    </script>
</x-app-layout>
