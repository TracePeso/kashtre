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

    $primaryItems = [
        [
            'label' => 'Dashboard',
            'route' => 'hr.dashboard',
            'active' => request()->routeIs('hr.dashboard'),
            'icon' => 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z M8 5a2 2 0 012-2h4a2 2 0 012 2v6H8V5z',
            'visible' => true,
        ],
        [
            'label' => 'AI Roster Constraints',
            'route' => 'hr.ai-roster-constraints.index',
            'active' => request()->routeIs('hr.ai-roster-constraints.*'),
            'icon' => 'M9.75 3.104c.69-1.2 2.41-1.2 3.1 0l.824 1.432a1.8 1.8 0 001.554.9h1.648c1.38 0 2.24 1.494 1.55 2.694l-.824 1.432a1.8 1.8 0 000 1.8l.824 1.432c.69 1.2-.17 2.694-1.55 2.694h-1.648a1.8 1.8 0 00-1.554.9l-.824 1.432c-.69 1.2-2.41 1.2-3.1 0l-.824-1.432a1.8 1.8 0 00-1.554-.9H5.678c-1.38 0-2.24-1.494-1.55-2.694l.824-1.432a1.8 1.8 0 000-1.8l-.824-1.432c-.69-1.2.17-2.694 1.55-2.694h1.648a1.8 1.8 0 001.554-.9l.87-1.432z',
            'visible' => ($user?->canViewHrSetup() ?? false) || ($user?->canManageAiRosterConstraints() ?? false),
        ],
    ];

    $navSections = [
        [
            'label' => 'Workforce',
            'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z',
            'items' => [
                [
                    'label' => 'Staff Routing',
                    'href' => route('hr.staff-assignments.index'),
                    'active' => request()->routeIs('hr.staff-assignments.*'),
                    'visible' => $user?->canViewHrStaff() ?? false,
                ],
                [
                    'label' => 'Client Spaces',
                    'href' => route('hr.client-spaces.index'),
                    'active' => request()->routeIs('hr.client-spaces.*'),
                    'visible' => ($user?->canViewHrStaff() ?? false) || ($user?->canViewHrSetup() ?? false),
                ],
                [
                    'label' => 'Tier Pool',
                    'href' => route('hr.tier-staff-assignments.index'),
                    'active' => request()->routeIs('hr.tier-staff-assignments.*'),
                    'visible' => ($user?->canViewHrStaff() ?? false) || $belongsToRoutingTier,
                ],
                [
                    'label' => 'Routing Structure',
                    'href' => route('hr.organizational-structure.index'),
                    'active' => request()->routeIs('hr.organizational-structure.*'),
                    'visible' => $user?->canViewHrSetup() ?? false,
                ],
            ],
        ],
        [
            'label' => 'Scheduling',
            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 12h6M9 16h6M9 8h6',
            'items' => [
                [
                    'label' => 'Rosters',
                    'href' => route('hr.rosters.index'),
                    'active' => request()->routeIs('hr.rosters.*'),
                    'visible' => ($user?->canViewHrStaff() ?? false) || ($user?->canViewHrSetup() ?? false) || $belongsToClientSpace,
                ],
                [
                    'label' => 'Open Shifts',
                    'href' => route('hr.open-shifts.index'),
                    'active' => request()->routeIs('hr.open-shifts.*'),
                    'visible' => (bool) ($user?->staff_uuid) || ($user?->canViewHrStaff() ?? false) || ($user?->canViewHrSetup() ?? false),
                ],
                [
                    'label' => 'My Roster',
                    'href' => route('hr.my-roster.index'),
                    'active' => request()->routeIs('hr.my-roster.*'),
                    'visible' => (bool) ($user?->staff_uuid),
                ],
                [
                    'label' => 'Shift Types',
                    'href' => route('hr.shift-types.index'),
                    'active' => request()->routeIs('hr.shift-types.*'),
                    'visible' => $user?->canViewHrSetup() ?? false,
                ],
            ],
        ],
        [
            'label' => 'Attendance',
            'icon' => 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M4 6h7a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2zm4 4a2 2 0 100 4 2 2 0 000-4z',
            'items' => [
                [
                    'label' => 'Biometric Enrollment',
                    'href' => route('hr.biometrics.enrollment'),
                    'active' => request()->routeIs('hr.biometrics.index', 'hr.biometrics.enrollment'),
                    'visible' => $user?->canViewHrBiometrics() ?? false,
                ],
                [
                    'label' => 'Biometric Attendance',
                    'href' => route('hr.biometrics.attendance'),
                    'active' => request()->routeIs('hr.biometrics.attendance'),
                    'visible' => $user?->canViewHrBiometrics() ?? false,
                ],
                [
                    'label' => 'Biometric Settings',
                    'href' => route('hr.biometrics.settings'),
                    'active' => request()->routeIs('hr.biometrics.settings'),
                    'visible' => $user?->canManageHrBiometrics() ?? false,
                ],
                [
                    'label' => 'Leave Applications',
                    'href' => route('hr.leave-applications.index'),
                    'active' => request()->routeIs('hr.leave-applications.*'),
                    'visible' => (bool) ($user?->staff_uuid) || ($user?->canViewHrStaff() ?? false) || ($user?->canViewHrSetup() ?? false),
                ],
                [
                    'label' => 'Leave Types',
                    'href' => route('hr.leave-types.index'),
                    'active' => request()->routeIs('hr.leave-types.*'),
                    'visible' => $user?->canViewHrSetup() ?? false,
                ],
                [
                    'label' => 'Calendar',
                    'href' => route('hr.calendar.index'),
                    'active' => request()->routeIs('hr.calendar.*'),
                    'visible' => $user?->canViewHrSetup() ?? false,
                ],
            ],
        ],
        [
            'label' => 'Governance',
            'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
            'items' => [
                [
                    'label' => 'Approval Requests',
                    'href' => route('hr.approval-requests.index'),
                    'active' => request()->routeIs('hr.approval-requests.*'),
                    'visible' => true,
                ],
                [
                    'label' => 'Approval Workflows',
                    'href' => route('hr.approval-workflows.index'),
                    'active' => request()->routeIs('hr.approval-workflows.*'),
                    'visible' => $user?->canViewHrSetup() ?? false,
                ],
                [
                    'label' => 'Policies',
                    'href' => route('hr.policies.index'),
                    'active' => request()->routeIs('hr.policies.*'),
                    'visible' => $user?->canViewHrSetup() ?? false,
                ],
            ],
        ],
    ];

    $navSections = collect($navSections)
        ->map(function (array $section): array {
            $section['items'] = collect($section['items'])
                ->filter(fn (array $item): bool => (bool) ($item['visible'] ?? true))
                ->values()
                ->all();

            $section['visible'] = ! empty($section['items']);
            $section['active'] = collect($section['items'])->contains(fn (array $item): bool => (bool) ($item['active'] ?? false));

            return $section;
        })
        ->filter(fn (array $section): bool => $section['visible'])
        ->values()
        ->all();

    $openGroup = collect($navSections)
        ->first(fn (array $section) => $section['active'] ?? false);

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
                @foreach ($primaryItems as $item)
                    @continue(! ($item['visible'] ?? true))
                    <li>
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
                    </li>
                @endforeach
                @foreach ($navSections as $section)
                    @php($groupKey = \Illuminate\Support\Str::slug($section['label']))
                    <li class="pt-2">
                        <button
                            type="button"
                            @click="openGroup = openGroup === '{{ $groupKey }}' ? '' : '{{ $groupKey }}'"
                            :class="openGroup === '{{ $groupKey }}' || {{ ($section['active'] ?? false) ? 'true' : 'false' }} ? 'border border-blue-500 text-blue-700 bg-blue-50' : 'text-gray-700 hover:text-blue-700'"
                            class="flex w-full items-center justify-between rounded-md pl-4 pr-3 py-2 text-left"
                            :aria-expanded="(openGroup === '{{ $groupKey }}').toString()"
                        >
                            <span class="flex items-center">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $section['icon'] }}"></path>
                                </svg>
                                <span class="ml-3 flex-1 text-sm font-medium whitespace-nowrap text-left duration-200 lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100">
                                    {{ $section['label'] }}
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
                            @foreach($section['items'] as $child)
                                <li>
                                    <a href="{{ $child['href'] }}" class="block py-1.5 text-sm {{ ($child['active'] ?? false) ? 'font-medium text-blue-700' : 'text-gray-700 hover:text-blue-700' }}">
                                        {{ $child['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
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
