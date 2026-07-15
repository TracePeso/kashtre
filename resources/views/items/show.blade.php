<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800 dark:text-white">Item Details</h2>
                    <div class="flex space-x-2">
                        @if(in_array('Edit Items', (array) (Auth::user()->permissions ?? [])))
                        <a href="{{ route('items.edit', $item) }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700 transition duration-150">
                            Edit Item
                        </a>
                        @endif
                        <a href="{{ route('items.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-300 text-gray-800 text-sm font-semibold rounded-md hover:bg-gray-400 transition duration-150">
                            Back to List
                        </a>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Basic Information -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 border-b pb-2">Basic Information</h3>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Name</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $item->name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Generic Name</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $item->generic_name ?: '—' }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Category</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $item->category ?: '—' }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Code</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $item->code }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Type</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">
                                @if($item->type === 'package')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                        Package
                                    </span>
                                @elseif($item->type === 'bulk')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                        Bulk
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ ucfirst($item->type) }}
                                    </span>
                                @endif
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Business</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $item->business->name ?? 'Not assigned' }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Sale price</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">UGX {{ number_format($item->default_price, 2) }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Purchase price</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">UGX {{ number_format((float) $item->purchase_price, 2) }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">VAT Rate</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $item->vat_rate ?? 0 }}%</p>
                        </div>

                        @if($item->type === 'package')
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Maximum Total Quantity</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $item->max_qty ?? 1 }}</p>
                        </div>
                        @endif

                        @if($item->type !== 'package' && $item->type !== 'bulk')
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Hospital Share</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $item->hospital_share }}%</p>
                        </div>
                        @endif

                        @if($item->other_names)
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Other Names</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $item->other_names }}</p>
                        </div>
                        @endif

                        @if($item->contractor && $item->hospital_share < 100)
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Contractor</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $item->contractor->user->name ?? 'N/A' }}</p>
                        </div>
                        @endif
                    </div>

                    <!-- Categorization (Only for Simple Items) -->
                    @if($item->type !== 'package' && $item->type !== 'bulk')
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 border-b pb-2">Categorization</h3>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Group</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $item->group->name ?? 'Not assigned' }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Subgroup</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $item->subgroup->name ?? 'Not assigned' }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Department</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $item->department->name ?? 'Not assigned' }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Unit of Measure</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $item->itemUnit->name ?? 'Not assigned' }}</p>
                        </div>


                    </div>
                    @endif
                </div>

                <!-- Description -->
                @if($item->description)
                <div class="mt-6">
                    <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 border-b pb-2 mb-4">Description</h3>
                    <p class="text-sm text-gray-900 dark:text-white">{{ $item->description }}</p>
                </div>
                @endif

                <!-- Package Items -->
                @if($item->type === 'package' && $item->packageItems->count() > 0)
                <div class="mt-6">
                    <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 border-b pb-2 mb-4">Constituent Items</h3>
                    <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4 mb-4">
                        <div class="flex items-center mb-3">
                            <svg class="h-5 w-5 text-purple-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-sm font-medium text-purple-800 dark:text-purple-200">Package Validity: {{ $item->validity_days }} days</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($item->packageItems as $packageItem)
                        @if($packageItem->includedItem)
                        <div class="border rounded-lg p-4 bg-purple-50 dark:bg-purple-900/20">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex-1">
                                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $packageItem->includedItem->name ?? 'N/A' }}</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $packageItem->includedItem->code ?? 'N/A' }}</p>
                                </div>
                                <span class="text-sm font-semibold text-purple-600 dark:text-purple-400">Max: {{ $packageItem->max_quantity }}</span>
                            </div>
                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                <p>Type: {{ ucfirst($packageItem->includedItem->type ?? 'N/A') }}</p>
                                <p>Price: UGX {{ number_format($packageItem->includedItem->default_price ?? 0, 2) }}</p>
                            </div>
                        </div>
                        @endif
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Bulk Items -->
                @if($item->type === 'bulk' && $item->bulkItems->count() > 0)
                <div class="mt-6">
                    <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 border-b pb-2 mb-4">Constituent Items</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($item->bulkItems as $bulkItem)
                        @if($bulkItem->includedItem)
                        <div class="border rounded-lg p-4 bg-orange-50 dark:bg-orange-900/20">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex-1">
                                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $bulkItem->includedItem->name ?? 'N/A' }}</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $bulkItem->includedItem->code ?? 'N/A' }}</p>
                                </div>
                                <span class="text-sm font-semibold text-orange-600 dark:text-orange-400">Qty: {{ $bulkItem->fixed_quantity }}</span>
                            </div>
                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                <p>Type: {{ ucfirst($bulkItem->includedItem->type ?? 'N/A') }}</p>
                                <p>Price: UGX {{ number_format($bulkItem->includedItem->default_price ?? 0, 2) }}</p>
                            </div>
                        </div>
                        @endif
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Included In Packages -->
                @if($item->includedInPackages->count() > 0)
                <div class="mt-6">
                    <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 border-b pb-2 mb-4">Included In Packages</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($item->includedInPackages as $packageInclusion)
                        @if($packageInclusion->packageItem)
                        <div class="border rounded-lg p-4 bg-purple-50 dark:bg-purple-900/20">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex-1">
                                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $packageInclusion->packageItem->name ?? 'N/A' }}</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $packageInclusion->packageItem->code ?? 'N/A' }}</p>
                                </div>
                                <span class="text-sm font-semibold text-purple-600 dark:text-purple-400">Max: {{ $packageInclusion->max_quantity }}</span>
                            </div>
                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                <p>Package Type: {{ ucfirst($packageInclusion->packageItem->type ?? 'N/A') }}</p>
                                <p>Package Price: UGX {{ number_format($packageInclusion->packageItem->default_price ?? 0, 2) }}</p>
                            </div>
                        </div>
                        @endif
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Included In Bulks -->
                @if($item->includedInBulks->count() > 0)
                <div class="mt-6">
                    <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 border-b pb-2 mb-4">Included In Bulks</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($item->includedInBulks as $bulkInclusion)
                        @if($bulkInclusion->bulkItem)
                        <div class="border rounded-lg p-4 bg-orange-50 dark:bg-orange-900/20">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex-1">
                                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $bulkInclusion->bulkItem->name ?? 'N/A' }}</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $bulkInclusion->bulkItem->code ?? 'N/A' }}</p>
                                </div>
                                <span class="text-sm font-semibold text-orange-600 dark:text-orange-400">Qty: {{ $bulkInclusion->fixed_quantity }}</span>
                            </div>
                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                <p>Bulk Type: {{ ucfirst($bulkInclusion->bulkItem->type ?? 'N/A') }}</p>
                                <p>Bulk Price: UGX {{ number_format($bulkInclusion->bulkItem->default_price ?? 0, 2) }}</p>
                            </div>
                        </div>
                        @endif
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Branch-Specific Attributes Table -->
                @if($item->branchPrices->count() > 0 || $item->branchServicePoints->count() > 0)
                <div class="mt-6">
                    <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 border-b pb-2 mb-4">Branch-Specific Attributes</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Branch</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Sale price</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Purchase price</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Service Points</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Last Updated</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @if($item->branchPrices->count() > 0)
                                    @foreach($item->branchPrices as $branchPrice)
                                    @if($branchPrice->branch)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $branchPrice->branch->name ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                            <span class="font-semibold">UGX {{ number_format($branchPrice->price, 2) }}</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                            <span class="font-semibold">UGX {{ number_format((float) ($branchPrice->purchase_price ?? $item->purchase_price), 2) }}</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                            @php
                                                $branchServicePoints = $item->branchServicePoints->where('branch_id', $branchPrice->branch_id);
                                            @endphp
                                            @if($branchServicePoints->count() > 0)
                                                <div class="space-y-1">
                                                    @foreach($branchServicePoints as $branchServicePoint)
                                                        <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                            {{ $branchServicePoint->servicePoint->name ?? 'N/A' }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="text-gray-400 text-xs">No service points assigned</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                Active
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $branchPrice->updated_at->format('M d, Y H:i') }}
                                        </td>
                                    </tr>
                                    @endif
                                    @endforeach
                                @else
                                    @php
                                        $filteredBranchServicePoints = $item->branchServicePoints->filter(function($bsp) use ($item) {
                                            return $bsp->business_id == $item->business_id;
                                        });
                                    @endphp
                                    @foreach($filteredBranchServicePoints->groupBy('branch_id') as $branchId => $branchServicePoints)
                                    @if($branchServicePoints->first()->branch)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $branchServicePoints->first()->branch->name ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                            <span class="text-gray-400 text-xs">No branch-specific price</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                            <span class="text-gray-400 text-xs">No branch-specific price</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                            <div class="space-y-1">
                                                @foreach($branchServicePoints as $branchServicePoint)
                                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                        {{ $branchServicePoint->servicePoint->name ?? 'N/A' }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                Active
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $branchServicePoints->first()->updated_at->format('M d, Y H:i') }}
                                        </td>
                                    </tr>
                                    @endif
                                    @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                <!-- Timestamps -->
                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-gray-500 dark:text-gray-400">
                        <div>
                            <label class="block font-medium">Created</label>
                            <p>{{ $item->created_at->format('M d, Y H:i') }}</p>
                        </div>
                        <div>
                            <label class="block font-medium">Last Updated</label>
                            <p>{{ $item->updated_at->format('M d, Y H:i') }}</p>
                        </div>
                        @if($item->deleted_at)
                        <div>
                            <label class="block font-medium text-red-600">Deleted</label>
                            <p class="text-red-600">{{ $item->deleted_at->format('M d, Y H:i') }}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout> 