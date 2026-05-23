<x-hr-layout>
    <x-slot name="header">HR Dashboard</x-slot>

    @php($user = Auth::user())

    @if($user?->canSyncHrData())
    <div class="flex justify-end mb-6">
        <form method="POST" action="{{ route('hr.dashboard.sync') }}">
            @csrf
            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                Refresh HR Data
            </button>
        </form>
    </div>
    @endif

    @if(($stats['orphaned_staff'] ?? 0) > 0 || ($stats['stuck_in_routing'] ?? 0) > 0)
    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4">
        <div class="flex items-start gap-3">
            <svg class="mt-0.5 h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M4.93 19h14.14a2 2 0 001.74-3L13.74 5a2 2 0 00-3.48 0L3.19 16a2 2 0 001.74 3z"/>
            </svg>
            <div class="flex-1">
                <p class="text-sm font-semibold text-red-800">Staff routing needs attention</p>
                <p class="mt-1 text-sm text-red-700">
                    {{ number_format($stats['orphaned_staff']) }} staff are not attached to any client space and {{ number_format($stats['stuck_in_routing']) }} are stuck in routing (>48h).
                    Routing tier members should complete routing and client-space placement.
                </p>
                <a href="{{ route('hr.tier-staff-assignments.index') }}" class="mt-2 inline-flex items-center text-sm font-semibold text-red-800 hover:text-red-900">
                    Review tier assignments &rarr;
                </a>
            </div>
        </div>
    </div>
    @endif

    @if(($lateAttendanceNotifications ?? collect())->isNotEmpty())
    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4">
        <div class="flex items-start gap-3">
            <svg class="mt-0.5 h-5 w-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div class="flex-1">
                <p class="text-sm font-semibold text-amber-900">Late attendance alerts</p>
                <div class="mt-3 space-y-3">
                    @foreach($lateAttendanceNotifications as $notification)
                        <div class="rounded-lg border border-amber-200 bg-white px-4 py-3">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ data_get($notification->data, 'title', 'Attendance alert') }}</p>
                                    <p class="mt-1 text-sm text-gray-700">{{ data_get($notification->data, 'message') }}</p>
                                    @if(data_get($notification->data, 'occurred_at'))
                                        <p class="mt-1 text-xs text-gray-500">Recorded at {{ \Illuminate\Support\Carbon::parse(data_get($notification->data, 'occurred_at'))->format('M j, Y H:i') }}</p>
                                    @endif
                                </div>
                                @if(data_get($notification->data, 'action_url'))
                                    <a href="{{ data_get($notification->data, 'action_url') }}" class="inline-flex items-center text-sm font-semibold text-amber-900 hover:text-amber-950">
                                        {{ data_get($notification->data, 'action_label', 'Open') }} &rarr;
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($user?->staff_uuid)
    <div class="mb-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">My Upcoming Roster</h2>
                <p class="mt-1 text-sm text-gray-500">Your scheduled shifts for the next two weeks appear here, including any draft roster entries not published yet.</p>
            </div>
            <a href="{{ route('hr.my-roster.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Open My Roster
            </a>
        </div>

        @if($nextRosterEntry)
                <div class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,18rem)_1fr]">
                    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Next Shift</p>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <p class="text-lg font-semibold text-gray-900">{{ $nextRosterEntry->shiftType?->code ?: 'Shift' }} - {{ $nextRosterEntry->shiftType?->name ?: 'Scheduled Shift' }}</p>
                            @php($isDraftNextRosterEntry = $nextRosterEntry->dutyRoster?->status === \App\Models\HrDutyRoster::STATUS_DRAFT)
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $isDraftNextRosterEntry ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">
                                {{ $isDraftNextRosterEntry ? 'Draft' : 'Published' }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-gray-700">{{ $nextRosterEntry->roster_date?->format('D, M j, Y') }}</p>
                        <p class="mt-1 text-sm text-blue-800">{{ $nextRosterEntry->dutyRoster?->organizationalUnit?->name ?: 'Client Space' }}</p>
                    </div>

            <div class="space-y-3">
                @if($todayRosterEntries->isNotEmpty())
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-green-700">Today</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach($todayRosterEntries as $entry)
                        <span class="rounded-full bg-white px-3 py-1 text-sm font-medium text-gray-700 shadow-sm">{{ $entry->shiftType?->code ?: 'Shift' }} - {{ $entry->shiftType?->name ?: 'Scheduled Shift' }}{{ $entry->dutyRoster?->status === \App\Models\HrDutyRoster::STATUS_DRAFT ? ' (Draft)' : '' }}</span>
                        @endforeach
                    </div>
                </div>
                @endif

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($upcomingRosterEntries->take(6) as $entry)
                    <div class="rounded-lg border border-gray-200 bg-gray-50/70 px-4 py-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold text-gray-900">{{ $entry->roster_date?->format('D, M j') }}</p>
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $entry->dutyRoster?->status === \App\Models\HrDutyRoster::STATUS_DRAFT ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">
                                {{ $entry->dutyRoster?->status === \App\Models\HrDutyRoster::STATUS_DRAFT ? 'Draft' : 'Published' }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-gray-700">{{ $entry->shiftType?->code ?: 'Shift' }} - {{ $entry->shiftType?->name ?: 'Scheduled Shift' }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ $entry->dutyRoster?->organizationalUnit?->name ?: 'Client Space' }}</p>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @else
        <div class="mt-5 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center">
            <p class="text-sm font-medium text-gray-700">No rostered shifts are assigned to you yet.</p>
            <p class="mt-1 text-sm text-gray-500">Draft and published shifts assigned to you will appear here.</p>
        </div>
        @endif
    </div>
    @endif

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-2 lg:grid-cols-5">
        <!-- Total Staff -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Staff (Main System)</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['total_staff']) }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Assigned Staff -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Active Staff</p>
                    <p class="text-2xl font-bold text-green-600 mt-1">{{ number_format($stats['assigned_staff']) }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Pending Routing -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Pending Routing</p>
                    <p class="text-2xl font-bold text-yellow-600 mt-1">{{ number_format($stats['pending_routing']) }}</p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Orphaned Staff -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Orphaned Staff</p>
                    <p class="text-2xl font-bold text-red-600 mt-1">{{ number_format($stats['orphaned_staff']) }}</p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Client Spaces -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Client Spaces</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['client_spaces']) }}</p>
                    @if($stats['unattached_client_spaces'] > 0)
                        <p class="text-xs text-red-600 mt-1">{{ number_format($stats['unattached_client_spaces']) }} need placement</p>
                    @endif
                </div>
                <div class="w-12 h-12 {{ $stats['unattached_client_spaces'] > 0 ? 'bg-red-100' : 'bg-green-100' }} rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 {{ $stats['unattached_client_spaces'] > 0 ? 'text-red-600' : 'text-green-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2h-1M5 11V9a2 2 0 012-2h1m2-3h4a2 2 0 012 2v1H8V6a2 2 0 012-2z"/>
                    </svg>
                </div>
            </div>
        </div>

    </div>

    <!-- Quick Links -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @if($user?->canViewHrStaff())
        <a href="{{ route('hr.staff-assignments.index') }}" class="block bg-white rounded-lg shadow-sm border border-gray-300 p-5 hover:border-blue-500 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2 transition-colors group">
            <h3 class="font-semibold text-gray-900 group-hover:text-blue-700">Staff Assignments</h3>
            <p class="text-sm text-gray-500 mt-1">View and manage staff assignments synced from KashTre.</p>
        </a>
        @endif
        @if(($user?->canViewHrStaff() ?? false) || ($user?->canViewHrSetup() ?? false))
        <a href="{{ route('hr.client-spaces.index') }}" class="block bg-white rounded-lg shadow-sm border border-gray-300 p-5 hover:border-blue-500 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2 transition-colors group">
            <h3 class="font-semibold text-gray-900 group-hover:text-blue-700">Client Spaces</h3>
            <p class="text-sm text-gray-500 mt-1">Review imported client spaces and placement status.</p>
        </a>
        @endif
        @if($user?->canViewHrSetup())
        <a href="{{ route('hr.approval-workflows.index') }}" class="block bg-white rounded-lg shadow-sm border border-gray-300 p-5 hover:border-blue-500 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2 transition-colors group">
            <h3 class="font-semibold text-gray-900 group-hover:text-blue-700">Approval Workflows</h3>
            <p class="text-sm text-gray-500 mt-1">Configure primary, secondary, and tertiary approvers for leave, roster, and coverage.</p>
        </a>
        @endif
        <a href="{{ route('hr.approval-requests.index') }}" class="block bg-white rounded-lg shadow-sm border border-gray-300 p-5 hover:border-blue-500 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2 transition-colors group">
            <h3 class="font-semibold text-gray-900 group-hover:text-blue-700">Approval Requests</h3>
            <p class="text-sm text-gray-500 mt-1">Review pending HR requests and move them through each approval level.</p>
        </a>
        @if($user?->staff_uuid)
        <a href="{{ route('hr.my-roster.index') }}" class="block bg-white rounded-lg shadow-sm border border-gray-300 p-5 hover:border-blue-500 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2 transition-colors group">
            <h3 class="font-semibold text-gray-900 group-hover:text-blue-700">My Roster</h3>
            <p class="text-sm text-gray-500 mt-1">Open your own draft and published shift schedule by date and client space.</p>
        </a>
        @endif
        @if($user?->staff_uuid || $user?->canViewHrStaff() || $user?->canViewHrSetup())
        <a href="{{ route('hr.leave-applications.index') }}" class="block bg-white rounded-lg shadow-sm border border-gray-300 p-5 hover:border-blue-500 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2 transition-colors group">
            <h3 class="font-semibold text-gray-900 group-hover:text-blue-700">Leave Applications</h3>
            <p class="text-sm text-gray-500 mt-1">Submit leave, track approval progress, and review approved or pending dates.</p>
        </a>
        @endif
        @if($user?->canViewHrSetup())
        <a href="{{ route('hr.shift-types.index') }}" class="block bg-white rounded-lg shadow-sm border border-gray-300 p-5 hover:border-blue-500 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2 transition-colors group">
            <h3 class="font-semibold text-gray-900 group-hover:text-blue-700">Shift Types</h3>
            <p class="text-sm text-gray-500 mt-1">Configure shift patterns: day, night, extended, and custom shifts.</p>
        </a>
        @endif
    </div>
</x-hr-layout>
