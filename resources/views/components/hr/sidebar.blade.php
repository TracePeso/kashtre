@php
    $user = Auth::user();
    $organization = \App\Models\Organization::current($user);
    $belongsToRoutingTier = $user?->staff_uuid
        ? \App\Models\StaffAssignment::query()
            ->where('staff_uuid', $user->staff_uuid)
            ->whereNotIn('status', ['inactive', 'orphaned'])
            ->whereHas('organizationalUnit', fn ($query) => $query->routingNodes())
            ->exists()
        : false;
    $belongsToClientSpace = $user?->staff_uuid
        ? \App\Models\StaffAssignment::query()
            ->where('staff_uuid', $user->staff_uuid)
            ->where('status', 'active')
            ->whereHas('organizationalUnit', fn ($query) => $query->clientSpaces())
            ->exists()
        : false;

    $navItems = [
        [
            'label' => 'Dashboard',
            'route' => 'hr.dashboard',
            'active' => request()->routeIs('hr.dashboard'),
            'icon' => 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z M8 5a2 2 0 012-2h4a2 2 0 012 2v6H8V5z',
            'visible' => true,
        ],
        [
            'label' => 'Staff Routing',
            'route' => 'hr.staff-assignments.index',
            'active' => request()->routeIs('hr.staff-assignments.*'),
            'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z',
            'visible' => $user?->canViewHrStaff() ?? false,
        ],
        [
            'label' => 'Client Spaces',
            'route' => 'hr.client-spaces.index',
            'active' => request()->routeIs('hr.client-spaces.*'),
            'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2h-1M5 11V9a2 2 0 012-2h1m2-3h4a2 2 0 012 2v1H8V6a2 2 0 012-2z',
            'visible' => ($user?->canViewHrStaff() ?? false) || ($user?->canViewHrSetup() ?? false),
        ],
        [
            'label' => 'Rosters',
            'route' => 'hr.rosters.index',
            'active' => request()->routeIs('hr.rosters.*'),
            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 12h6M9 16h6M9 8h6',
            'visible' => ($user?->canViewHrStaff() ?? false) || ($user?->canViewHrSetup() ?? false) || $belongsToClientSpace,
        ],
        [
            'label' => 'AI Roster Constraints',
            'route' => 'hr.ai-roster-constraints.index',
            'active' => request()->routeIs('hr.ai-roster-constraints.*'),
            'icon' => 'M4 6h16M4 12h10m-10 6h16',
            'visible' => $user?->canManageAiRosterConstraints() ?? false,
        ],
        [
            'label' => 'Open Shifts',
            'route' => 'hr.open-shifts.index',
            'active' => request()->routeIs('hr.open-shifts.*'),
            'icon' => 'M12 8c-2.21 0-4 1.79-4 4m8-4c2.21 0 4 1.79 4 4m-8 0v8m0-8a4 4 0 118 0m-8 0a4 4 0 10-8 0m4 8h8',
            'visible' => ($user?->staff_uuid) || ($user?->canViewHrStaff() ?? false) || ($user?->canViewHrSetup() ?? false),
        ],
        [
            'label' => 'My Roster',
            'route' => 'hr.my-roster.index',
            'active' => request()->routeIs('hr.my-roster.*'),
            'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2zm3-4h2m4 0h2',
            'visible' => (bool) ($user?->staff_uuid),
        ],
        [
            'label' => 'Biometrics',
            'route' => 'hr.biometrics.index',
            'active' => request()->routeIs('hr.biometrics.*'),
            'icon' => 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M4 6h7a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2zm4 4a2 2 0 100 4 2 2 0 000-4z',
            'visible' => $user?->canViewHrBiometrics() ?? false,
            'children' => [
                [
                    'label' => 'Enrollment',
                    'href' => route('hr.biometrics.enrollment'),
                    'active' => request()->routeIs('hr.biometrics.index', 'hr.biometrics.enrollment'),
                    'visible' => true,
                ],
                [
                    'label' => 'Attendance',
                    'href' => route('hr.biometrics.attendance'),
                    'active' => request()->routeIs('hr.biometrics.attendance'),
                    'visible' => true,
                ],
                [
                    'label' => 'Settings',
                    'href' => route('hr.biometrics.settings'),
                    'active' => request()->routeIs('hr.biometrics.settings'),
                    'visible' => $user?->canManageHrBiometrics() ?? false,
                ],
            ],
        ],
        [
            'label' => 'Tier Pool',
            'route' => 'hr.tier-staff-assignments.index',
            'active' => request()->routeIs('hr.tier-staff-assignments.*'),
            'icon' => 'M16 11c1.657 0 2.969-1.626 2.969-3.625 0-1.999-1.312-3.625-2.969-3.625-1.658 0-2.969 1.626-2.969 3.625 0 1.999 1.311 3.625 2.969 3.625zm-9.75 9h-4.25A1 1 0 012 19v-3.5c0-2.485 2.686-4.5 6-4.5s6 2.015 6 4.5V19a1 1 0 01-1 1H6.25zm12-8a.75.75 0 01.75-.75h2a.75.75 0 010 1.5h-2a.75.75 0 01-.75-.75z',
            'visible' => ($user?->canViewHrStaff() ?? false) || $belongsToRoutingTier,
        ],
        [
            'label' => 'Approvals',
            'route' => 'hr.approval-workflows.index',
            'active' => request()->routeIs('hr.approval-workflows.*'),
            'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
            'visible' => $user?->canViewHrSetup() ?? false,
        ],
        [
            'label' => 'Routing Structure',
            'route' => 'hr.organizational-structure.index',
            'active' => request()->routeIs('hr.organizational-structure.*'),
            'icon' => 'M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v2M7 13h10M7 17h6M5 21h14a2 2 0 002-2v-8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z',
            'visible' => $user?->canViewHrSetup() ?? false,
        ],
        [
            'label' => 'Approval Requests',
            'route' => 'hr.approval-requests.index',
            'active' => request()->routeIs('hr.approval-requests.*'),
            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2m-6 4h6m-6 4h6m-6 4h4m-2-14h4a2 2 0 012 2v0a2 2 0 01-2 2h-4a2 2 0 01-2-2v0a2 2 0 012-2z',
            'visible' => true,
        ],
        [
            'label' => 'Shifts',
            'route' => 'hr.shift-types.index',
            'active' => request()->routeIs('hr.shift-types.*'),
            'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
            'visible' => $user?->canViewHrSetup() ?? false,
        ],
        [
            'label' => 'Leave',
            'active' => request()->routeIs('hr.leave-applications.*', 'hr.leave-types.*'),
            'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
            'visible' => (bool) ($user?->staff_uuid) || ($user?->canViewHrStaff() ?? false) || ($user?->canViewHrSetup() ?? false),
            'children' => [
                [
                    'label' => 'Applications',
                    'href' => route('hr.leave-applications.index'),
                    'active' => request()->routeIs('hr.leave-applications.*'),
                    'visible' => true,
                ],
                [
                    'label' => 'Types',
                    'href' => route('hr.leave-types.index'),
                    'active' => request()->routeIs('hr.leave-types.*'),
                    'visible' => $user?->canViewHrSetup() ?? false,
                ],
            ],
        ],
        [
            'label' => 'Calendar',
            'route' => 'hr.calendar.index',
            'active' => request()->routeIs('hr.calendar.*'),
            'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2zm3 4h2v2h-2v-2zm4 0h2v2h-2v-2zm-8 4h2v2H7v-2zm4 0h2v2h-2v-2z',
            'visible' => $user?->canViewHrSetup() ?? false,
        ],
        [
            'label' => 'Policies',
            'route' => 'hr.policies.index',
            'active' => request()->routeIs('hr.policies.*'),
            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            'visible' => $user?->canViewHrSetup() ?? false,
        ],
    ];

    $openGroup = collect($navItems)
        ->first(fn (array $item) => ! empty($item['children']) && ($item['active'] ?? false));

    $openGroupKey = $openGroup ? \Illuminate\Support\Str::slug($openGroup['label']) : '';
@endphp

<div class="min-w-fit">
    <div
        class="fixed inset-0 z-40 bg-gray-900/30 transition-opacity duration-200 lg:hidden lg:z-auto"
        :class="sidebarOpen ? 'opacity-100' : 'opacity-0 pointer-events-none'"
        aria-hidden="true"
        x-cloak
    ></div>

    <div
        id="sidebar"
        class="absolute left-0 top-0 z-40 flex h-[100dvh] w-64 shrink-0 flex-col overflow-y-scroll no-scrollbar bg-white p-4 transition-all duration-200 ease-in-out dark:bg-gray-800 lg:static lg:left-auto lg:top-auto lg:w-20 lg:translate-x-0 lg:overflow-y-auto lg:sidebar-expanded:!w-64 2xl:!w-64 rounded-r-2xl shadow-xs"
        :class="sidebarOpen ? 'max-lg:translate-x-0' : 'max-lg:-translate-x-64'"
        @click.outside="sidebarOpen = false"
        @keydown.escape.window="sidebarOpen = false"
    >
        <div class="mb-10 flex justify-between pr-3 sm:px-2">
            <button
                class="text-gray-500 hover:text-gray-400 lg:hidden"
                @click.stop="sidebarOpen = !sidebarOpen"
                aria-controls="sidebar"
                :aria-expanded="sidebarOpen"
            >
                <span class="sr-only">Close sidebar</span>
                <svg class="h-6 w-6 fill-current" viewBox="0 0 24 24">
                    <path d="M10.7 18.7l1.4-1.4L7.8 13H20v-2H7.8l4.3-4.3-1.4-1.4L4 12z" />
                </svg>
            </button>

            <a href="{{ route('hr.dashboard') }}" class="flex w-full flex-col items-center">
                <h1 class="mb-1 text-xl font-extrabold text-[#011478]">HR Manager</h1>
                <div class="h-16 w-16 overflow-hidden rounded-lg">
                    <img src="{{ asset('images/kashtre_logo.svg') }}" alt="Kashtre Logo" class="h-full w-full object-contain">
                </div>
                <h2 class="mt-2 text-center text-sm font-bold text-[#011478] lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100 duration-200">
                    {{ $organization?->name ?: 'Kashtre HR Workspace' }}
                </h2>
                <p class="mt-0.5 text-center text-xs text-gray-500 lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100 duration-200">
                    Independent HR module
                </p>
            </a>
        </div>

        <div class="space-y-8">
            <div>
                <ul class="mt-3 space-y-2" x-data="{ openGroup: '{{ $openGroupKey }}' }">
                @foreach ($navItems as $item)
                    @continue(! $item['visible'])
                    @php($groupKey = \Illuminate\Support\Str::slug($item['label']))
                    <li>
                        @if(! empty($item['children']))
                            <button
                                type="button"
                                @click="openGroup = openGroup === '{{ $groupKey }}' ? '' : '{{ $groupKey }}'"
                                :class="openGroup === '{{ $groupKey }}' ? 'border border-blue-500 text-blue-700 bg-blue-50' : 'text-gray-700 hover:text-blue-700'"
                                class="flex w-full items-center justify-between rounded-md pl-4 pr-3 py-2 text-left"
                                :aria-expanded="(openGroup === '{{ $groupKey }}').toString()"
                            >
                                <span class="flex items-center">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"></path>
                                    </svg>
                                    <span class="ml-3 flex-1 text-sm font-medium whitespace-nowrap text-left duration-200 lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100">
                                        {{ $item['label'] }}
                                    </span>
                                </span>
                                <svg class="h-4 w-4 transform transition-transform duration-200 lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100" :class="{ 'rotate-180': openGroup === '{{ $groupKey }}' }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <ul
                                x-show="openGroup === '{{ $groupKey }}'"
                                x-collapse
                                class="mt-1 space-y-1 pl-10"
                            >
                                @foreach($item['children'] as $child)
                                    @continue(! ($child['visible'] ?? true))
                                    <li>
                                        <a href="{{ $child['href'] }}" class="block py-1.5 text-sm {{ ($child['active'] ?? false) ? 'font-medium text-blue-700' : 'text-gray-700 hover:text-blue-700' }}">
                                            {{ $child['label'] }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <a
                                href="{{ route($item['route']) }}"
                                class="flex items-center rounded-lg pl-4 pr-3 py-2 {{ $item['active'] ? 'bg-blue-100 text-blue-900 font-semibold' : 'text-gray-700 hover:text-blue-700 hover:bg-blue-50' }}"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"></path>
                                </svg>
                                <span class="ml-3 text-sm font-medium whitespace-nowrap duration-200 lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100">
                                    {{ $item['label'] }}
                                </span>
                            </a>
                        @endif
                    </li>
                @endforeach
                </ul>
            </div>
        </div>

        <div class="mt-auto hidden justify-end pt-3 lg:inline-flex 2xl:hidden">
            <div class="w-12 pl-4 pr-3 py-2">
                <button class="text-gray-400 transition-colors hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400" @click="sidebarExpanded = !sidebarExpanded" type="button">
                    <span class="sr-only">Expand or collapse sidebar</span>
                    <svg class="shrink-0 fill-current text-gray-400 dark:text-gray-500 sidebar-expanded:rotate-180" width="16" height="16" viewBox="0 0 16 16">
                        <path d="M15 16a1 1 0 0 1-1-1V1a1 1 0 1 1 2 0v14a1 1 0 0 1-1 1ZM8.586 7H1a1 1 0 1 0 0 2h7.586l-2.793 2.793a1 1 0 1 0 1.414 1.414l4.5-4.5A.997.997 0 0 0 12 8.01M11.924 7.617a.997.997 0 0 0-.217-.324l-4.5-4.5a1 1 0 0 0-1.414 1.414L8.586 7M12 7.99a.996.996 0 0 0-.076-.373Z" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="pt-3">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button
                    type="submit"
                    class="flex w-full items-center rounded-lg px-4 py-2 text-gray-700 transition hover:bg-red-50 hover:text-red-700"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span class="ml-3 text-sm font-medium whitespace-nowrap duration-200 lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100">
                        Sign Out
                    </span>
                </button>
            </form>
        </div>
    </div>
</div>
