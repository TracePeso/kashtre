<x-app-layout>
    <style>
        .tab-button {
            cursor: pointer;
            transition: all 0.2s ease-in-out;
        }
        .tab-button.active {
            border-bottom-color: #3b82f6 !important;
            color: #2563eb !important;
        }
        .tab-button:hover {
            border-bottom-color: #6b7280 !important;
            color: #374151 !important;
        }
        .tab-content {
            display: block;
        }
        .tab-content.hidden {
            display: none;
        }
        .client-card {
            transition: all 0.2s ease-in-out;
        }
        .client-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-weight: 500;
        }
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .status-partially-done {
            background-color: #fed7aa;
            color: #ea580c;
        }
        .status-completed {
            background-color: #dcfce7;
            color: #166534;
        }

    </style>

    @php
        // Ensure $clientsWithItems is always an array
        $clientsWithItems = $clientsWithItems ?? [];
    @endphp

    <div class="py-12 bg-gradient-to-b from-[#011478]/10 to-transparent">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <!-- Page Header -->
            <div class="mb-8 flex justify-between items-center bg-white/50 backdrop-blur-sm p-6 rounded-xl shadow-sm">
                <div class="flex items-center space-x-4">
                    <a href="{{ route('service-queues.index') }}" class="text-blue-600 hover:text-blue-800">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </a>
                    <div>
                        <h2 class="text-3xl font-bold text-[#011478]">{{ $servicePoint->name }}</h2>
                        <p class="text-gray-600 mt-2">{{ $servicePoint->branch->name ?? 'N/A' }} - Client Management</p>
                    </div>
                </div>
                <div class="flex items-center gap-6">
                    <div class="text-center">
                        <div class="text-sm text-gray-600">Total Clients</div>
                        <div class="text-2xl font-bold text-[#011478]">{{ count($clientsWithItems) }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-sm text-gray-600">Total Items</div>
                        <div class="text-2xl font-bold text-[#011478]">
                            {{ $servicePoint->pendingDeliveryQueues->count() + $servicePoint->partiallyDoneDeliveryQueues->count() + $servicePoint->serviceDeliveryQueues->count() }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Service Point Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <!-- Service Point Header -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900">{{ $servicePoint->name }}</h3>
                            <p class="text-sm text-gray-600 mt-1">{{ $servicePoint->branch->name ?? 'N/A' }}</p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Client Management Section -->
                <div class="p-6">
                    <div class="mb-6">
                        <h4 class="text-lg font-semibold text-gray-900 mb-4">Client Service Queue</h4>

                        <!-- Tab Navigation -->
                        <div class="border-b border-gray-200 mb-6">
                            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                                <button onclick="switchTab('pending')"
                                        id="tab-pending"
                                        class="tab-button border-blue-500 text-blue-600 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm active">
                                    <span class="flex items-center">
                                        <span class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></span>
                                        Pending Clients ({{ count(array_filter($clientsWithItems, function($client) { return isset($client['pending']) && count($client['pending']) > 0; })) }})
                                    </span>
                                </button>
                                <button onclick="switchTab('partially-done')"
                                        id="tab-partially-done"
                                        class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                                    <span class="flex items-center">
                                        <span class="w-3 h-3 bg-orange-500 rounded-full mr-2"></span>
                                        In Progress Clients ({{ count(array_filter($clientsWithItems, function($client) { return isset($client['partially_done']) && count($client['partially_done']) > 0; })) }})
                                    </span>
                                </button>
                                <button onclick="switchTab('completed')"
                                        id="tab-completed"
                                        class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                                    <span class="flex items-center">
                                        <span class="w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                                        Completed Items ({{ count(array_filter($clientsWithItems, function($client) { return isset($client['completed']) && count($client['completed']) > 0; })) }})
                                    </span>
                                </button>
                            </nav>
                        </div>

                        <!-- Tab Content -->
                        <div id="tab-content">
                            <!-- Pending Clients Tab -->
                            <div id="content-pending" class="tab-content">
                                @php
                                    $pendingClients = array_filter($clientsWithItems, function($client) {
                                        return isset($client['pending']) && count($client['pending']) > 0;
                                    });
                                @endphp

                                @if(count($pendingClients) > 0)
                                    @php
                                        // Find the next client in queue (earliest queue time)
                                        $nextClientId   = null;
                                        $nextClientName = null;
                                        $earliestTime   = null;
                                        foreach ($pendingClients as $cId => $cData) {
                                            if ($earliestTime === null || $cData['earliest_queue_time'] < $earliestTime) {
                                                $earliestTime   = $cData['earliest_queue_time'];
                                                $nextClientId   = $cId;
                                                $nextClientName = $cData['client']->visit_id ?? $cData['client']->name ?? 'Next Client';
                                            }
                                        }
                                    @endphp

                                    <!-- Call Next bar -->
                                    <div class="flex items-center justify-between mb-4 p-3 bg-blue-50 border border-blue-100 rounded-lg">
                                        <div class="flex items-center gap-2 text-sm text-blue-700">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                            <span>Next in queue: <strong>{{ $nextClientName }}</strong></span>
                                        </div>
                                        <button onclick="callClient('{{ addslashes($nextClientName) }}', {{ $nextClientId }})"
                                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-green-600 hover:bg-green-700 active:scale-95 text-white text-sm font-semibold rounded-lg transition-all shadow-md shadow-green-200">
                                            <!-- Megaphone icon -->
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 011.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 001.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 010 3.46"/>
                                            </svg>
                                            Call Next
                                        </button>
                                    </div>

                                    <div class="overflow-x-auto">
                                        <table class="min-w-full bg-white border border-gray-200 rounded-lg text-sm">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Client ID</th>
                                                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Wait Time</th>
                                                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Status</th>
                                                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                @foreach($pendingClients as $clientId => $clientData)
                                                    @php
                                                        $queueTime = $clientData['earliest_queue_time'];
                                                        $timeInQueue = now()->diffInSeconds($queueTime);
                                                        $hours = floor($timeInQueue / 3600);
                                                        $minutes = floor(($timeInQueue % 3600) / 60);
                                                        $seconds = $timeInQueue % 60;

                                                        // Format time display with seconds (2m:45s format)
                                                        if ($hours > 0) {
                                                            $timeDisplay = sprintf("%dh %dm:%02ds", $hours, $minutes, $seconds);
                                                        } else {
                                                            $timeDisplay = sprintf("%dm:%02ds", $minutes, $seconds);
                                                        }

                                                        // Add status indicator based on wait time
                                                        $waitStatus = '';
                                                        $waitClass = '';
                                                        if ($timeInQueue >= 60) {
                                                            $waitStatus = 'Long Wait';
                                                            $waitClass = 'text-red-600 bg-red-50';
                                                        } elseif ($timeInQueue >= 30) {
                                                            $waitStatus = 'Medium Wait';
                                                            $waitClass = 'text-orange-600 bg-orange-50';
                                                        } else {
                                                            $waitStatus = 'Short Wait';
                                                            $waitClass = 'text-green-600 bg-green-50';
                                                        }
                                                    @endphp
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-4 py-3 text-gray-900 font-medium">
                                                            {{ $clientData['client']->visit_id ?? $clientData['client']->name ?? 'Unknown' }}
                                                        </td>
                                                        <td class="px-4 py-3">
                                                            <div class="text-sm" data-queue-time="{{ $queueTime->toISOString() }}">
                                                                <div class="flex items-center space-x-2 mb-1">
                                                                    <div class="font-semibold text-lg time-display text-gray-800">{{ $timeDisplay }}</div>
                                                                    <span class="wait-status px-2 py-1 rounded-full text-xs font-medium {{ $waitClass }}">
                                                                        {{ $waitStatus }}
                                                                    </span>
                                                                </div>
                                                                <div class="text-xs text-gray-500 flex items-center">
                                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                                    </svg>
                                                                    Joined at {{ $queueTime->format('H:i') }}
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="px-4 py-3">
                                                            <span class="status-badge status-pending">Pending</span>
                                                        </td>
                                                        <td class="px-4 py-3">
                                                            <div class="flex items-center gap-2">
                                                                <!-- Megaphone: call this specific client -->
                                                                <button onclick="callClient('{{ addslashes($clientData['client']->visit_id ?? $clientData['client']->name ?? 'Client') }}', {{ $clientId }})"
                                                                        title="Call {{ $clientData['client']->visit_id ?? $clientData['client']->name ?? 'Client' }}"
                                                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-500 hover:bg-green-600 active:scale-95 text-white text-xs font-semibold rounded-lg transition-all shadow shadow-green-200">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 011.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 001.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 010 3.46"/>
                                                                    </svg>
                                                                    Call
                                                                </button>
                                                                <a href="{{ route('service-points.client-details', [$servicePoint, $clientId]) }}"
                                                                   onclick="event.preventDefault(); announceAndNavigate(this.href, '{{ addslashes($clientData['client']->visit_id ?? $clientData['client']->name ?? 'Client') }}', {{ $clientId }})"
                                                                   class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded transition-colors text-sm inline-block">
                                                                    View Details
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-8 text-center">
                                        <div class="text-gray-500">No pending clients</div>
                                    </div>
                                @endif
                            </div>

                            <!-- In Progress Clients Tab -->
                            <div id="content-partially-done" class="tab-content hidden">
                                @php
                                    $inProgressClients = array_filter($clientsWithItems, function($client) {
                                        return isset($client['partially_done']) && count($client['partially_done']) > 0;
                                    });
                                @endphp

                                @if(count($inProgressClients) > 0)
                                    @php
                                        $firstInProgress = reset($inProgressClients);
                                        $firstInProgressId = key($inProgressClients);
                                        $firstInProgressName = $firstInProgress['client']->visit_id ?? $firstInProgress['client']->name ?? 'Client';
                                    @endphp
                                    <!-- Call Next bar -->
                                    <div class="flex items-center justify-between mb-4 p-3 bg-blue-50 border border-blue-100 rounded-lg">
                                        <div class="flex items-center gap-2 text-sm text-blue-700">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                            <span>Next in queue: <strong>{{ $firstInProgressName }}</strong></span>
                                        </div>
                                        <button onclick="callClient('{{ addslashes($firstInProgressName) }}', {{ $firstInProgressId }})"
                                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-green-600 hover:bg-green-700 active:scale-95 text-white text-sm font-semibold rounded-lg transition-all shadow-md shadow-green-200">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 011.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 001.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 010 3.46"/>
                                            </svg>
                                            Call Next
                                        </button>
                                    </div>

                                    <div class="overflow-x-auto">
                                        <table class="min-w-full bg-white border border-gray-200 rounded-lg text-sm">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Client ID</th>
                                                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Wait Time</th>
                                                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Status</th>
                                                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                @foreach($inProgressClients as $clientId => $clientData)
                                                    @php
                                                        $queueTime = $clientData['earliest_queue_time'];
                                                        $timeInQueue = now()->diffInSeconds($queueTime);
                                                        $hours = floor($timeInQueue / 3600);
                                                        $minutes = floor(($timeInQueue % 3600) / 60);
                                                        $seconds = $timeInQueue % 60;

                                                        // Format time display with seconds (2m:45s format)
                                                        if ($hours > 0) {
                                                            $timeDisplay = sprintf("%dh %dm:%02ds", $hours, $minutes, $seconds);
                                                        } else {
                                                            $timeDisplay = sprintf("%dm:%02ds", $minutes, $seconds);
                                                        }

                                                        // Add status indicator based on wait time
                                                        $waitStatus = '';
                                                        $waitClass = '';
                                                        if ($timeInQueue >= 60) {
                                                            $waitStatus = 'Long Wait';
                                                            $waitClass = 'text-red-600 bg-red-50';
                                                        } elseif ($timeInQueue >= 30) {
                                                            $waitStatus = 'Medium Wait';
                                                            $waitClass = 'text-orange-600 bg-orange-50';
                                                        } else {
                                                            $waitStatus = 'Short Wait';
                                                            $waitClass = 'text-green-600 bg-green-50';
                                                        }
                                                    @endphp
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-4 py-3 text-gray-900 font-medium">
                                                            {{ $clientData['client']->visit_id ?? $clientData['client']->name ?? 'Unknown' }}
                                                        </td>
                                                        <td class="px-4 py-3">
                                                            <div class="text-sm" data-queue-time="{{ $queueTime->toISOString() }}">
                                                                <div class="flex items-center space-x-2 mb-1">
                                                                    <div class="font-semibold text-lg time-display text-gray-800">{{ $timeDisplay }}</div>
                                                                    <span class="wait-status px-2 py-1 rounded-full text-xs font-medium {{ $waitClass }}">
                                                                        {{ $waitStatus }}
                                                                    </span>
                                                                </div>
                                                                <div class="text-xs text-gray-500 flex items-center">
                                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                                    </svg>
                                                                    Joined at {{ $queueTime->format('H:i') }}
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="px-4 py-3">
                                                            <span class="status-badge status-partially-done">In Progress</span>
                                                        </td>
                                                        <td class="px-4 py-3">
                                                            <div class="flex items-center gap-2">
                                                                <button onclick="callClient('{{ addslashes($clientData['client']->visit_id ?? $clientData['client']->name ?? 'Client') }}', {{ $clientId }})"
                                                                        title="Call {{ $clientData['client']->visit_id ?? $clientData['client']->name ?? 'Client' }}"
                                                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-500 hover:bg-green-600 active:scale-95 text-white text-xs font-semibold rounded-lg transition-all shadow shadow-green-200">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 011.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 001.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 010 3.46"/>
                                                                    </svg>
                                                                    Call
                                                                </button>
                                                                <a href="{{ route('service-points.client-details', [$servicePoint, $clientId]) }}"
                                                                   onclick="event.preventDefault(); announceAndNavigate(this.href, '{{ addslashes($clientData['client']->visit_id ?? $clientData['client']->name ?? 'Client') }}', {{ $clientId }})"
                                                                   class="bg-orange-500 hover:bg-orange-600 text-white px-3 py-1 rounded transition-colors text-sm inline-block">
                                                                    View Details
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-8 text-center">
                                        <div class="text-gray-500">No clients in progress</div>
                                    </div>
                                @endif
                            </div>

                            <!-- Completed Items Tab -->
                            <div id="content-completed" class="tab-content hidden">
                                @php
                                    $completedClients = array_filter($clientsWithItems, function($client) {
                                        return count($client['completed']) > 0;
                                    });
                                @endphp

                                @if(count($completedClients) > 0)
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full bg-white border border-gray-200 rounded-lg text-sm">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Client ID</th>
                                                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Wait Time</th>
                                                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Status</th>
                                                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                @foreach($completedClients as $clientId => $clientData)
                                                    @php
                                                        $queueTime = $clientData['earliest_queue_time'];
                                                        $timeInQueue = now()->diffInSeconds($queueTime);
                                                        $hours = floor($timeInQueue / 3600);
                                                        $minutes = floor(($timeInQueue % 3600) / 60);
                                                        $seconds = $timeInQueue % 60;

                                                        // Format time display with seconds (2m:45s format)
                                                        if ($hours > 0) {
                                                            $timeDisplay = sprintf("%dh %dm:%02ds", $hours, $minutes, $seconds);
                                                        } else {
                                                            $timeDisplay = sprintf("%dm:%02ds", $minutes, $seconds);
                                                        }

                                                        // Add status indicator based on wait time
                                                        $waitStatus = '';
                                                        $waitClass = '';
                                                        if ($timeInQueue >= 60) {
                                                            $waitStatus = 'Long Wait';
                                                            $waitClass = 'text-red-600 bg-red-50';
                                                        } elseif ($timeInQueue >= 30) {
                                                            $waitStatus = 'Medium Wait';
                                                            $waitClass = 'text-orange-600 bg-orange-50';
                                                        } else {
                                                            $waitStatus = 'Short Wait';
                                                            $waitClass = 'text-green-600 bg-green-50';
                                                        }
                                                    @endphp
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-4 py-3 text-gray-900 font-medium">
                                                            {{ $clientData['client']->visit_id ?? $clientData['client']->name ?? 'Unknown' }}
                                                        </td>
                                                        <td class="px-4 py-3">
                                                            <div class="text-sm" data-queue-time="{{ $queueTime->toISOString() }}">
                                                                <div class="flex items-center space-x-2 mb-1">
                                                                    <div class="font-semibold text-lg time-display text-gray-800">{{ $timeDisplay }}</div>
                                                                    <span class="wait-status px-2 py-1 rounded-full text-xs font-medium {{ $waitClass }}">
                                                                        {{ $waitStatus }}
                                                                    </span>
                                                                </div>
                                                                <div class="text-xs text-gray-500 flex items-center">
                                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                                    </svg>
                                                                    Joined at {{ $queueTime->format('H:i') }}
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="px-4 py-3">
                                                            <span class="status-badge status-completed">Completed</span>
                                                        </td>
                                                        <td class="px-4 py-3">
                                                            <a href="{{ route('service-points.client-details', [$servicePoint, $clientId]) }}"
                                                               class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded transition-colors text-sm inline-block">
                                                                View Details
                                                            </a>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-8 text-center">
                                        <div class="text-gray-500">No completed items</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <script>
        function switchTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('#tab-content .tab-content');
            tabContents.forEach(content => {
                content.classList.add('hidden');
            });

            // Remove active class from all tab buttons
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => {
                button.classList.remove('active', 'border-blue-500', 'text-blue-600');
                button.classList.add('border-transparent', 'text-gray-500');
            });

            // Show the selected tab content
            const selectedContent = document.getElementById(`content-${tabName}`);
            if (selectedContent) {
                selectedContent.classList.remove('hidden');
            }

            // Add active class to the selected tab button
            const selectedButton = document.getElementById(`tab-${tabName}`);
            if (selectedButton) {
                selectedButton.classList.add('active', 'border-blue-500', 'text-blue-600');
                selectedButton.classList.remove('border-transparent', 'text-gray-500');
            }
        }



        function moveToPartiallyDone(itemId) {
            // Show confirmation dialog with Sweet Alert
            Swal.fire({
                title: 'Start Service?',
                text: 'This will process money transfers and move the item to in progress status.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#f97316',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, start it!',
                cancelButtonText: 'Cancel',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch(`/service-delivery-queues/${itemId}/move-to-partially-done`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Content-Type': 'application/json',
                        },
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || 'An error occurred');
                        }
                        return data;
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Service Started!',
                        text: result.value.message,
                        icon: 'success',
                        confirmButtonColor: '#10b981'
                    }).then(() => {
                        // Reload the page to show updated status
                        window.location.reload();
                    });
                }
            }).catch((error) => {
                Swal.fire({
                    title: 'Error!',
                    text: error.message,
                    icon: 'error',
                    confirmButtonColor: '#ef4444'
                });
            });
        }

        function moveToCompleted(itemId) {
            // Show confirmation dialog with Sweet Alert
            Swal.fire({
                title: 'Complete Service?',
                text: 'This will mark the item as completed and move it to the completed list.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, complete it!',
                cancelButtonText: 'Cancel',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch(`/service-delivery-queues/${itemId}/move-to-completed`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Content-Type': 'application/json',
                        },
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || 'An error occurred');
                        }
                        return data;
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Service Completed!',
                        text: result.value.message,
                        icon: 'success',
                        confirmButtonColor: '#10b981'
                    }).then(() => {
                        // Reload the page to show updated status
                        window.location.reload();
                    });
                }
            }).catch((error) => {
                Swal.fire({
                    title: 'Error!',
                    text: error.message,
                    icon: 'error',
                    confirmButtonColor: '#ef4444'
                });
            });
        }

        // Update queue times every second for precise timing
        function updateQueueTimes() {
            const queueTimeElements = document.querySelectorAll('[data-queue-time]');
            queueTimeElements.forEach(element => {
                const queueTime = new Date(element.dataset.queueTime);
                const now = new Date();
                const diffInSeconds = Math.floor((now - queueTime) / 1000);
                const hours = Math.floor(diffInSeconds / 3600);
                const minutes = Math.floor((diffInSeconds % 3600) / 60);
                const seconds = diffInSeconds % 60;

                // Format time display with seconds (2m:45s format)
                let timeDisplay;
                if (hours > 0) {
                    timeDisplay = `${hours}h ${minutes}m:${seconds.toString().padStart(2, '0')}s`;
                } else {
                    timeDisplay = `${minutes}m:${seconds.toString().padStart(2, '0')}s`;
                }

                // Update wait status (convert seconds to minutes for comparison)
                const diffInMinutes = Math.floor(diffInSeconds / 60);
                let waitStatus, waitClass;
                if (diffInMinutes >= 60) {
                    waitStatus = 'Long Wait';
                    waitClass = 'text-red-600 bg-red-50';
                } else if (diffInMinutes >= 30) {
                    waitStatus = 'Medium Wait';
                    waitClass = 'text-orange-600 bg-orange-50';
                } else {
                    waitStatus = 'Short Wait';
                    waitClass = 'text-green-600 bg-green-50';
                }

                // Update time display
                const timeDisplayElement = element.querySelector('.time-display');
                if (timeDisplayElement) {
                    timeDisplayElement.textContent = timeDisplay;
                }

                // Update wait status badge
                const waitStatusElement = element.querySelector('.wait-status');
                if (waitStatusElement) {
                    waitStatusElement.textContent = waitStatus;
                    waitStatusElement.className = `px-2 py-1 rounded-full text-xs font-medium ${waitClass}`;
                }
            });
        }

        /**
         * fireEmergency — instantly broadcast without confirmation dialog (used by F9).
         */
        function fireEmergency() {
            const presetMessage = '{{ addslashes($defaultEmergencyMessage ?? 'Emergency at ' . $servicePoint->name) }}';
            fetch('{{ route('emergency.trigger', $servicePoint) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ message: presetMessage })
            }).then(r => r.json()).then(data => {
                if (data.success) emergencyActive = true;
            }).catch(() => {});
        }

        /**
         * triggerEmergency — broadcast an emergency alert to all display boards.
         */
        function triggerEmergency() {
            const presetMessage = '{{ addslashes($defaultEmergencyMessage ?? 'Emergency at ' . $servicePoint->name) }}';

            Swal.fire({
                title: 'Trigger Emergency Alert?',
                html: `<p class="text-sm text-gray-600">The following message will be broadcast to all display boards and speakers:</p>
                       <p class="mt-3 font-semibold text-red-700 text-lg">"${presetMessage}"</p>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, broadcast now',
                cancelButtonText: 'Cancel',
            }).then((result) => {
                if (!result.isConfirmed) return;

                fetch('{{ route('emergency.trigger', $servicePoint) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ message: presetMessage })
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        document.getElementById('all-clear-btn').classList.remove('hidden');
                        Swal.fire({
                            title: 'Alert Broadcast!',
                            text: data.message,
                            icon: 'warning',
                            confirmButtonColor: '#dc2626',
                            timer: 4000,
                            timerProgressBar: true,
                        });
                    } else {
                        Swal.fire('Error', data.error || 'Failed to send emergency alert.', 'error');
                    }
                }).catch(() => {
                    Swal.fire('Error', 'Failed to send emergency alert. Check calling module.', 'error');
                });
            });
        }

        /**
         * resolveEmergency — clear the active emergency alert.
         */
        function resolveEmergency() {
            fetch('{{ route('emergency.resolve', $servicePoint) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({})
            }).then(r => r.json()).then(data => {
                if (data.success) emergencyActive = false;
            }).catch(() => {});
        }

        function announceAndNavigate(url, clientName, clientId) {
            // Trigger a "serving" announcement for the display board
            fetch('{{ route('calling.announce') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    client_id: clientId,
                    client_name: clientName,
                    service_point_id: {{ $servicePoint->id }},
                    type: 'serving'
                })
            }).then(() => {
                window.location.href = url;
            }).catch(() => {
                window.location.href = url;
            });
        }

        /**
         * callClient — announce a client at this service point and record the call log.
         * Audio is handled by the display board (ElevenLabs), not the browser.
         */
        function callClient(clientName, clientId) {
            // Visual feedback
            Swal.fire({
                title: 'Calling...',
                html: `<div class="text-center">
                            <div class="text-2xl font-bold text-indigo-700 mt-2">${clientName}</div>
                            <p class="text-gray-500 text-sm mt-2">Please proceed to <strong>{{ $servicePoint->name }}</strong></p>
                       </div>`,
                icon: 'info',
                confirmButtonText: 'Done',
                confirmButtonColor: '#4f46e5',
                timer: 8000,
                timerProgressBar: true,
            });

            // Record the call in the caller log
            fetch('{{ route('calling.announce') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    client_id: clientId,
                    client_name: clientName,
                    service_point_id: {{ $servicePoint->id }},
                    type: 'calling'
                })
            });
        }

        // Update queue times immediately and then every second for precise timing
        updateQueueTimes();
        setInterval(updateQueueTimes, 1000);

        // Auto-refresh every 5 minutes (reduced from 30 seconds for better performance)
        setInterval(() => {
            location.reload();
        }, 300000);

    </script>
</x-app-layout>

