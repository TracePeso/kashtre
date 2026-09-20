<div class="space-y-6">
    
    <!-- Contractor Welcome Section -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-xl shadow-sm p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold">Welcome, {{ Auth::user()->name }}!</h2>
                <p class="text-blue-100 mt-1">Contractor Dashboard</p>
            </div>
            <div class="text-right">
                <p class="text-blue-100 text-sm">Last Update</p>
                <p class="text-white font-semibold">{{ $lastUpdate }}</p>
            </div>
        </div>
    </div>

    <!-- Contractor Overview Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        
        <!-- Contractor Balance Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">My Balance</h3>
                <span class="text-xs text-gray-400">Available</span>
            </div>
            <div class="flex items-baseline">
                <span class="text-2xl font-bold text-green-600 dark:text-green-400">
                    {{ number_format($balance, 2) }}
                </span>
                <span class="ml-2 text-sm text-gray-500">UGX</span>
            </div>
        </div>

        <!-- Assigned Service Points Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Service Points</h3>
                <span class="text-xs text-gray-400">Assigned</span>
            </div>
            <div class="flex items-baseline">
                <span class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                    {{ $assignedServicePoints->count() }}
                </span>
                <span class="ml-2 text-sm text-gray-500">Points</span>
            </div>
        </div>

        <!-- Today's Clients Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Today's Clients</h3>
                <span class="text-xs text-gray-400">{{ now()->format('M d, Y') }}</span>
            </div>
            <div class="flex items-baseline">
                <span class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                    {{ \App\Models\ServiceDeliveryQueue::where('started_by_user_id', Auth::user()->id)
                        ->whereDate('created_at', today())
                        ->count() }}
                </span>
                <span class="ml-2 text-sm text-gray-500">Clients</span>
            </div>
        </div>

        <!-- Completed Today Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Completed Today</h3>
                <span class="text-xs text-gray-400">{{ now()->format('M d, Y') }}</span>
            </div>
            <div class="flex items-baseline">
                <span class="text-2xl font-bold text-green-600 dark:text-green-400">
                    {{ \App\Models\ServiceDeliveryQueue::where('started_by_user_id', Auth::user()->id)
                        ->where('status', 'completed')
                        ->whereDate('updated_at', today())
                        ->count() }}
                </span>
                <span class="ml-2 text-sm text-gray-500">Completed</span>
            </div>
        </div>

    </div>

    <!-- Quick Actions for Contractors -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
        <div class="flex flex-wrap gap-4">
            <a href="{{ route('service-queues.index') }}" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors flex items-center">
                <i class="fas fa-clipboard-list mr-2"></i>
                View Service Queues
            </a>
            <a href="{{ route('contractor-balance-statement.show', $contractorProfile->id) }}" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors flex items-center">
                <i class="fas fa-history mr-2"></i>
                View Contractor Account Statement
            </a>
        </div>
    </div>

    <!-- Assigned Service Points -->
    @if($assignedServicePoints->count() > 0)
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">My Service Points</h3>
            <a href="{{ route('service-queues.index') }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</a>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($assignedServicePoints as $servicePoint)
            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="font-semibold text-gray-900">{{ $servicePoint->name }}</h4>
                    <span class="text-xs text-gray-500">{{ $servicePoint->branch->name ?? 'N/A' }}</span>
                </div>
                <div class="text-sm text-gray-600 mb-3">
                    {{ $servicePoint->description ?? 'No description available' }}
                </div>
                <div class="flex justify-between text-xs text-gray-500">
                    <span>Pending: {{ $servicePoint->pendingDeliveryQueues->count() }}</span>
                    <span>In Progress: {{ $servicePoint->partiallyDoneDeliveryQueues->count() }}</span>
                </div>
                <a href="{{ route('service-points.show', $servicePoint->id) }}" class="mt-3 inline-block w-full text-center bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded text-sm transition-colors">
                    View Queue
                </a>
            </div>
            @endforeach
        </div>
    </div>
    @else
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <div class="text-center py-8">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No service points assigned</h3>
            <p class="mt-1 text-sm text-gray-500">You haven't been assigned to any service points yet.</p>
        </div>
    </div>
    @endif

    <!-- Recent Activities -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Activities</h3>
        </div>
        
        @php
            $recentActivities = \App\Models\ServiceDeliveryQueue::where('started_by_user_id', Auth::user()->id)
                ->with(['client', 'invoice'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        @endphp
        
        @if($recentActivities->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($recentActivities as $activity)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                                {{ $activity->created_at->inOperationalTimezone()->format('M d, H:i') }}
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                                {{ $activity->client->name ?? 'N/A' }}
                            </td>
                            <td class="px-3 py-2 text-sm text-gray-900">
                                <div class="truncate max-w-xs" title="{{ $activity->item_name }}">
                                    {{ $activity->item_name }}
                                </div>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                    {{ $activity->status === 'completed' ? 'bg-green-100 text-green-800' : 
                                       ($activity->status === 'partially_done' ? 'bg-yellow-100 text-yellow-800' : 
                                       ($activity->status === 'in_progress' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800')) }}">
                                    {{ ucwords(str_replace('_', ' ', $activity->status)) }}
                                </span>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-sm font-medium text-gray-900">
                                UGX {{ number_format($activity->price, 2) }}
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
                <h3 class="mt-2 text-sm font-medium text-gray-900">No recent activities</h3>
                <p class="mt-1 text-sm text-gray-500">You haven't processed any clients yet.</p>
            </div>
        @endif
    </div>

</div>

