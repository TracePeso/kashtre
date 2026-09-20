<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex justify-between items-center">
                        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                            Package Tracking Details
                        </h2>
                        <div class="flex space-x-3">
                            <a href="{{ route('package-tracking.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 transition-colors">
                                Back to List
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Package Sales Section -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Package Sales</h3>
                    @if(isset($packageSales) && $packageSales->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($packageSales as $sale)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $sale->date->format('M d, Y H:i') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <span class="font-mono text-blue-600">{{ $sale->invoice_number }}</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">{{ $sale->item_name }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $sale->qty }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                UGX {{ number_format($sale->amount, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 py-1 text-xs rounded-full {{ $sale->status === 'completed' ? 'bg-green-100 text-green-800' : ($sale->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') }}">
                                                    {{ ucfirst($sale->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-gray-500 text-center py-4">No package sales recorded yet.</p>
                    @endif
                </div>
            </div>

            <!-- Package Tracking Details Section -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Package Tracking Details</h3>
                    
                    <!-- Basic Information -->
                    <div class="mb-6">
                        <h4 class="text-md font-medium text-gray-800 mb-3">Basic Information</h4>
                        <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Client</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $packageTracking->client->name }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Invoice Number</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $packageTracking->invoice->invoice_number }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Package Tracking Number</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono text-blue-600">{{ $packageTracking->tracking_number ?? 'PKG-' . $packageTracking->id . '-' . $packageTracking->created_at->inOperationalTimezone()->format('YmdHis') }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Package Item</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $packageTracking->packageItem->name }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Total Items</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $packageTracking->trackingItems->count() }} items included</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Status</dt>
                                <dd class="mt-1">
                                    <span class="px-2 py-1 text-xs rounded-full {{ $packageTracking->status === 'active' ? 'bg-green-100 text-green-800' : ($packageTracking->status === 'expired' ? 'bg-red-100 text-red-800' : ($packageTracking->status === 'fully_used' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800')) }}">
                                        {{ ucfirst(str_replace('_', ' ', $packageTracking->status)) }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Created Date</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $packageTracking->created_at->inOperationalTimezone()->format('M d, Y H:i') }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Quantity Information -->
                    <div class="mb-6">
                        <h4 class="text-md font-medium text-gray-800 mb-3">Quantity Information</h4>
                        <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Total Quantity</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $packageTracking->total_quantity }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Used Quantity</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $packageTracking->used_quantity }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Remaining Quantity</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $packageTracking->remaining_quantity }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Usage Percentage</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ number_format($packageTracking->usage_percentage, 1) }}%</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Package Price</dt>
                                <dd class="mt-1 text-sm text-gray-900">UGX {{ number_format($packageTracking->package_price) }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Item Price</dt>
                                <dd class="mt-1 text-sm text-gray-900">UGX {{ number_format($packageTracking->item_price) }}</dd>
                            </div>
                        </dl>

                        <!-- Progress Bar -->
                        <div class="mt-4">
                            <div class="flex justify-between text-sm text-gray-600 mb-2">
                                <span>Usage Progress</span>
                                <span>{{ number_format($packageTracking->usage_percentage, 1) }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $packageTracking->usage_percentage }}%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Validity Information -->
                    <div class="mb-6">
                        <h4 class="text-md font-medium text-gray-800 mb-3">Validity Information</h4>
                        <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Valid From</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $packageTracking->valid_from->format('M d, Y') }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Valid Until</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $packageTracking->valid_until->format('M d, Y') }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Days Until Expiry</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $packageTracking->days_until_expiry }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Is Expired</dt>
                                <dd class="mt-1">
                                    @if($packageTracking->is_expired)
                                        <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Yes</span>
                                    @else
                                        <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">No</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Package Contents -->
                    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h4 class="text-lg font-semibold text-gray-900">Package Contents</h4>
                        </div>
                        
                        <div class="p-6">
                            @livewire('package-tracking-items-table', ['packageTracking' => $packageTracking])
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <h4 class="text-md font-medium text-gray-800 mb-3">Notes</h4>
                        @if($packageTracking->notes)
                            <p class="text-sm text-gray-900">{{ $packageTracking->notes }}</p>
                        @else
                            <p class="text-sm text-gray-500 italic">No notes available.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
