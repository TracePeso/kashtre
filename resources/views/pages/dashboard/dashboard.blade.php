<x-app-layout>
    <div class="py-12 bg-gradient-to-b from-[#011478]/10 to-transparent">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <!-- Impersonation Banner -->
            @impersonating
                <div
                    class="bg-yellow-500/80 backdrop-blur-sm text-white p-4 text-center flex justify-between items-center mx-auto max-w-7xl sm:px-6 lg:px-8 rounded-xl shadow-sm mb-4">
                    <span class="font-medium text-lg">
                        You are currently impersonating <strong>{{ auth()->user()->name }}</strong>.
                    </span>
                    <a href="{{ route('impersonate.leave') }}"
                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-xl text-sm font-semibold transition duration-200">
                        Stop Impersonating
                    </a>
                </div>
            @endImpersonating

            @if(session('success'))
                <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-green-800 shadow-sm" role="alert">
                    <p class="text-sm font-medium">{{ session('success') }}</p>
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800 shadow-sm" role="alert">
                    <p class="text-sm font-medium">{{ session('error') }}</p>
                </div>
            @endif

            @if((int) auth()->user()->business_id === 1)
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50/90 p-6 shadow-sm">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div class="flex items-start space-x-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-100">
                                <svg class="h-6 w-6 text-red-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-red-900">Super-admin: reset testing data</h3>
                                <p class="mt-1 text-sm text-red-800/90">
                                    Runs <span class="font-mono text-xs">service-queues:reset --all</span>,
                                    <span class="font-mono text-xs">suspense:clear</span>, and
                                    <span class="font-mono text-xs">reset:account-statements</span> (queues, suspense, all statements including third-party AR &mdash; destructive).
                                </p>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('dashboard.testing-environment-reset') }}" class="shrink-0"
                              onsubmit="return confirm('This will cancel pending queues, clear suspense, and wipe statements / transfers / accounts receivable / third-party payer histories. Only use on a database you are allowed to reset. Continue?');">
                            @csrf
                            <button type="submit"
                                    class="rounded-lg bg-red-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">
                                Reset testing environment
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            <!-- Page Title with User & Branch Info -->
            <div class="mb-8 flex justify-between items-center bg-white/50 backdrop-blur-sm p-6 rounded-xl shadow-sm">
                <div>
                    <h2 class="text-3xl font-bold text-[#011478]">Welcome to {{ $business->name ?? 'N/A' }}</h2>
                    <div class="flex items-center mt-2 space-x-4">
                        <p class="text-gray-600 font-medium">User: {{ Auth::user()->name }}</p>

                        @if ($currentBranch)
                            <span class="text-gray-400">|</span>
                            <p class="text-gray-600 font-medium">
                                Branch: {{ $currentBranch->name }}
                                @if(count($allowedBranches) > 1)
                                    <button onclick="showBranchSelection()" class="ml-2 text-blue-600 hover:text-blue-800 text-sm underline">
                                        (Change)
                                    </button>
                                @endif
                            </p>
                        @endif

                        <span class="text-gray-400">|</span>
                        <p class="text-gray-600 font-medium">{{ now()->format('l, F j, Y') }}</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-sm font-semibold text-[#011478]/80">Current Time</p>
                    <p class="text-lg font-mono">{{ now()->format('H:i:s') }} EAT</p>
                </div>
            </div>

            <!-- Service Points Quick Access - Moved up for prominence -->
            @if(auth()->user()->service_points)
                <div class="mb-6 bg-gradient-to-r from-green-50 to-blue-50 border border-green-200 rounded-xl p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Service Points Dashboard</h3>
                                <p class="text-sm text-gray-600">Manage your service points and client queues</p>
                            </div>
                        </div>
                        <a href="{{ route('service-queues.index') }}" 
                           class="bg-[#011478] hover:bg-[#011478]/90 text-white px-6 py-3 rounded-lg font-semibold transition duration-200 flex items-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                            <span>View Service Points</span>
                        </a>
                    </div>
                </div>
            @endif

            <!-- Automated Test Button -->
            <div class="mb-6 bg-gradient-to-r from-purple-50 to-indigo-50 border border-purple-200 rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">System Testing</h3>
                            <p class="text-sm text-gray-600">Run automated tests for finances, queueing, packages, and bulk items</p>
                        </div>
                    </div>
                    <a href="{{ route('automated-tests.index') }}" 
                       class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg font-semibold transition duration-200 flex items-center space-x-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        <span>Automated Test</span>
                    </a>
                </div>
            </div>

            <!-- Testing Dashboard - Only for Admin Users and Local Environment -->
            @if((Auth::user()->business_id == 1 || $business->shortcode === 'KS1759822163') && Auth::user()->status === 'active' && config('app.env') === 'local' && !app()->environment('production'))
                <div class="mb-6 bg-gradient-to-r from-red-50 to-orange-50 border border-red-200 rounded-xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">⚠️ Testing Dashboard</h3>
                                <p class="text-sm text-gray-600">Clear data for testing purposes (Admin Only)</p>
                                <p class="text-xs text-red-600 font-medium mt-1">⚠️ WARNING: These actions are irreversible and will permanently delete data!</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <!-- Clear Service Queues -->
                        <button onclick="clearData('queues')" 
                                class="bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-lg font-semibold transition duration-200 flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            <span>Clear Queues & Package Tracking</span>
                        </button>

                        <!-- Clear Transactions -->
                        <button onclick="clearData('transactions')" 
                                class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-3 rounded-lg font-semibold transition duration-200 flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            <span>Clear Transactions</span>
                        </button>

                        <!-- Clear Client Balances -->
                        <button onclick="clearData('client-balances')" 
                                class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-3 rounded-lg font-semibold transition duration-200 flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            <span>Clear Client Balances</span>
                        </button>

                        <!-- Clear Temporary Accounts -->
                        <button onclick="clearData('simple-test')" 
                                class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-3 rounded-lg font-semibold transition duration-200 flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>Test Simple Case</span>
                        </button>

                        <!-- Clear Package Tracking -->
                        <button onclick="clearData('package-tracking')" 
                                class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-3 rounded-lg font-semibold transition duration-200 flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                            <span>Clear Package Tracking</span>
                        </button>

                        <!-- Clear Kashtre Balance -->
                        <button onclick="clearData('kashtre-balance')" 
                                class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-3 rounded-lg font-semibold transition duration-200 flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                            <span>Clear Kashtre Balance</span>
                        </button>

                        <!-- Clear Business Balances -->
                        <button onclick="clearData('business-balances')" 
                                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-3 rounded-lg font-semibold transition duration-200 flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                            <span>Clear Business Balances</span>
                        </button>

                        <!-- Clear All Statements -->
                        <button onclick="clearData('statements')" 
                                class="bg-pink-600 hover:bg-pink-700 text-white px-4 py-3 rounded-lg font-semibold transition duration-200 flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span>Clear All Statements</span>
                        </button>

                        <!-- Clear Client Balance Statements -->
                        <button onclick="clearData('client-balance-statements')" 
                                class="bg-cyan-600 hover:bg-cyan-700 text-white px-4 py-3 rounded-lg font-semibold transition duration-200 flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            <span>Clear Client Account Statements</span>
                        </button>

                        <!-- Reset Payment to Pending -->
                        <button onclick="clearData('reset-payment-pending')" 
                                class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-3 rounded-lg font-semibold transition duration-200 flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            <span>Reset Payment to Pending</span>
                        </button>

                        <!-- Check Service Delivery Queues -->
                        <button onclick="clearData('check-queues')" 
                                class="bg-slate-600 hover:bg-slate-700 text-white px-4 py-3 rounded-lg font-semibold transition duration-200 flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                            </svg>
                            <span>Check Service Delivery Queues</span>
                        </button>

                        <!-- Debug Client Balance -->
                        <button onclick="clearData('debug-balance')" 
                                class="bg-amber-600 hover:bg-amber-700 text-white px-4 py-3 rounded-lg font-semibold transition duration-200 flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                            <span>Debug Client Balance</span>
                        </button>

                        <!-- Clear Business ID 3 Data -->
                        <button onclick="clearData('clear-business-3')" 
                                class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-3 rounded-lg font-semibold transition duration-200 flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            <span>Clear Business ID 3</span>
                        </button>

                        <!-- Clear Business Statements -->
                        <button onclick="clearData('clear-business-statements')" 
                                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-3 rounded-lg font-semibold transition duration-200 flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span>Clear Business Statements</span>
                        </button>

                        <!-- Run Comprehensive Tests -->
                        <a href="{{ route('system-tests.runner') }}" 
                           class="bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 text-white px-4 py-3 rounded-lg font-semibold transition duration-200 flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            <span>🧪 Run Comprehensive Tests</span>
                        </a>
                    </div>
                </div>
            @endif

            <!-- Main Content -->
            <div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-sm p-6">
                <!-- Dashboard Content -->
                @livewire('dashboard')
            </div>
        </div>
    </div>

    {{-- Hidden form to submit selected room --}}
    <form method="POST" action="{{ url()->current() }}" id="room-select-form" style="display:none;">
        @csrf
        <input type="hidden" name="room_id" id="selected-room-id" value="">
    </form>

    <script>
        window.userHasRoom = @json(session()->has('room_id'));
        window.rooms = @json($rooms);
        window.allowedBranches = @json($allowedBranches);
        window.currentBranchId = @json($currentBranch ? $currentBranch->id : null);

        document.addEventListener('DOMContentLoaded', function() {
            // If user doesn't have a room and there are rooms available
            if (!window.userHasRoom && window.rooms && window.rooms.length > 0) {
                // If there's only one room, auto-select it
                if (window.rooms.length === 1) {
                    const roomId = window.rooms[0].id;
                    fetch('{{ route('room.select') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: JSON.stringify({
                            room_id: roomId
                        })
                    })
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => {
                        // Auto-select successful, reload page
                        location.reload();
                    })
                    .catch(() => {
                        console.log('Auto-room selection failed, continuing without room');
                    });
                } else {
                    // Multiple rooms available, show selection dialog
                    let optionsHtml = `<option value="" disabled selected>-- Select a Room --</option>` +
                        window.rooms.map(room =>
                            `<option value="${room.id}">${room.name}</option>`
                        ).join('');

                    Swal.fire({
                        title: 'Select Your Room',
                        html: `
                        <select id="swal-room-select" style="
                            width: 100%;
                            padding: 0.5rem 0.75rem;
                            font-size: 1rem;
                            border-radius: 0.375rem;
                            border: 1px solid #CBD5E1; /* Tailwind slate-300 */
                            color: #475569; /* Tailwind slate-600 */
                            background-color: white;
                        ">
                            ${optionsHtml}
                        </select>
                    `,
                        icon: 'info',
                        confirmButtonText: 'Confirm',
                        confirmButtonColor: '#A5B4FC', // Tailwind indigo-300
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        focusConfirm: false,
                        preConfirm: () => {
                            const selectedRoom = Swal.getPopup().querySelector('#swal-room-select').value;
                            if (!selectedRoom) {
                                Swal.showValidationMessage('Please select a room');
                            }
                            return selectedRoom;
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const roomId = result.value;

                            fetch('{{ route('room.select') }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    },
                                    body: JSON.stringify({
                                        room_id: roomId
                                    })
                                })
                                .then(response => {
                                    if (!response.ok) throw new Error('Network response was not ok');
                                    return response.json();
                                })
                                .then(data => {
                                    Swal.fire({
                                        title: 'Success',
                                        text: data.message,
                                        icon: 'success',
                                        timer: 1500,
                                        showConfirmButton: false
                                    }).then(() => {
                                        location.reload();
                                    });
                                })
                                .catch(() => {
                                    Swal.fire('Error', 'Failed to set room. Please try again.', 'error');
                                });
                        }
                    });
                }
            }
            // If no rooms available, continue without room selection
        });

        function showBranchSelection() {
            if (!window.allowedBranches || window.allowedBranches.length === 0) {
                Swal.fire('No Branches Available', 'You do not have access to any branches.', 'info');
                return;
            }

            let optionsHtml = `<option value="" disabled>-- Select a Branch --</option>` +
                window.allowedBranches.map(branch =>
                    `<option value="${branch.id}" ${branch.id == window.currentBranchId ? 'selected' : ''}>${branch.name}</option>`
                ).join('');

            Swal.fire({
                title: 'Select Your Working Branch',
                html: `
                <select id="swal-branch-select" style="
                    width: 100%;
                    padding: 0.5rem 0.75rem;
                    font-size: 1rem;
                    border-radius: 0.375rem;
                    border: 1px solid #CBD5E1; /* Tailwind slate-300 */
                    color: #475569; /* Tailwind slate-600 */
                    background-color: white;
                ">
                    ${optionsHtml}
                </select>
            `,
                icon: 'info',
                confirmButtonText: 'Confirm',
                confirmButtonColor: '#A5B4FC', // Tailwind indigo-300
                allowOutsideClick: true,
                allowEscapeKey: true,
                focusConfirm: false,
                preConfirm: () => {
                    const selectedBranch = Swal.getPopup().querySelector('#swal-branch-select').value;
                    if (!selectedBranch) {
                        Swal.showValidationMessage('Please select a branch');
                    }
                    return selectedBranch;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const branchId = result.value;

                    fetch('{{ route('branch.select') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                branch_id: branchId
                            })
                        })
                        .then(response => {
                            if (!response.ok) throw new Error('Network response was not ok');
                            return response.json();
                        })
                        .then(data => {
                            Swal.fire({
                                title: 'Success',
                                text: data.message,
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        })
                        .catch((error) => {
                            console.error('Error:', error);
                            Swal.fire('Error', 'Failed to set branch. Please try again.', 'error');
                        });
                }
            });
        }

        // Clear data function for testing
        function clearData(type) {
            console.log('DEBUG: clearData called with type:', type, 'length:', type.length);
            const confirmMessages = {
                'queues': 'Are you sure you want to clear ALL service delivery queues and package tracking data? This action cannot be undone.',
                'transactions': 'Are you sure you want to clear ALL transactions? This action cannot be undone.',
                'client-balances': 'Are you sure you want to clear ALL client balances? This action cannot be undone.',
                'temp-accounts': 'Are you sure you want to clear ALL temporary accounts (suspense balances)? This action cannot be undone.',
                'test123': 'Are you sure you want to clear ALL temporary accounts (suspense balances)? This action cannot be undone.',
                'simple-test': 'Are you sure you want to test the simple case?',
                'package-tracking': 'Are you sure you want to clear ALL package tracking data (delivery queues, package suspense accounts, package transactions)? This action cannot be undone.',
                'kashtre-balance': 'Are you sure you want to clear the Kashtre balance? This action cannot be undone.',
                'business-balances': 'Are you sure you want to clear ALL business balances? This action cannot be undone.',
                'statements': 'Are you sure you want to clear ALL statements for all users? This action cannot be undone.',
                'client-balance-statements': 'Are you sure you want to clear ALL client account statements? This action cannot be undone.',
                'reset-payment-pending': 'Are you sure you want to reset the most recent completed payment back to pending? This will allow you to test the payment completion flow.',
                'check-queues': 'This will show you information about service delivery queues without clearing any data.',
                'debug-balance': 'This will debug client balance calculation and show detailed balance history information.',
                'clear-business-3': 'Are you sure you want to clear ALL data for Business ID 3 (queues, transactions, balances, money accounts, package tracking)? This action cannot be undone.',
                'clear-business-statements': 'Are you sure you want to clear ALL business balance statement records? This will remove all business account statement history. This action cannot be undone.'
            };

            Swal.fire({
                title: 'Confirm Clear Data',
                text: confirmMessages[type] || 'Are you sure you want to clear this data?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, clear it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Clearing Data...',
                        text: 'Please wait while we clear the data',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Make API call
                    const requestBody = { type: type };
                    console.log('DEBUG: Sending request body:', requestBody);
                    fetch('{{ route("testing.clear-data") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: JSON.stringify(requestBody)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: data.message,
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                title: 'Error!',
                                text: data.message || 'Failed to clear data',
                                icon: 'error'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            title: 'Error!',
                            text: 'An error occurred while clearing data',
                            icon: 'error'
                        });
                    });
                }
            });
        }
    </script>



</x-app-layout>
