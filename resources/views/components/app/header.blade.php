<header class="sticky top-0 before:absolute before:inset-0 before:backdrop-blur-md max-lg:before:bg-white/90 dark:max-lg:before:bg-gray-800/90 before:-z-10 z-30 {{ $variant === 'v2' || $variant === 'v3' ? 'before:bg-white after:absolute after:h-px after:inset-x-0 after:top-full after:bg-gray-200 dark:after:bg-gray-700/60 after:-z-10' : 'max-lg:shadow-sm lg:before:bg-gray-100/90 dark:lg:before:bg-gray-900/90' }} {{ $variant === 'v2' ? 'dark:before:bg-gray-800' : '' }} {{ $variant === 'v3' ? 'dark:before:bg-gray-900' : '' }}">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 {{ $variant === 'v2' || $variant === 'v3' ? '' : 'lg:border-b border-gray-200 dark:border-gray-700/60' }}">
            
            <!-- Header: Left side -->
            <div class="flex items-center space-x-4">
                <!-- Hamburger button -->
                <button
                    class="text-gray-500 hover:text-gray-600 dark:hover:text-gray-400 lg:hidden"
                    @click.stop="sidebarOpen = !sidebarOpen"
                    aria-controls="sidebar"
                    :aria-expanded="sidebarOpen"
                >
                    <span class="sr-only">Open sidebar</span>
                    <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24">
                        <rect x="4" y="5" width="16" height="2" />
                        <rect x="4" y="11" width="16" height="2" />
                        <rect x="4" y="17" width="16" height="2" />
                    </svg>
                </button>

                <!-- App Mode Badge -->
                {{-- @php
                    $mode = strtolower($business->mode ?? '');
                    $isLive = $mode === 'live';
                    $badgeClass = $isLive
                        ? 'bg-green-100 border border-green-500 text-green-700'
                        : ($mode === 'sandbox'
                            ? 'bg-yellow-100 border border-yellow-500 text-yellow-700'
                            : 'bg-gray-100 border border-gray-400 text-gray-700');
                    $modeLabel = $isLive ? 'LIVE MODE' : ($mode === 'sandbox' ? 'SANDBOX MODE' : 'N/A');
                @endphp

                <span class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-bold uppercase {{ $badgeClass }}">
                    {{ $modeLabel }}
                </span> --}}

                <!-- Dashboard Link -->
                <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-gray-700 hover:text-blue-600 dark:text-gray-200 dark:hover:text-white transition">
                    Dashboard
                </a>

            </div>

            <!-- Header: Right side -->
            <div class="flex items-center space-x-3">
                <!-- Dashboard shortcut -->
                <a href="{{ route('dashboard') }}" class="text-gray-500 hover:text-gray-600 dark:hover:text-gray-400">
                    <span class="sr-only">Dashboard</span>
                    <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24">
                        <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8v-10h-8v10zm0-18v6h8V3h-8z"/>
                    </svg>
                </a>

                <!-- Notifications -->
                <x-dropdown-notifications align="right" />

                <!-- Theme toggle -->
                <x-theme-toggle />

                <!-- Divider -->
                <hr class="w-px h-6 bg-gray-200 dark:bg-gray-700/60 border-none" />

                <!-- Impersonation Mini Badge -->
                @impersonating
                    <a
                        href="{{ route('impersonate.leave') }}"
                        class="inline-flex items-center gap-1 text-xs font-semibold text-yellow-900 bg-yellow-300 hover:bg-yellow-400 px-2 py-1 rounded-full transition"
                        title="Stop impersonating {{ auth()->user()->name }}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Impersonating
                    </a>
                @endImpersonating

                <!-- User Profile Dropdown -->
                <x-dropdown-profile align="right" />
            </div>

        </div>
    </div>
</header>
