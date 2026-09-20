<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex justify-between items-center">
                        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                            Package Tracking Dashboard
                        </h2>
                        <div class="flex space-x-3">
                            <a href="{{ route('package-tracking.index') }}" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">
                                View All Packages
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Packages -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Total Packages</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $totalPackages }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Packages -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Active Packages</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $activePackages }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Expired Packages -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-8 w-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Expired Packages</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $expiredPackages }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fully Used Packages -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-8 w-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Fully Used</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $fullyUsedPackages }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Recent Packages -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Packages</h3>
                        @if($recentPackages->count() > 0)
                            <div class="space-y-4">
                                @foreach($recentPackages as $package)
                                    <div class="border rounded-lg p-4">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <p class="font-medium text-gray-900">{{ $package->client->name }}</p>
                                                <p class="text-sm text-gray-600">{{ $package->packageItem->name }}</p>
                                                <p class="text-xs text-blue-600 font-mono">{{ $package->tracking_number ?? 'PKG-' . $package->id . '-' . $package->created_at->inOperationalTimezone()->format('YmdHis') }}</p>
                                                <p class="text-xs text-gray-500">{{ $package->remaining_quantity }}/{{ $package->total_quantity }} remaining • {{ $package->packageItem->packageItems->count() }} items</p>
                                            </div>
                                            <span class="px-2 py-1 text-xs rounded-full {{ $package->status === 'active' ? 'bg-green-100 text-green-800' : ($package->status === 'expired' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                                {{ ucfirst(str_replace('_', ' ', $package->status)) }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-gray-500 text-center py-4">No recent packages found.</p>
                        @endif
                    </div>
                </div>

                <!-- Expiring Soon -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Expiring Soon (30 days)</h3>
                        @if($expiringSoon->count() > 0)
                            <div class="space-y-4">
                                @foreach($expiringSoon as $package)
                                    <div class="border rounded-lg p-4">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <p class="font-medium text-gray-900">{{ $package->client->name }}</p>
                                                <p class="text-sm text-gray-600">{{ $package->packageItem->name }}</p>
                                                <p class="text-xs text-blue-600 font-mono">{{ $package->tracking_number ?? 'PKG-' . $package->id . '-' . $package->created_at->inOperationalTimezone()->format('YmdHis') }}</p>
                                                <p class="text-xs text-red-600">Expires: {{ $package->valid_until->format('M d, Y') }} • {{ $package->packageItem->packageItems->count() }} items</p>
                                            </div>
                                            <span class="px-2 py-1 text-xs rounded-full bg-orange-100 text-orange-800">
                                                {{ $package->valid_until->diffForHumans() }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-gray-500 text-center py-4">No packages expiring soon.</p>
                        @endif
                    </div>
                </div>

                <!-- Low Quantity Packages -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg lg:col-span-2">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Low Quantity Packages (< 25% remaining)</h3>
                        @if($lowQuantity->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Package</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tracking #</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Max Qty</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Balance</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usage %</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($lowQuantity as $package)
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900">{{ $package->client->name }}</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900">{{ $package->packageItem->name }}</div>
                                                    <div class="text-xs text-gray-500">{{ $package->packageItem->packageItems->count() }} items</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-blue-600 font-mono">{{ $package->tracking_number ?? 'PKG-' . $package->id . '-' . $package->created_at->inOperationalTimezone()->format('YmdHis') }}</div>
                                                    <div class="text-xs text-gray-500">Package ID</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900">{{ $package->includedItem->name }}</div>
                                                    <div class="text-xs text-gray-500">
                                                        @if($package->packageItem->packageItems->count() > 1)
                                                            +{{ $package->packageItem->packageItems->count() - 1 }} more
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900">{{ $package->total_quantity }}</div>
                                                    <div class="text-xs text-gray-500">Total available</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-green-600">{{ $package->remaining_quantity }}</div>
                                                    <div class="text-xs text-gray-500">Remaining</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900">{{ number_format($package->usage_percentage, 1) }}%</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <a href="{{ route('package-tracking.show', $package) }}" class="text-blue-600 hover:text-blue-900">View</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-gray-500 text-center py-4">No packages with low quantity.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
