<div class="min-w-fit">
    <!-- Sidebar backdrop (mobile only) -->
    <div class="fixed inset-0 bg-gray-900/30 z-40 lg:hidden lg:z-auto transition-opacity duration-200" :class="sidebarOpen ? 'opacity-100' : 'opacity-0 pointer-events-none'" aria-hidden="true" x-cloak></div>

    <!-- Sidebar -->
    <div id="sidebar" class="flex flex-col absolute z-40 left-0 top-0 lg:static lg:left-auto lg:top-auto lg:translate-x-0 h-[100dvh] overflow-y-scroll lg:overflow-y-auto no-scrollbar w-64 lg:w-20 lg:sidebar-expanded:!w-64 2xl:!w-64 shrink-0 bg-white dark:bg-gray-800 p-4 transition-all duration-200 ease-in-out {{ $variant === 'v2' ? 'border-r border-gray-200 dark:border-gray-700/60' : 'rounded-r-2xl shadow-xs' }}" :class="sidebarOpen ? 'max-lg:translate-x-0' : 'max-lg:-translate-x-64'" @click.outside="sidebarOpen = false" @keydown.escape.window="sidebarOpen = false">

        <!-- Sidebar header -->
        <div class="flex justify-between mb-10 pr-3 sm:px-2">
            <!-- Close button -->
            <button class="lg:hidden text-gray-500 hover:text-gray-400" @click.stop="sidebarOpen = !sidebarOpen" aria-controls="sidebar" :aria-expanded="sidebarOpen">
                <span class="sr-only">Close sidebar</span>
                <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24">
                    <path d="M10.7 18.7l1.4-1.4L7.8 13H20v-2H7.8l4.3-4.3-1.4-1.4L4 12z" />
                </svg>
            </button>

            <!-- Logo and Business Info -->
            <div class="flex flex-col items-center w-full">
                <h1 class="text-[#011478] font-extrabold text-xl mb-1">{{ env('APP_NAME') }}</h1>
                <div class="w-16 h-16 rounded-lg overflow-hidden">
                    @if ($business?->logo_url)
                    <img src="{{ $business->logo_url }}" alt="{{ $business->name }} logo" class="w-full h-full object-contain">
                    @else
                    <img src="{{ asset('images/kashtre_logo.svg') }}" alt="Default Logo" class="w-full h-full object-contain">
                    @endif
                </div>
                <h2 class="text-[#011478] font-bold text-sm mt-2 text-center">
                    {{ $business->name ?? 'N/A' }}
                </h2>
                <p class="text-gray-500 text-xs mt-0.5">
                    A/C: {{ $business->account_number ?? 'N/A' }}
                </p>
                @if(Auth::user()->current_branch)
                <p class="text-gray-500 text-xs mt-0.5">
                    Branch: {{ Auth::user()->current_branch->name }}
                </p>
                @endif
            </div>
        </div>

        <!-- Links -->
        <div class="space-y-8">
            <div>
                <ul class="mt-3 space-y-2" x-data="{ openGroup: '{{ request()->routeIs('inventory.*') ? 'inventory' : '' }}' }">

                    <!-- Dashboard: usually visible to all -->
                    <li>
                        <a href="{{ route('dashboard') }}" class="flex items-center pl-4 pr-3 py-2 rounded-lg bg-blue-100 text-blue-900 font-semibold">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v6H8V5z"></path>
                            </svg>
                            <span class="text-sm font-medium ml-3 lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100 duration-200">Dashboard</span>
                        </a>
                    </li>

                    <!-- Service Points: for users with assigned service points (exclude business_id = 1) -->
                    @if(auth()->user()->service_points && auth()->user()->business_id != 1)
                    <li>
                        <a href="{{ route('service-queues.index') }}" class="flex items-center pl-4 pr-3 py-2 rounded-lg text-gray-700 hover:text-blue-700 hover:bg-blue-50">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                            <span class="text-sm font-medium ml-3 lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100 duration-200">Service Points</span>
                        </a>
                    </li>
                    @endif

                    @if($inventoryModuleEnabled)
                    <li>
                        <button @click="openGroup === 'inventory' ? openGroup = '' : openGroup = 'inventory'"
                                :class="openGroup === 'inventory' ? 'border border-blue-500 text-blue-700 bg-blue-50' : 'text-gray-700 hover:text-blue-700'"
                                class="flex items-center justify-between w-full text-left pl-4 pr-3 py-2 rounded-md">
                            <span class="flex items-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                                <span class="ml-3 text-sm font-medium lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100 duration-200">Inventory</span>
                            </span>
                            <svg class="w-4 h-4 transform transition-transform duration-200 lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100" :class="{ 'rotate-180': openGroup === 'inventory' }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <ul x-show="openGroup === 'inventory'" x-collapse class="mt-1 space-y-1 pl-10">
                            <li>
                                <a href="{{ route('inventory.receive') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5 {{ request()->routeIs('inventory.receive') ? 'text-blue-700 font-medium' : '' }}" @click.stop>
                                    Receive Goods
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventory.monitor') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5 {{ request()->routeIs('inventory.monitor*') ? 'text-blue-700 font-medium' : '' }}" @click.stop>
                                    Monitor Stock
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventory.fulfillment.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5 {{ request()->routeIs('inventory.fulfillment*') ? 'text-blue-700 font-medium' : '' }}" @click.stop>
                                    EndStore
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventory.crash-carts.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5 {{ request()->routeIs('inventory.crash-carts*') ? 'text-blue-700 font-medium' : '' }}" @click.stop>
                                    Crash Carts
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventory.replenishment.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5 {{ request()->routeIs('inventory.replenishment*') ? 'text-blue-700 font-medium' : '' }}" @click.stop>
                                    Internal Replenishment
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventory.approved-pool.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5 {{ request()->routeIs('inventory.approved-pool*') ? 'text-blue-700 font-medium' : '' }}" @click.stop>
                                    Approved Pool
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventory.usage.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5 {{ request()->routeIs('inventory.usage*') ? 'text-blue-700 font-medium' : '' }}" @click.stop>
                                    Record Usage
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventory.stock-counts.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5 {{ request()->routeIs('inventory.stock-counts*') ? 'text-blue-700 font-medium' : '' }}" @click.stop>
                                    Stock Counts
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventory.escrow.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5 {{ request()->routeIs('inventory.escrow*') ? 'text-blue-700 font-medium' : '' }}" @click.stop>
                                    Expired Escrow
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventory.consumption.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5 {{ request()->routeIs('inventory.consumption*') ? 'text-blue-700 font-medium' : '' }}" @click.stop>
                                    Consumption
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventory.orders.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5 {{ request()->routeIs('inventory.orders*') ? 'text-blue-700 font-medium' : '' }}" @click.stop>
                                    Orders
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventory.incoming-rfqs.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5 {{ request()->routeIs('inventory.incoming-rfqs*') ? 'text-blue-700 font-medium' : '' }}" @click.stop>
                                    Incoming RFQs
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventory.supplied-quotations.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5 {{ request()->routeIs('inventory.supplied-quotations*') ? 'text-blue-700 font-medium' : '' }}" @click.stop>
                                    Supplied quotations
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventory.purchase-orders.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5 {{ request()->routeIs('inventory.purchase-orders*') ? 'text-blue-700 font-medium' : '' }}" @click.stop>
                                    LPOs
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventory.transfers.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5 {{ request()->routeIs('inventory.transfers*') ? 'text-blue-700 font-medium' : '' }}" @click.stop>
                                    Transfers
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventory.returns.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5 {{ request()->routeIs('inventory.returns*') ? 'text-blue-700 font-medium' : '' }}" @click.stop>
                                    Returns
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventory.reports.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5 {{ request()->routeIs('inventory.reports*') ? 'text-blue-700 font-medium' : '' }}" @click.stop>
                                    Reports
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventory.settings.edit') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5 {{ request()->routeIs('inventory.settings*') || request()->routeIs('inventory.approvers') ? 'text-blue-700 font-medium' : '' }}" @click.stop>
                                    Settings
                                </a>
                            </li>
                        </ul>
                    </li>
                    @endif


                    @if(Auth::user()->business_id == 1 && in_array('View Sales', $permissions))
                    <li>
                        <a href="{{ route('sales.index') }}" class="flex items-center justify-between w-full text-left pl-4 pr-3 py-2 rounded-md {{ request()->routeIs('sales.index') ? 'border border-blue-500 text-blue-700 bg-blue-50' : 'text-gray-700 hover:text-blue-700' }}">
                            <span class="flex items-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h18M9 8h12M9 13h12M3 18h18M3 8h3M3 13h3"></path>
                                </svg>
                                <span class="ml-3">Sales</span>
                            </span>
                        </a>
                    </li>
                    @endif

                    <!-- Admin Group -->
                    @if(Auth::user()->business_id == 1)
                    <li>
                        <button @click="openGroup === 'admin' ? openGroup = '' : openGroup = 'admin'" :class="openGroup === 'admin' ? 'border border-blue-500 text-blue-700 bg-blue-50' : 'text-gray-700 hover:text-blue-700'" class="flex items-center justify-between w-full text-left pl-4 pr-3 py-2 rounded-md">
                            <span class="flex items-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                                <span class="ml-3">Admin</span>
                            </span>
                            <svg class="w-4 h-4 transform transition-transform duration-200" :class="{ 'rotate-180': openGroup === 'admin' }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <ul x-show="openGroup === 'admin'" x-collapse class="mt-1 space-y-1 pl-10">
                            {{-- @if(in_array('View Admin Users', $permissions)) --}}
                            <li><a href="{{ route('admins.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Admin Users</a></li>
                            {{-- @endif --}}
                            @if(in_array('View Audit Logs', (array) $permissions))
                            <li><a href="{{ route('audit-logs.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Audit Logs</a></li>
                            @endif
                            @if(in_array('Manage System Settings', (array) $permissions))
                            <li><a href="{{ route('admins.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>System Settings</a></li>
                            @endif
                        </ul>
                    </li>
                    @endif

                    <!-- Staff Group -->
                    @if(in_array('View Staff', (array) $permissions))
                    <li>
                        <button @click="openGroup === 'staff' ? openGroup = '' : openGroup = 'staff'" :class="openGroup === 'staff' ? 'border border-blue-500 text-blue-700 bg-blue-50' : 'text-gray-700 hover:text-blue-700'" class="flex items-center justify-between w-full text-left pl-4 pr-3 py-2 rounded-md">
                            <span class="flex items-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                </svg>
                                <span class="ml-3">Staff</span>
                            </span>
                            <svg class="w-4 h-4 transform transition-transform duration-200" :class="{ 'rotate-180': openGroup === 'staff' }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <ul x-show="openGroup === 'staff'" x-collapse class="mt-1 space-y-1 pl-10">
                            @if(in_array('View Staff', (array) $permissions))
                            <li><a href="{{ route('users.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Staff</a></li>
                            @endif
                            @if(in_array('View Contractor Profile', (array) $permissions))
                            <li><a href="{{ route('contractor-profiles.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Contractors</a></li>
                            @endif
                        </ul>
                    </li>
                    @endif

                    <!-- HR (always shown when enabled; extra nav items added once API cache warms) -->
                    @if($hrModuleEnabled)
                    <li>
                        <button @click="openGroup === 'hr' ? openGroup = '' : openGroup = 'hr'"
                                :class="openGroup === 'hr' ? 'border border-blue-500 text-blue-700 bg-blue-50' : 'text-gray-700 hover:text-blue-700'"
                                class="flex items-center justify-between w-full text-left pl-4 pr-3 py-2 rounded-md">
                            <span class="flex items-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <span class="ml-3 text-sm font-medium lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100 duration-200">HR</span>
                            </span>
                            <svg class="w-4 h-4 transform transition-transform duration-200 lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100" :class="{ 'rotate-180': openGroup === 'hr' }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <ul x-show="openGroup === 'hr'" x-collapse class="mt-1 space-y-1 pl-10">
                            @foreach($hrNavigation as $hrNavItem)
                            <li>
                                <a href="{{ route('hr.embed', ['path' => $hrNavItem['path']]) }}"
                                   class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>
                                    {{ $hrNavItem['label'] }}
                                </a>
                            </li>
                            @endforeach
                        </ul>
                    </li>
                    @endif

                    <!-- Businesses Group -->
                    @if(in_array('View Business', (array) $permissions))
                    <li>
                        <button @click="openGroup === 'business' ? openGroup = '' : openGroup = 'business'" :class="openGroup === 'business' ? 'border border-blue-500 text-blue-700 bg-blue-50' : 'text-gray-700 hover:text-blue-700'" class="flex items-center justify-between w-full text-left pl-4 pr-3 py-2 rounded-md">
                            <span class="flex items-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                <span class="ml-3">Businesses</span>
                            </span>
                            <svg class="w-4 h-4 transform transition-transform duration-200" :class="{ 'rotate-180': openGroup === 'business' }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <ul x-show="openGroup === 'business'" x-collapse class="mt-1 space-y-1 pl-10">
                            @if(in_array('View Business', (array) $permissions))
                            <li><a href="{{ route('businesses.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Business</a></li>
                            @endif
                            @if(in_array('View Branches', (array) $permissions))
                            <li><a href="{{ route('branches.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Branches</a></li>
                            @endif
                        </ul>
                    </li>
                    @endif

                    <!-- Items Group -->
                    @if(in_array('View Items', $permissions))
                    <li>
                        <button @click="openGroup === 'items' ? openGroup = '' : openGroup = 'items'" :class="openGroup === 'items' ? 'border border-blue-500 text-blue-700 bg-blue-50' : 'text-gray-700 hover:text-blue-700'" class="flex items-center justify-between w-full text-left pl-4 pr-3 py-2 rounded-md">
                            <span class="flex items-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                                <span class="ml-3">Items</span>
                            </span>
                            <svg class="w-4 h-4 transform transition-transform duration-200" :class="{ 'rotate-180': openGroup === 'items' }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <ul x-show="openGroup === 'items'" x-collapse class="mt-1 space-y-1 pl-10">
                            @if(in_array('View Items', $permissions))
                            <li><a href="{{ route('items.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Items</a></li>
                            @endif
                        </ul>
                    </li>
                    @endif

                    <!-- Package Tracking Group -->
                    @if(in_array('View Package Tracking', $permissions))
                    <li>
                        <button @click="openGroup === 'package-tracking' ? openGroup = '' : openGroup = 'package-tracking'" :class="openGroup === 'package-tracking' ? 'border border-blue-500 text-blue-700 bg-blue-50' : 'text-gray-700 hover:text-blue-700'" class="flex items-center justify-between w-full text-left pl-4 pr-3 py-2 rounded-md">
                            <span class="flex items-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                </svg>
                                <span class="ml-3">Package Tracking</span>
                            </span>
                            <svg class="w-4 h-4 transform transition-transform duration-200" :class="{ 'rotate-180': openGroup === 'package-tracking' }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <ul x-show="openGroup === 'package-tracking'" x-collapse class="mt-1 space-y-1 pl-10">
                            <li><a href="{{ route('package-tracking.dashboard') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Dashboard</a></li>
                            <li><a href="{{ route('package-tracking.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>All Packages</a></li>
                        </ul>
                    </li>
                    @endif

                    <!-- Package Sales Group -->
                    @if(in_array('View Package Sales', $permissions))
                    <li>
                        <button @click="openGroup === 'package-sales' ? openGroup = '' : openGroup = 'package-sales'" :class="openGroup === 'package-sales' ? 'border border-blue-500 text-blue-700 bg-blue-50' : 'text-gray-700 hover:text-blue-700'" class="flex items-center justify-between w-full text-left pl-4 pr-3 py-2 rounded-md">
                            <span class="flex items-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                                <span class="ml-3">Package Sales</span>
                            </span>
                            <svg class="w-4 h-4 transform transition-transform duration-200" :class="{ 'rotate-180': openGroup === 'package-sales' }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <ul x-show="openGroup === 'package-sales'" x-collapse class="mt-1 space-y-1 pl-10">
                            @if(in_array('View Package Sales', $permissions))
                            <li><a href="{{ route('package-sales.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>All Package Sales</a></li>
                            @endif
                            @if(in_array('View Package Sales History', $permissions))
                            <li><a href="{{ route('package-sales.history') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Sales History</a></li>
                            @endif
                            @if(in_array('Export Package Sales', $permissions))
                            <li><a href="{{ route('package-sales.export') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Export Sales</a></li>
                            @endif
                        </ul>
                    </li>
                    @endif

                    <!-- Manage Finance -->
                    @if(in_array('View Finance', $permissions))
                    <li>
                        <button @click="openGroup === 'finance' ? openGroup = '' : openGroup = 'finance'" :class="openGroup === 'finance' ? 'border border-blue-500 text-blue-700 bg-blue-50' : 'text-gray-700 hover:text-blue-700'" class="flex items-center justify-between w-full text-left pl-4 pr-3 py-2 rounded-md">
                            <span class="flex items-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                                <span class="ml-3">Manage Finance</span>
                            </span>
                            <svg class="w-4 h-4 transform transition-transform duration-200" :class="{ 'rotate-180': openGroup === 'finance' }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <ul x-show="openGroup === 'finance'" x-collapse class="mt-1 space-y-1 pl-10">
                            <!-- Business Account Statement - Only for regular businesses (not Kashtre) -->
                            @if(Auth::user()->business_id != 1)
                            <li>
                                <a href="{{ route('business-balance-statement.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>
                                    Business Account Statement
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('refunds.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>
                                    Refunds
                                </a>
                            </li>
                            @endif
                            
                            <!-- Accounts Receivable - Available for all businesses with permission -->
                            @if(in_array('View Accounts Receivable', (array) $permissions))
                            <li>
                                <a href="{{ route('accounts-receivable.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>
                                    <span class="flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        Accounts Receivable
                                    </span>
                                </a>
                            </li>
                            @endif

                            <!-- Credit Limit Requests - Available for all businesses with permission -->
                            @if(in_array('Manage Credit Limits', (array) $permissions))
                            @php
                                $user = Auth::user();
                                $pendingCount = 0;
                                
                                // Get user's approval levels
                                $userApproverLevels = \App\Models\CreditLimitApprovalApprover::where('business_id', $user->business_id)
                                    ->where('approver_id', $user->id)
                                    ->pluck('approval_level')
                                    ->toArray();
                                
                                // Count pending authorizations
                                if (in_array('authorizer', $userApproverLevels)) {
                                    $pendingCount += \App\Models\CreditLimitChangeRequest::where('business_id', $user->business_id)
                                        ->where('status', 'initiated')
                                        ->where('current_step', 2)
                                        ->whereHas('approvals', function ($q) use ($user) {
                                            $q->where('approver_id', $user->id)
                                              ->where('approval_level', 'authorizer')
                                              ->whereNull('action');
                                        })
                                        ->count();
                                }
                                
                                // Count pending approvals
                                if (in_array('approver', $userApproverLevels)) {
                                    $pendingCount += \App\Models\CreditLimitChangeRequest::where('business_id', $user->business_id)
                                        ->where('status', 'authorized')
                                        ->where('current_step', 3)
                                        ->whereHas('approvals', function ($q) use ($user) {
                                            $q->where('approver_id', $user->id)
                                              ->where('approval_level', 'approver')
                                              ->whereNull('action');
                                        })
                                        ->count();
                                }
                            @endphp
                            <li>
                                <a href="{{ route('credit-limit-requests.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>
                                    <span class="flex items-center justify-between w-full">
                                        <span class="flex items-center">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            Credit Limit Requests
                                        </span>
                                        @if($pendingCount > 0)
                                            <span class="ml-2 bg-yellow-100 text-yellow-800 text-xs font-semibold px-2 py-0.5 rounded-full">
                                                {{ $pendingCount }}
                                            </span>
                                        @endif
                                    </span>
                                </a>
                            </li>
                            @endif
                            
                            <!-- Kashtre Account Statement - Only for super business (Kashtre) -->
                            @if(Auth::user()->business_id == 1)
                            <li>
                                <a href="{{ route('kashtre-balance-statement.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>
                                    <span class="flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                        </svg>
                                        Kashtre Account Statement
                                    </span>
                                </a>
                            </li>
                            @endif
                            
                            <!-- Suspense Accounts - Only for Kashtre (business_id = 1) -->
                            @if(Auth::user()->business_id == 1)
                            <li>
                                <a href="{{ route('suspense-accounts.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>
                                    <span class="flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                        </svg>
                                        Suspense Accounts
                                    </span>
                                </a>
                            </li>
                            @endif
                            
                            <!-- Withdrawal Requests -->
                            {{-- @if(in_array('View Withdrawal Requests', $permissions)) --}}
                            <li>
                                <a href="{{ route('withdrawal-requests.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>
                                    <span class="flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                        </svg>
                                        Withdrawal Requests
                                    </span>
                                </a>
                            </li>
                            {{-- @endif --}}
                            
                            <!-- Bank Schedules - Only for Kashtre (business_id = 1) -->
                            @if(Auth::user()->business_id == 1 && in_array('Manage Bank Schedules', $permissions))
                            <li>
                                <a href="{{ route('bank-schedules.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>
                                    <span class="flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        Bank Schedules
                                    </span>
                                </a>
                            </li>
                            @endif
                        </ul>
                    </li>
                    @endif

                    <!-- Clients Group -->
                    @if(in_array('View Clients', $permissions))
                    <li>
                        <button @click="openGroup === 'clients' ? openGroup = '' : openGroup = 'clients'" :class="openGroup === 'clients' ? 'border border-blue-500 text-blue-700 bg-blue-50' : 'text-gray-700 hover:text-blue-700'" class="flex items-center justify-between w-full text-left pl-4 pr-3 py-2 rounded-md">
                            <span class="flex items-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                <span class="ml-3">Clients</span>
                            </span>
                            <svg class="w-4 h-4 transform transition-transform duration-200" :class="{ 'rotate-180': openGroup === 'clients' }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <ul x-show="openGroup === 'clients'" x-collapse class="mt-1 space-y-1 pl-10">
                            @if(in_array('View Clients', $permissions))
                            <li><a href="{{ route('clients.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>All Clients</a></li>
                            @endif
                            @if(in_array('Add Clients', $permissions))
                            <li><a href="{{ route('clients.create') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Register Visit</a></li>
                            @endif
                            @if(in_array('View Visits', $permissions))
                            <li><a href="{{ route('daily-visits.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Active Visits</a></li>
                            <li><a href="{{ route('visit-archives.index', ['recordType' => 'snapshot']) }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Daily Visit Record</a></li>
                            <li><a href="{{ route('visit-archives.index', ['recordType' => 'previous']) }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Previous Visits</a></li>
                            @endif
                            @if(in_array('View Clients', $permissions))
                            <li><a href="{{ route('clients.completed') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Completed Items</a></li>
                            @endif
                        </ul>
                    </li>
                    @endif

                    <!-- Third Party Payers Group (only for Kashtre - business_id == 1) -->
                    @if(Auth::user()->business_id == 1 && in_array('View Third Party Payers', (array) $permissions))
                    <li>
                        <button @click="openGroup === 'third-party-payers' ? openGroup = '' : openGroup = 'third-party-payers'" :class="openGroup === 'third-party-payers' ? 'border border-blue-500 text-blue-700 bg-blue-50' : 'text-gray-700 hover:text-blue-700'" class="flex items-center justify-between w-full text-left pl-4 pr-3 py-2 rounded-md">
                            <span class="flex items-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                <span class="ml-3">Third Party Payers</span>
                            </span>
                            <svg class="w-4 h-4 transform transition-transform duration-200" :class="{ 'rotate-180': openGroup === 'third-party-payers' }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <ul x-show="openGroup === 'third-party-payers'" x-collapse class="mt-1 space-y-1 pl-10">
                            @if(in_array('View Third Party Payers', (array) $permissions))
                            <li><a href="{{ route('third-party-payers.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>All Third Party Payers</a></li>
                            @endif
                            @if(in_array('Add Third Party Payers', (array) $permissions))
                            <li><a href="{{ route('third-party-payers.create') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Add Third Party Payer</a></li>
                            @endif
                        </ul>
                    </li>
                    @endif

                    <!-- Third Party Vendors (for non-Kashtre businesses) -->
                    @if(Auth::user()->business_id != 1)
                    <li>
                        <a href="{{ route('third-party-vendors.index') }}" class="flex items-center pl-4 pr-3 py-2 rounded-lg {{ request()->routeIs('third-party-vendors.*') ? 'bg-blue-100 text-blue-900 font-semibold' : 'text-gray-700 hover:text-blue-700 hover:bg-blue-50' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                            </svg>
                            <span class="text-sm font-medium ml-3 lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100 duration-200">Third Party Vendors</span>
                        </a>
                    </li>
                    @endif

                    <!-- Reports Group -->
                    @if(in_array('View Reports', $permissions))
                    <li>
                        <button @click="openGroup === 'reports' ? openGroup = '' : openGroup = 'reports'" :class="openGroup === 'reports' ? 'border border-blue-500 text-blue-700 bg-blue-50' : 'text-gray-700 hover:text-blue-700'" class="flex items-center justify-between w-full text-left pl-4 pr-3 py-2 rounded-md">
                            <span class="flex items-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                                <span class="ml-3">Reports</span>
                            </span>
                            <svg class="w-4 h-4 transform transition-transform duration-200" :class="{ 'rotate-180': openGroup === 'reports' }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <ul x-show="openGroup === 'reports'" x-collapse class="mt-1 space-y-1 pl-10">
                            <li><a href="{{ route('dashboard') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>View Reports</a></li>
                        </ul>
                    </li>
                    @endif

                    <!-- Contractor Group (only for contractors) -->
                    @if(Auth::user()->contractorProfile)
                    <li>
                        <button @click="openGroup === 'contractor' ? openGroup = '' : openGroup = 'contractor'" :class="openGroup === 'contractor' ? 'border border-blue-500 text-blue-700 bg-blue-50' : 'text-gray-700 hover:text-blue-700'" class="flex items-center justify-between w-full text-left pl-4 pr-3 py-2 rounded-md">
                            <span class="flex items-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                <span class="ml-3">Contractor</span>
                            </span>
                            <svg class="w-4 h-4 transform transition-transform duration-200" :class="{ 'rotate-180': openGroup === 'contractor' }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <ul x-show="openGroup === 'contractor'" x-collapse class="mt-1 space-y-1 pl-10">
                            <li><a href="{{ route('dashboard') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Dashboard</a></li>
                            <li><a href="{{ route('contractor-balance-statement.show', Auth::user()->contractorProfile->id) }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Contractor Account Statement</a></li>
                            <li><a href="{{ route('service-queues.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Service Queues</a></li>
                        </ul>
                    </li>
                    @endif

                    <!-- Settings Group (for all businesses) -->
                    <li>
                        <button @click="openGroup === 'settings' ? openGroup = '' : openGroup = 'settings'" :class="openGroup === 'settings' ? 'border border-blue-500 text-blue-700 bg-blue-50' : 'text-gray-700 hover:text-blue-700'" class="flex items-center justify-between w-full text-left pl-4 pr-3 py-2 rounded-md">
                            <span class="flex items-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <span class="ml-3">Settings</span>
                            </span>
                            <svg class="w-4 h-4 transform transition-transform duration-200" :class="{ 'rotate-180': openGroup === 'settings' }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <ul x-show="openGroup === 'settings'" x-collapse class="mt-1 space-y-1 pl-10">
                            <!-- Business Settings - Available for all businesses with permission -->
                            @if(in_array('View Business Settings', (array) $permissions))
                            <li>
                                <a href="{{ route('business-settings.edit') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>
                                    Business Settings
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('business-settings.edit') }}#document-letterhead" class="block text-sm text-gray-600 hover:text-blue-700 py-1.5 pl-2 border-l-2 border-transparent hover:border-blue-400" @click.stop>
                                    Document letterhead preview
                                </a>
                            </li>
                            @endif

                            @php
                                $clientSpacePermissions = [
                                    'Client Spaces',
                                    'View Client Spaces',
                                    'Add Client Spaces',
                                    'Edit Client Spaces',
                                    'Delete Client Spaces',
                                ];
                                $canAccessClientSpaces = count(array_intersect($clientSpacePermissions, (array) $permissions)) > 0;
                            @endphp
                            @if($canAccessClientSpaces)
                            <li><a href="{{ route('client-spaces.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Client Spaces</a></li>
                            @endif

                            <!-- Settings only for business_id == 1 (Kashtre) -->
                            @if(Auth::user()->business_id == 1)
                            @if(in_array('View Service Points', $permissions))
                            <li>
                                <a href="{{ route('service-points.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>
                                    Manage Service Points
                                </a>
                            </li>
                            @endif

                            @if(in_array('View Departments', $permissions))
                            <li><a href="{{ route('departments.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Departments</a></li>
                            @endif

                            @if(in_array('View Qualifications', $permissions))
                            <li><a href="{{ route('qualifications.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Qualifications</a></li>
                            @endif

                            @if(in_array('View Titles', $permissions))
                            <li><a href="{{ route('titles.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Titles</a></li>
                            @endif

                            @if(in_array('View Staff Categories', $permissions))
                            <li><a href="{{ route('staff-categories.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Staff Categories</a></li>
                            @endif

                            @if(in_array('View Supplier Industries', $permissions))
                            <li><a href="{{ route('supplier-industries.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Supplier Industries</a></li>
                            @endif

                            @if(in_array('View Supplier Sub Categories', $permissions))
                            <li><a href="{{ route('supplier-sub-categories.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Supplier Sub Categories</a></li>
                            @endif

                            @if(Auth::user()->business_id == 1)
                            <li><a href="{{ route('maturation-periods.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Payment Methods and Maturation Periods</a></li>
                            @endif

                            @if(in_array('View Inventory Module', $permissions))
                            <li><a href="{{ route('inventory-module-configs.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Inventory</a></li>
                            @endif

                            @if(in_array('View Credit Note Workflows', $permissions))
                            <li><a href="{{ route('credit-note-workflows.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Refund Workflows</a></li>
                            @endif

                            @if(in_array('View Rooms', $permissions))
                            <li><a href="{{ route('rooms.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Rooms</a></li>
                            @endif

                            @if(in_array('View Sections', $permissions))
                            <li><a href="{{ route('sections.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Sections</a></li>
                            @endif

                            @if(in_array('View Item Units', $permissions))
                            <li><a href="{{ route('item-units.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Item Units</a></li>
                            @endif

                            @if(in_array('View Groups', $permissions))
                            <li><a href="{{ route('groups.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Groups</a></li>
                            @endif

                            @if(in_array('View Patient Categories', $permissions))
                            <li><a href="{{ route('patient-categories.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Client Categories</a></li>
                            @endif

                            @if(in_array('View Suppliers', $permissions))
                            <li><a href="{{ route('suppliers.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Suppliers</a></li>
                            @endif

                            @if(in_array('View Stores', $permissions))
                            <li><a href="{{ route('stores.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Stores</a></li>
                            @endif

                            @if(in_array('View Item Categories', $permissions))
                            <li><a href="{{ route('item-importance-categories.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Item Categories</a></li>
                            @endif


                            @if(in_array('View Insurance Companies', $permissions))
                            <li><a href="{{ route('settings.index', ['tab' => 'insurance-companies']) }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Third Party Vendors</a></li>
                            @endif

                            @if(in_array('Manage System Settings', (array) $permissions) || in_array('Manage Settings', (array) $permissions))
                            <li>
                                <a href="{{ route('settings.countries.index') }}"
                                   class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>
                                    Manage Countries & Currencies
                                </a>
                            </li>
                            @endif

                            @if((int) Auth::user()->business_id === 1)
                            <li>
                                <a href="{{ route('settings.kashtre.edit') }}"
                                   class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>
                                    Kashtre Settings
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('settings.hr-module.edit') }}"
                                   class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>
                                    HR Settings
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('settings.clinical-module.edit') }}"
                                   class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>
                                    Clinical Module Settings
                                </a>
                            </li>
                            @endif

                            @if(in_array('View Sub Groups', $permissions))
                            <li><a href="{{ route('sub-groups.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Sub Groups</a></li>
                            @endif
                            @if(in_array('Manage Service Charges', $permissions))
                            <li><a href="{{ route('service-charges.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Entity Service Charges</a></li>
                            @endif
                            @if(in_array('Manage Contractor Service Charges', $permissions))
                            <li><a href="{{ route('contractor-service-charges.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Contractor Service Charges</a></li>
                            @endif
                            @if(in_array('View Withdrawal Settings', $permissions))
                            <li><a href="{{ route('withdrawal-settings.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Withdrawal Settings</a></li>
                            @endif
                            @if(in_array('View Business Withdrawal Charges', $permissions))
                            <li><a href="{{ route('business-withdrawal-settings.index') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Business Withdraw Charges</a></li>
                            @endif
                             @if(in_array('Bulk Validations Upload', $permissions)) 
                            <li><a href="{{ route('bulk.upload.form') }}" class="block text-sm text-gray-700 hover:text-blue-700 py-1.5" @click.stop>Manage Bulk Upload</a></li>
                             @endif 
                        </ul>
                    </li>
                    @endif

                </ul>
            </div>
        </div>

        <!-- Expand / collapse button -->
        <div class="pt-3 hidden lg:inline-flex 2xl:hidden justify-end mt-auto">
            <div class="w-12 pl-4 pr-3 py-2">
                <button class="text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400 transition-colors" @click="sidebarExpanded = !sidebarExpanded">
                    <span class="sr-only">Expand / collapse sidebar</span>
                    <svg class="shrink-0 fill-current text-gray-400 dark:text-gray-500 sidebar-expanded:rotate-180" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16">
                        <path d="M15 16a1 1 0 0 1-1-1V1a1 1 0 1 1 2 0v14a1 1 0 0 1-1 1ZM8.586 7H1a1 1 0 1 0 0 2h7.586l-2.793 2.793a1 1 0 1 0 1.414 1.414l4.5-4.5A.997.997 0 0 0 12 8.01M11.924 7.617a.997.997 0 0 0-.217-.324l-4.5-4.5a1 1 0 0 0-1.414 1.414L8.586 7M12 7.99a.996.996 0 0 0-.076-.373Z" />
                    </svg>
                </button>
            </div>
        </div>

    </div>
</div>
