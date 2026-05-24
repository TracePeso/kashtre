<div wire:poll.15s="loadRequests">
    @php
        $categoryLabels = ['leave' => 'Leave', 'roster' => 'Roster', 'coverage' => 'Coverage', 'offsite_duty' => 'Official Workshop/Meeting'];
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
        $pageTitle = $leaveOnly
            ? 'Leave Applications'
            : ($canViewAllApprovals ? 'Approval Queue' : 'My Approval Queue');
        $pageDescription = $leaveOnly
            ? 'Submit leave applications, track approval progress, and review approved or pending leave dates.'
            : ($canViewAllApprovals
                ? 'Submit HR requests and review every approval stage.'
                : 'Requests appear here when they are waiting for your approval or when you submitted them.');
        $createButtonLabel = $leaveOnly ? 'Apply for Leave' : 'New Request';
        $emptyHeading = $leaveOnly ? 'No leave applications yet.' : 'No approval requests yet.';
        $emptyDescription = $leaveOnly
            ? 'Submit a leave application to start the approval workflow.'
            : 'New requests will appear here as soon as they are submitted.';
        $modalHeading = $leaveOnly ? 'New Leave Application' : 'New Approval Request';
        $submitButtonLabel = $leaveOnly ? 'Submit Leave Application' : 'Submit';
        $workflowWarning = $leaveOnly
            ? 'Configure an active leave approval workflow with approvers before staff can submit leave applications.'
            : 'Configure at least one active approval workflow with approvers before submitting requests.';
        $staffLinkWarning = $leaveOnly
            ? 'Your user account is not linked to a staff UUID yet, so you cannot submit leave applications until that link is added.'
            : 'Your user account is not linked to a staff UUID yet, so approver-specific requests cannot be routed to you.';
    @endphp

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">{{ $pageTitle }}</h2>
            <p class="text-sm text-gray-500">{{ $pageDescription }}</p>
        </div>
        <button wire:click="openCreateModal" class="inline-flex items-center justify-center px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors" @if(empty($workflowCategories)) disabled @endif>
            {{ $createButtonLabel }}
        </button>
    </div>

    @if($message)
    <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-lg px-4 py-3 mb-4 text-sm">
        {{ $message }}
    </div>
    @endif

    @if(empty($workflowCategories))
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-4">
        <p class="text-sm text-yellow-800">{{ $workflowWarning }}</p>
    </div>
    @endif

    @if(!$canManageAllApprovals && !$currentStaffUuid)
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-4">
        <p class="text-sm text-yellow-800">{{ $staffLinkWarning }}</p>
    </div>
    @endif

    @if($leaveOnly)
        @if($individualLeaveSummary)
        <section class="mb-6 overflow-hidden rounded-xl border border-amber-300 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-amber-300 bg-amber-50 px-4 py-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Individual Leave View</h3>
                    <p class="mt-1 text-sm text-gray-600">Personal leave register for {{ $individualLeaveSummary['staff_name'] }}.</p>
                </div>
                @if(count($leaveSummaryAssignmentOptions) > 1)
                <div class="w-full max-w-sm">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Assignment</label>
                    <select wire:model.live="selectedLeaveSummaryAssignmentId" class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-blue focus:ring-brand-blue text-sm">
                        @foreach($leaveSummaryAssignmentOptions as $assignmentId => $assignmentLabel)
                        <option value="{{ $assignmentId }}">{{ $assignmentLabel }}</option>
                        @endforeach
                    </select>
                </div>
                @else
                <p class="text-sm font-medium text-gray-700">{{ $individualLeaveSummary['assignment_label'] }}</p>
                @endif
            </div>

            <div class="overflow-x-auto bg-stone-100/40 p-3">
                <table class="min-w-full border-collapse overflow-hidden rounded-lg">
                    <thead>
                        <tr class="bg-amber-500 text-white">
                            <th class="border border-amber-300 px-3 py-3 text-left text-sm font-semibold">Leave Type</th>
                            <th class="border border-amber-300 px-3 py-3 text-center text-sm font-semibold">Leave Code</th>
                            <th class="border border-amber-300 px-3 py-3 text-center text-sm font-semibold">Leave Start Date</th>
                            <th class="border border-amber-300 px-3 py-3 text-center text-sm font-semibold">Leave End Date</th>
                            <th class="border border-amber-300 px-3 py-3 text-center text-sm font-semibold">No of Leave Days Entitled</th>
                            <th class="border border-amber-300 px-3 py-3 text-center text-sm font-semibold">No of Leave days Requested</th>
                            <th class="border border-amber-300 px-3 py-3 text-center text-sm font-semibold">Balance Leave Days</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        @foreach($individualLeaveSummary['rows'] as $row)
                        <tr>
                            <td class="border border-stone-300 px-3 py-3 text-sm font-semibold {{ $row['leave_type_classes']['cell'] }} {{ $row['leave_type_classes']['text'] }}">
                                {{ $row['leave_type'] }}
                            </td>
                            <td class="border border-stone-300 px-3 py-3 text-center text-sm text-gray-700">{{ $row['code'] }}</td>
                            <td class="border border-stone-300 px-3 py-3 text-center text-sm text-gray-700">{{ $row['start_date'] }}</td>
                            <td class="border border-stone-300 px-3 py-3 text-center text-sm text-gray-700">{{ $row['end_date'] }}</td>
                            <td class="border border-stone-300 px-3 py-3 text-center text-sm text-gray-700">{{ $row['entitled_days'] }}</td>
                            <td class="border border-stone-300 px-3 py-3 text-center text-sm text-gray-700">{{ $row['requested_days'] }}</td>
                            <td class="border border-stone-300 px-3 py-3 text-center text-sm text-gray-700">{{ $row['balance_days'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        @elseif($currentStaffUuid)
        <div class="mb-6 rounded-xl border border-dashed border-gray-300 bg-white px-5 py-4">
            <p class="text-sm font-medium text-gray-700">Individual Leave View</p>
            <p class="mt-1 text-sm text-gray-500">No active staff assignment is linked to this account yet, so the personal leave register cannot be shown.</p>
        </div>
        @endif
    @endif

    @if(empty($requests))
    <div class="bg-white border border-gray-200 rounded-xl p-8 text-center shadow-sm">
        <p class="text-gray-700 font-medium">{{ $emptyHeading }}</p>
        <p class="text-sm text-gray-500 mt-1">{{ $emptyDescription }}</p>
    </div>
    @else
    <div class="space-y-4">
        @foreach($requests as $request)
        @php
            $statusClass = $statusClasses[$request['status']] ?? 'bg-gray-100 text-gray-800';
            $currentSteps = collect($request['steps'])->filter(fn ($step) => $step['status'] === 'pending' && ($step['is_current'] ?? false))->values();
            $currentStep = $currentSteps->first() ?? collect($request['steps'])->firstWhere('status', 'pending');
            $currentApproverNames = $currentSteps->pluck('approver_name')->filter()->unique()->values();
            $waitingLabel = $currentApproverNames->isNotEmpty()
                ? $currentApproverNames->join(', ', ' or ')
                : ($currentStep['approver_name'] ?? 'the next approver');
            $canAct = $currentStep && ($canApproveAnyRequest || $currentSteps->contains(fn ($step) => $step['approver_staff_uuid'] === $currentStaffUuid));
        @endphp
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                <div>
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $statusClass }}">{{ ucfirst($request['status']) }}</span>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">{{ $categoryLabels[$request['approval_category']] ?? $request['approval_category'] }}</span>
                        @if($request['current_level'])
                        <span class="text-xs font-medium text-gray-500 uppercase">Current: {{ $request['current_level'] }}</span>
                        @endif
                    </div>
                    <h3 class="text-base font-semibold text-gray-900">{{ $request['subject'] }}</h3>
                    <p class="text-sm text-gray-500 mt-1">Requested by {{ $request['requester_name'] }}</p>
                    @if(in_array($request['approval_category'], ['leave', 'offsite_duty'], true))
                    <div class="mt-3 flex flex-wrap gap-2 text-xs text-gray-600">
                        @if($request['approval_category'] === 'leave' && !empty($request['leave_type']['name']))
                        <span class="rounded-full bg-blue-50 px-2.5 py-1 font-medium text-blue-700">{{ $request['leave_type']['name'] }}</span>
                        @endif
                        @if(!empty($request['start_date']) && !empty($request['end_date']))
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-700">
                            {{ \Illuminate\Support\Carbon::parse($request['start_date'])->format('M j, Y') }} to {{ \Illuminate\Support\Carbon::parse($request['end_date'])->format('M j, Y') }}
                        </span>
                        @endif
                        @if($request['approval_category'] === 'leave' && !empty($request['requested_days']))
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-700">{{ rtrim(rtrim(number_format((float) $request['requested_days'], 2), '0'), '.') }} day(s)</span>
                        @endif
                        @if(!empty($request['staff_assignment']))
                        <span class="rounded-full bg-amber-50 px-2.5 py-1 font-medium text-amber-700">
                            {{ $request['staff_assignment']['staff_title'] ?: 'Assignment' }}
                            @if(!empty($request['staff_assignment']['organizational_unit']['name']))
                                / {{ $request['staff_assignment']['organizational_unit']['name'] }}
                            @endif
                        </span>
                        @endif
                        @if($request['approval_category'] === 'offsite_duty')
                        <span class="rounded-full bg-cyan-50 px-2.5 py-1 font-medium text-cyan-700">Roster blocked when approved</span>
                        <span class="rounded-full bg-sky-50 px-2.5 py-1 font-medium text-sky-700">Office network + geofence bypass when approved</span>
                        @endif
                    </div>
                    @endif
                    @if($request['details'])
                    <p class="text-sm text-gray-700 mt-3">{{ $request['details'] }}</p>
                    @endif
                </div>

                @if($request['status'] === 'pending' && $currentStep && $canAct)
                <div class="w-full lg:w-80">
                    <p class="text-xs font-medium text-gray-500 uppercase mb-1">Waiting on {{ $waitingLabel }}</p>
                    <textarea wire:model="approvalComments.{{ $request['id'] }}" rows="2" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-blue focus:ring-brand-blue text-sm" placeholder="Optional note"></textarea>
                    <div class="flex gap-2 mt-2">
                        <button wire:click="approveRequest({{ $request['id'] }})" class="flex-1 px-3 py-2 text-sm font-medium text-white bg-green-700 rounded-lg hover:bg-green-800">Approve</button>
                        <button wire:click="rejectRequest({{ $request['id'] }})" wire:confirm="Reject this request?" class="flex-1 px-3 py-2 text-sm font-medium text-white bg-red-700 rounded-lg hover:bg-red-800">Reject</button>
                    </div>
                </div>
                @elseif($request['status'] === 'pending' && $currentStep)
                <div class="w-full lg:w-80 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                    <p class="text-xs font-medium text-gray-500 uppercase">Waiting on {{ $waitingLabel }}</p>
                    <p class="text-sm text-gray-600 mt-1">Approval actions appear only for the current approver or users with Edit HR Approvals.</p>
                </div>
                @endif
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-5">
                <div>
                    <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Approval Steps</h4>
                    <div class="space-y-2">
                        @foreach($request['steps'] as $step)
                        @php $stepClass = $stepClasses[$step['status']] ?? 'bg-gray-50 text-gray-700 border-gray-200'; @endphp
                        <div class="flex items-center justify-between border rounded-lg px-3 py-2 {{ $stepClass }}">
                            <div>
                                <p class="text-sm font-medium">{{ ucfirst($step['approver_level']) }}: {{ $step['approver_name'] }}</p>
                                @if($step['comments'])
                                <p class="text-xs mt-0.5">{{ $step['comments'] }}</p>
                                @endif
                            </div>
                            <span class="text-xs font-semibold uppercase">{{ $step['status'] }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div>
                    <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Movement History</h4>
                    <div class="space-y-2">
                        @foreach($request['events'] as $event)
                        <div class="border border-gray-200 rounded-lg px-3 py-2">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-medium text-gray-900">{{ ucfirst($event['action']) }}</p>
                                <p class="text-xs text-gray-500">{{ \Illuminate\Support\Carbon::parse($event['created_at'])->format('M j, H:i') }}</p>
                            </div>
                            @if($event['actor_name'])
                            <p class="text-xs text-gray-500 mt-0.5">By {{ $event['actor_name'] }}</p>
                            @endif
                            @if($event['comments'])
                            <p class="text-xs text-gray-700 mt-1">{{ $event['comments'] }}</p>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    @if($showCreateModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="fixed inset-0 bg-gray-900/50" wire:click="$set('showCreateModal', false)"></div>
        <div class="bg-white rounded-xl shadow-xl w-full max-w-xl mx-4 relative z-10">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">{{ $modalHeading }}</h3>
            </div>
            <form
                wire:submit.prevent="submitRequest"
                class="p-6 space-y-4"
                x-data="leaveRequestWorkingDays({
                    category: $wire.entangle('category').live,
                    leaveTypeId: $wire.entangle('leaveTypeId').live,
                    startDate: $wire.entangle('leaveStartDate').live,
                    endDate: $wire.entangle('leaveEndDate').live,
                    requestedDays: $wire.entangle('requestedDays').live,
                    weekendDays: @js($leaveWorkingDayPreview['weekendDays'] ?? [0, 6]),
                    holidayDates: @js($leaveWorkingDayPreview['holidayDates'] ?? []),
                    recurringHolidayTokens: @js($leaveWorkingDayPreview['recurringHolidayTokens'] ?? []),
                    leaveTypeConfig: @js($leaveTypeClientConfig),
                })"
                x-init="init()"
            >
                @if(!$leaveOnly)
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select wire:model.live="category" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-blue focus:ring-brand-blue text-sm">
                        @foreach($workflowCategories as $workflowCategory)
                        <option value="{{ $workflowCategory }}">{{ $categoryLabels[$workflowCategory] ?? $workflowCategory }}</option>
                        @endforeach
                    </select>
                    @error('category') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Requester</label>
                    @if($category === 'leave')
                    <input type="text" value="{{ $requesterName ?: 'Your staff account' }}" class="w-full rounded-lg border-gray-300 bg-gray-50 shadow-sm text-sm" readonly>
                    <p class="mt-1 text-xs text-gray-500">Leave applications are always submitted for the logged-in account owner.</p>
                    @elseif(!$canManageAllApprovals && $currentStaffUuid)
                    <input type="text" value="{{ $requesterName ?: 'Your staff account' }}" class="w-full rounded-lg border-gray-300 bg-gray-50 shadow-sm text-sm" readonly>
                    @else
                    <select wire:model.live="requesterUuid" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-blue focus:ring-brand-blue text-sm">
                        <option value="">Select staff</option>
                        @foreach($staffOptions as $uuid => $name)
                        <option value="{{ $uuid }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @endif
                    @if(empty($staffOptions))
                    <p class="text-xs text-yellow-700 mt-1">No staff records were returned by the API or local assignments.</p>
                    @endif
                    @error('requesterUuid') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                @if(in_array($category, ['leave', 'offsite_duty'], true))
                <div class="rounded-lg border border-blue-100 bg-blue-50/60 p-4 space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900">{{ $category === 'leave' ? 'Leave Details' : 'Official Workshop/Meeting Details' }}</h4>
                            <p class="mt-1 text-xs text-gray-600">
                                {{ $category === 'leave'
                                    ? 'This request will create a linked roster unavailability record for the selected assignment.'
                                    : 'When approved, this request blocks the staff member from the normal roster and allows biometric attendance outside the office network and geofence.' }}
                            </p>
                        </div>
                    </div>

                    @if($category === 'leave')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Leave Type</label>
                        <select wire:model="leaveTypeId" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-blue focus:ring-brand-blue text-sm">
                            <option value="">Select leave type</option>
                            @foreach($leaveTypeOptions as $leaveTypeId => $leaveTypeLabel)
                            <option value="{{ $leaveTypeId }}">{{ $leaveTypeLabel }}</option>
                            @endforeach
                        </select>
                        @if(empty($leaveTypeOptions))
                        <p class="text-xs text-yellow-700 mt-1">Configure active leave types first.</p>
                        @endif
                        @error('leaveTypeId') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    @if($selectedLeaveTypeSummary)
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <div class="rounded-lg border border-blue-200 bg-white px-3 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Leave Code</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900">{{ $selectedLeaveTypeSummary['code'] }}</p>
                        </div>
                        <div class="rounded-lg border border-blue-200 bg-white px-3 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Session</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900">{{ $selectedLeaveTypeSummary['session_label'] }}</p>
                        </div>
                        <div class="rounded-lg border border-blue-200 bg-white px-3 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Entitled Days</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900">
                                {{ $selectedLeaveTypeSummary['tracks_balance']
                                    ? ($selectedLeaveTypeSummary['entitled_days'] ?? 'No yearly cap')
                                    : 'Not balance tracked' }}
                            </p>
                        </div>
                        <div class="rounded-lg border border-blue-200 bg-white px-3 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Balance Days</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900">
                                {{ $selectedLeaveTypeSummary['tracks_balance']
                                    ? ($selectedLeaveTypeSummary['remaining_days'] ?? 'Select dates')
                                    : 'Not applicable' }}
                            </p>
                        </div>
                        <div class="rounded-lg border border-blue-200 bg-white px-3 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Deduction Rule</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900">{{ $selectedLeaveTypeSummary['deduction_label'] }}</p>
                        </div>
                        <div class="rounded-lg border border-blue-200 bg-white px-3 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Paid Status</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900">{{ $selectedLeaveTypeSummary['is_paid'] ? 'Paid leave' : 'Unpaid leave' }}</p>
                        </div>
                        <div class="rounded-lg border border-blue-200 bg-white px-3 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Requested Days</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900">{{ $selectedLeaveTypeSummary['requested_days'] ?? 'Select dates' }}</p>
                        </div>
                    </div>
                    @endif
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Staff Assignment</label>
                        <select wire:model="staffAssignmentId" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-blue focus:ring-brand-blue text-sm">
                            <option value="">Select assignment</option>
                            @foreach($requesterAssignmentOptions as $assignmentId => $assignmentLabel)
                            <option value="{{ $assignmentId }}">{{ $assignmentLabel }}</option>
                            @endforeach
                        </select>
                        @if(empty($requesterAssignmentOptions))
                        <p class="text-xs text-yellow-700 mt-1">The requester must already have an active HR staff assignment in this organization.</p>
                        @endif
                        @error('staffAssignmentId') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    @if($category === 'leave')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Client Space</label>
                        <select wire:model="leaveClientSpaceId" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-blue focus:ring-brand-blue text-sm">
                            <option value="">Select client space</option>
                            @foreach($leaveClientSpaceOptions as $clientSpaceId => $clientSpaceName)
                            <option value="{{ $clientSpaceId }}">{{ $clientSpaceName }}</option>
                            @endforeach
                        </select>
                        @if(empty($leaveClientSpaceOptions))
                        <p class="text-xs text-yellow-700 mt-1">The selected assignment must belong to or be linked to at least one client space before leave can be submitted.</p>
                        @else
                        <p class="text-xs text-gray-500 mt-1">Leave approval is routed through the selected client space.</p>
                        @endif
                        @error('leaveClientSpaceId') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    @endif

                    <div class="grid grid-cols-1 {{ $category === 'leave' ? 'md:grid-cols-3' : 'md:grid-cols-2' }} gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input type="date" x-model="startDate" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-blue focus:ring-brand-blue text-sm">
                            @error('leaveStartDate') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                            <input type="date" x-model="endDate" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-blue focus:ring-brand-blue text-sm">
                            @error('leaveEndDate') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        @if($category === 'leave')
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Requested Leave Days</label>
                            <input type="number" min="0.25" step="0.25" x-model="requestedDays" class="w-full rounded-lg border-gray-300 bg-gray-50 shadow-sm focus:border-brand-blue focus:ring-brand-blue text-sm" readonly>
                            <p class="mt-1 text-xs text-gray-500">Updates from working days, approved public holidays, and the selected leave type deduction setting.</p>
                            @error('requestedDays') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        @endif
                    </div>
                </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                    <input type="text" wire:model="subject" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-blue focus:ring-brand-blue text-sm" placeholder="{{ $category === 'leave' ? 'e.g. Annual leave for April roster' : ($category === 'offsite_duty' ? 'e.g. Official Workshop/Meeting - Kampala' : 'Add a short request subject') }}" required>
                    @error('subject') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Details</label>
                    <textarea wire:model="details" rows="4" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-blue focus:ring-brand-blue text-sm" placeholder="{{ $category === 'leave' ? 'Add handover notes, coverage context, or any leave comments.' : 'Add dates, shift details, coverage notes, or off-site duty context.' }}"></textarea>
                    @error('details') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" wire:click="$set('showCreateModal', false)" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-sm text-white bg-gray-900 rounded-lg hover:bg-gray-800">{{ $submitButtonLabel }}</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <script>
        function leaveRequestWorkingDays(config) {
            return {
                category: config.category,
                leaveTypeId: config.leaveTypeId,
                startDate: config.startDate,
                endDate: config.endDate,
                requestedDays: config.requestedDays,
                weekendDays: Array.isArray(config.weekendDays) ? config.weekendDays.map(Number) : [0, 6],
                holidayDates: new Set(Array.isArray(config.holidayDates) ? config.holidayDates : []),
                recurringHolidayTokens: new Set(Array.isArray(config.recurringHolidayTokens) ? config.recurringHolidayTokens : []),
                leaveTypeConfig: config.leaveTypeConfig || {},

                init() {
                    this.syncRequestedDays();
                    this.$watch('category', () => this.syncRequestedDays());
                    this.$watch('leaveTypeId', () => this.syncRequestedDays());
                    this.$watch('startDate', () => this.syncRequestedDays());
                    this.$watch('endDate', () => this.syncRequestedDays());
                },

                syncRequestedDays() {
                    if (this.category !== 'leave') {
                        this.requestedDays = null;
                        return;
                    }

                    const start = this.parseDate(this.startDate);
                    const end = this.parseDate(this.endDate);

                    if (!start || !end || end < start) {
                        this.requestedDays = null;
                        return;
                    }

                    let count = 0;

                    for (let cursor = new Date(start.getTime()); cursor <= end; cursor = this.addDay(cursor)) {
                        if (!this.isWeekend(cursor) && !this.isHoliday(cursor)) {
                            count += 1;
                        }
                    }

                    const leaveTypeSettings = this.leaveTypeConfig[this.leaveTypeId] || null;
                    const factor = Number(leaveTypeSettings?.daysPerWorkday ?? 1);
                    const requested = Math.round(count * (Number.isFinite(factor) && factor > 0 ? factor : 1) * 100) / 100;

                    this.requestedDays = String(requested).replace(/\.0$/, '');
                },

                parseDate(value) {
                    if (!value || typeof value !== 'string') {
                        return null;
                    }

                    const parts = value.split('-').map(Number);

                    if (parts.length !== 3 || parts.some(Number.isNaN)) {
                        return null;
                    }

                    return new Date(Date.UTC(parts[0], parts[1] - 1, parts[2]));
                },

                addDay(date) {
                    const next = new Date(date.getTime());
                    next.setUTCDate(next.getUTCDate() + 1);

                    return next;
                },

                isWeekend(date) {
                    return this.weekendDays.includes(date.getUTCDay());
                },

                isHoliday(date) {
                    const isoDate = this.toIsoDate(date);

                    if (this.holidayDates.has(isoDate)) {
                        return true;
                    }

                    return this.recurringHolidayTokens.has(isoDate.slice(5));
                },

                toIsoDate(date) {
                    const year = date.getUTCFullYear();
                    const month = String(date.getUTCMonth() + 1).padStart(2, '0');
                    const day = String(date.getUTCDate()).padStart(2, '0');

                    return `${year}-${month}-${day}`;
                },
            };
        }
    </script>
</div>
