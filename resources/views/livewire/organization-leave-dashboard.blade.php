<div wire:poll.15s>
    @php
        $statusClasses = [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'approved' => 'bg-green-100 text-green-800',
            'rejected' => 'bg-red-100 text-red-800',
            'cancelled' => 'bg-gray-100 text-gray-800',
        ];
        $stepClasses = [
            'pending' => 'bg-yellow-50 text-yellow-800 border-yellow-200',
            'approved' => 'bg-green-50 text-green-800 border-green-200',
            'rejected' => 'bg-red-50 text-red-800 border-red-200',
            'skipped' => 'bg-gray-50 text-gray-600 border-gray-200',
        ];
    @endphp

    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Organization Leave Dashboard</h2>
            <p class="text-sm text-gray-500">Review every pending or approved leave request across the current organization and act on the ones assigned to you.</p>
        </div>
        <div class="inline-flex rounded-lg border border-gray-200 bg-white p-1 shadow-sm">
            <button
                type="button"
                wire:click="$set('statusFilter', 'pending')"
                class="rounded-md px-3 py-2 text-sm font-medium {{ $statusFilter === 'pending' ? 'bg-amber-100 text-amber-900' : 'text-gray-600 hover:text-gray-900' }}"
            >
                Pending
            </button>
            <button
                type="button"
                wire:click="$set('statusFilter', 'approved')"
                class="rounded-md px-3 py-2 text-sm font-medium {{ $statusFilter === 'approved' ? 'bg-emerald-100 text-emerald-900' : 'text-gray-600 hover:text-gray-900' }}"
            >
                Approved
            </button>
            <button
                type="button"
                wire:click="$set('statusFilter', 'all')"
                class="rounded-md px-3 py-2 text-sm font-medium {{ $statusFilter === 'all' ? 'bg-slate-100 text-slate-900' : 'text-gray-600 hover:text-gray-900' }}"
            >
                All
            </button>
        </div>
    </div>

    @if($message)
    <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
        {{ $message }}
    </div>
    @endif

    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-amber-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-gray-500">Pending Requests</p>
            <p class="mt-2 text-3xl font-bold text-amber-700">{{ number_format($stats['pending_count']) }}</p>
            <p class="mt-1 text-xs text-gray-500">Leaves still moving through approval.</p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-gray-500">Approved Requests</p>
            <p class="mt-2 text-3xl font-bold text-emerald-700">{{ number_format($stats['approved_count']) }}</p>
            <p class="mt-1 text-xs text-gray-500">Approved leaves recorded for this organization.</p>
        </div>
        <div class="rounded-xl border border-sky-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-gray-500">Pending Leave Days</p>
            <p class="mt-2 text-3xl font-bold text-sky-700">{{ $stats['pending_days'] }}</p>
            <p class="mt-1 text-xs text-gray-500">Total requested days awaiting approval.</p>
        </div>
        <div class="rounded-xl border border-rose-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-gray-500">Away Today</p>
            <p class="mt-2 text-3xl font-bold text-rose-700">{{ number_format($stats['away_today_count']) }}</p>
            <p class="mt-1 text-xs text-gray-500">Staff currently out on approved leave.</p>
        </div>
    </div>

    <div class="mt-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_18rem_auto]">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Search</label>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-blue focus:ring-brand-blue text-sm"
                    placeholder="Search by requester, leave type, assignment, client space, or subject"
                >
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Leave Type</label>
                <select wire:model.live="leaveTypeFilter" class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-blue focus:ring-brand-blue text-sm">
                    <option value="">All leave types</option>
                    @foreach($leaveTypeOptions as $leaveTypeId => $leaveTypeLabel)
                    <option value="{{ $leaveTypeId }}">{{ $leaveTypeLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="button" wire:click="clearFilters" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Clear Filters
                </button>
            </div>
        </div>
    </div>

    @if(empty($requests))
    <div class="mt-6 rounded-xl border border-dashed border-gray-300 bg-white px-6 py-10 text-center shadow-sm">
        <p class="text-sm font-medium text-gray-700">No leave requests match the current filters.</p>
        <p class="mt-1 text-sm text-gray-500">Pending and approved leave requests for this organization will appear here.</p>
    </div>
    @else
    <div class="mt-6 space-y-4">
        @foreach($requests as $request)
        @php
            $statusClass = $statusClasses[$request['status']] ?? 'bg-gray-100 text-gray-800';
        @endphp
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ ucfirst($request['status']) }}</span>
                        @if($request['current_level'])
                        <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-800">Current: {{ ucfirst($request['current_level']) }}</span>
                        @endif
                        @if(!empty($request['leave_type']['name']))
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $request['leave_type']['name'] }}</span>
                        @endif
                    </div>

                    <h3 class="mt-3 text-base font-semibold text-gray-900">{{ $request['subject'] }}</h3>
                    <p class="mt-1 text-sm text-gray-600">Requested by {{ $request['requester_name'] }}</p>

                    <div class="mt-3 flex flex-wrap gap-2 text-xs text-gray-600">
                        @if(!empty($request['start_date']) && !empty($request['end_date']))
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-700">
                            {{ \Illuminate\Support\Carbon::parse($request['start_date'])->format('M j, Y') }} to {{ \Illuminate\Support\Carbon::parse($request['end_date'])->format('M j, Y') }}
                        </span>
                        @endif
                        @if(!empty($request['requested_days']))
                        <span class="rounded-full bg-amber-50 px-2.5 py-1 font-medium text-amber-700">{{ $request['requested_days'] }} day(s)</span>
                        @endif
                        @if(!empty($request['staff_assignment']['staff_title']) || !empty($request['staff_assignment']['organizational_unit']['name']))
                        <span class="rounded-full bg-blue-50 px-2.5 py-1 font-medium text-blue-700">
                            {{ $request['staff_assignment']['staff_title'] ?: 'Assignment' }}
                            @if(!empty($request['staff_assignment']['organizational_unit']['name']))
                                / {{ $request['staff_assignment']['organizational_unit']['name'] }}
                            @endif
                        </span>
                        @endif
                        @if(!empty($request['submitted_at']))
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-700">
                            Submitted {{ \Illuminate\Support\Carbon::parse($request['submitted_at'])->format('M j, Y H:i') }}
                        </span>
                        @endif
                    </div>

                    @if($request['details'])
                    <p class="mt-3 text-sm text-gray-700">{{ $request['details'] }}</p>
                    @endif
                </div>

                @if($request['status'] === 'pending' && $request['can_act'])
                <div class="w-full lg:w-80">
                    <p class="mb-1 text-xs font-medium uppercase text-gray-500">Waiting on {{ $request['waiting_label'] }}</p>
                    <textarea wire:model="approvalComments.{{ $request['id'] }}" rows="2" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-blue focus:ring-brand-blue text-sm" placeholder="Optional note"></textarea>
                    <div class="mt-2 flex gap-2">
                        <button wire:click="approveRequest({{ $request['id'] }})" class="flex-1 rounded-lg bg-green-700 px-3 py-2 text-sm font-medium text-white hover:bg-green-800">Approve</button>
                        <button wire:click="rejectRequest({{ $request['id'] }})" wire:confirm="Reject this leave request?" class="flex-1 rounded-lg bg-red-700 px-3 py-2 text-sm font-medium text-white hover:bg-red-800">Reject</button>
                    </div>
                </div>
                @elseif($request['status'] === 'pending')
                <div class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 lg:w-80">
                    <p class="text-xs font-medium uppercase text-gray-500">Waiting on {{ $request['waiting_label'] }}</p>
                    <p class="mt-1 text-sm text-gray-600">Approval actions appear for the assigned approver or users with Edit HR Approvals.</p>
                </div>
                @endif
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                <div>
                    <h4 class="mb-2 text-xs font-semibold uppercase text-gray-500">Approval Steps</h4>
                    <div class="space-y-2">
                        @forelse($request['steps'] as $step)
                        @php($stepClass = $stepClasses[$step['status']] ?? 'bg-gray-50 text-gray-700 border-gray-200')
                        <div class="flex items-center justify-between rounded-lg border px-3 py-2 {{ $stepClass }}">
                            <div>
                                <p class="text-sm font-medium">{{ ucfirst($step['approver_level']) }}: {{ $step['approver_name'] }}</p>
                                @if($step['comments'])
                                <p class="mt-0.5 text-xs">{{ $step['comments'] }}</p>
                                @endif
                            </div>
                            <span class="text-xs font-semibold uppercase">{{ $step['status'] }}</span>
                        </div>
                        @empty
                        <div class="rounded-lg border border-dashed border-gray-300 px-3 py-4 text-sm text-gray-500">
                            No approval steps were recorded for this leave request.
                        </div>
                        @endforelse
                    </div>
                </div>

                <div>
                    <h4 class="mb-2 text-xs font-semibold uppercase text-gray-500">Movement History</h4>
                    <div class="space-y-2">
                        @forelse($request['events'] as $event)
                        <div class="rounded-lg border border-gray-200 px-3 py-2">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-medium text-gray-900">{{ ucfirst($event['action']) }}</p>
                                @if(!empty($event['created_at']))
                                <p class="text-xs text-gray-500">{{ \Illuminate\Support\Carbon::parse($event['created_at'])->format('M j, H:i') }}</p>
                                @endif
                            </div>
                            @if($event['actor_name'])
                            <p class="mt-0.5 text-xs text-gray-500">By {{ $event['actor_name'] }}</p>
                            @endif
                            @if($event['comments'])
                            <p class="mt-1 text-xs text-gray-700">{{ $event['comments'] }}</p>
                            @endif
                        </div>
                        @empty
                        <div class="rounded-lg border border-dashed border-gray-300 px-3 py-4 text-sm text-gray-500">
                            No movement history has been recorded yet.
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
