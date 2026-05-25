<div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-2xl font-semibold text-gray-900">HR Calendar</h2>
            <p class="mt-1 text-sm text-gray-500">Public holidays and organisation dates. Click any day to add a holiday.</p>
        </div>
        @if($canAddCalendarEvents || $canEditCalendarEvents)
            <div class="flex gap-2">
                @if($canEditCalendarEvents)
                    <button type="button" wire:click="seedDefaultHolidays" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        Load defaults
                    </button>
                @endif
                @if($canAddCalendarEvents)
                    <button type="button" wire:click="openCreateModal" class="inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                        Add Holiday
                    </button>
                @endif
            </div>
        @endif
    </div>

    @if(session()->has('message'))
        <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('message') }}
        </div>
    @endif

    {{-- Weekend day picker --}}
    <div class="mb-5 rounded-md border border-gray-200 bg-white px-5 py-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Weekend Days</h3>
                <p class="text-xs text-gray-500">Days marked as weekend will be shaded on the calendar and excluded from rosters.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @php
                    $dayLabels = [0 => 'Sun', 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat'];
                @endphp
                @foreach($dayLabels as $dayNum => $dayLabel)
                    @php $isWeekend = in_array($dayNum, $weekendDays); @endphp
                    <button
                        type="button"
                        wire:click="toggleWeekendDay({{ $dayNum }})"
                        @class([
                            'rounded-md px-3 py-1.5 text-sm font-medium border transition-colors',
                            'bg-gray-900 text-white border-gray-900' => $isWeekend,
                            'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' => !$isWeekend,
                            'opacity-50 cursor-not-allowed' => !$canEditCalendarEvents,
                        ])
                        @unless($canEditCalendarEvents) disabled @endunless
                    >
                        {{ $dayLabel }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-[1fr_24rem]">

        {{-- FullCalendar (wire:ignore keeps Livewire from touching this div) --}}
        <div class="rounded-md border border-gray-200 bg-white p-4"
             wire:ignore
             x-data="hrCalendar({
                 googleApiKey: '{{ $googleApiKey }}',
                 googleCalendarId: '{{ $googleCalendarId }}',
                 eventsUrl: '{{ $eventsUrl }}',
                 weekendDays: {{ json_encode($weekendDays) }},
                 canAdd: {{ $canAddCalendarEvents ? 'true' : 'false' }},
                 canEdit: {{ $canEditCalendarEvents ? 'true' : 'false' }},
             })"
             x-init="init()"
             @calendar-updated.window="calendar && calendar.refetchEvents()"
             @weekend-days-updated.window="onWeekendsUpdated($event.detail.days)">
            <div x-ref="el"></div>
        </div>

        {{-- Configured holidays sidebar --}}
        <div class="rounded-md border border-gray-200 bg-white">
            <div class="border-b border-gray-200 px-5 py-4">
                <h3 class="text-base font-semibold text-gray-900">Public Holidays</h3>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $holidayCount }} active {{ Str::plural('holiday', $holidayCount) }} configured.
                </p>
                @if(($pendingHolidayCount ?? 0) > 0)
                    <p class="mt-1 text-xs font-medium text-amber-700">
                        {{ $pendingHolidayCount }} pending {{ Str::plural('approval', $pendingHolidayCount) }}.
                    </p>
                @endif
            </div>

            @if($googleCalendarId)
                <div class="border-b border-gray-200 bg-blue-50 px-5 py-3">
                    <p class="text-xs font-medium text-blue-800">
                        Google public holidays are shown in
                        <span class="inline-block h-3 w-3 rounded-full bg-red-500 align-middle"></span>
                        red on the calendar. Holidays you add here appear in their own colour.
                    </p>
                </div>
            @elseif(!$googleApiKey)
                <div class="border-b border-gray-200 bg-amber-50 px-5 py-3">
                    <p class="text-xs font-medium text-amber-800">
                        Set <code class="font-mono">GOOGLE_CALENDAR_API_KEY</code> and <code class="font-mono">GOOGLE_HOLIDAY_CALENDAR_ID</code> in your <code class="font-mono">.env</code> to load public holidays from Google Calendar automatically.
                    </p>
                </div>
            @endif

            @if($holidays->isEmpty())
                <div class="px-5 py-6">
                    <p class="text-sm font-medium text-gray-900">No holidays configured yet</p>
                    <p class="mt-1 text-sm text-gray-500">Add public holidays for HR planning and roster management.</p>
                </div>
            @else
                <div class="max-h-[44rem] divide-y divide-gray-100 overflow-y-auto">
                    @foreach($holidays as $event)
                        <div class="px-5 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="truncate text-sm font-semibold text-gray-900">{{ $event->title }}</p>
                                        @if($event->approval_status === \App\Models\HrCalendarEvent::APPROVAL_PENDING)
                                            <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Pending Approval</span>
                                        @elseif($event->approval_status === \App\Models\HrCalendarEvent::APPROVAL_REJECTED)
                                            <span class="rounded bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-800">Rejected</span>
                                        @else
                                            <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Approved</span>
                                        @endif
                                        @unless($event->is_active)
                                            <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">Inactive</span>
                                        @endunless
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ $event->starts_on->format('M j, Y') }}
                                        @if($event->ends_on)
                                            to {{ $event->ends_on->format('M j, Y') }}
                                        @endif
                                    </p>
                                    @if($event->repeats_yearly)
                                        <span class="mt-1.5 inline-block rounded bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">Repeats yearly</span>
                                    @endif
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                            {{ str_replace('_', ' ', $event->reward_type ?: 'multiplier_pay') }}
                                        </span>
                                        @if($event->blocks_rosters)
                                            <span class="rounded bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700">Roster blocked</span>
                                        @endif
                                    </div>
                                </div>
                                @if($canEditCalendarEvents)
                                    <div class="flex shrink-0 items-center gap-2">
                                        @if($event->approval_status !== \App\Models\HrCalendarEvent::APPROVAL_APPROVED)
                                            <button type="button" wire:click="approveEvent({{ $event->id }})" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">Approve</button>
                                        @endif
                                        @if($event->approval_status === \App\Models\HrCalendarEvent::APPROVAL_PENDING)
                                            <button type="button" wire:click="rejectEvent({{ $event->id }})" class="text-sm font-medium text-amber-700 hover:text-amber-800">Reject</button>
                                        @endif
                                        <button type="button" wire:click="openEditModal({{ $event->id }})" class="text-sm font-medium text-gray-600 hover:text-gray-900">Edit</button>
                                        <button type="button" wire:click="deleteEvent({{ $event->id }})" wire:confirm="Delete this public holiday?" class="text-sm font-medium text-red-700 hover:text-red-800">Delete</button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Add / Edit holiday modal --}}
    @if($showEventModal)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-500 bg-opacity-75 px-4 py-6 sm:items-center">
            <div class="w-full max-w-lg max-h-[calc(100vh-3rem)] overflow-y-auto rounded-md bg-white px-4 pb-4 pt-5 shadow-xl sm:p-6">
                <h3 class="mb-4 text-lg font-medium leading-6 text-gray-900">
                    {{ $editingEventId ? 'Edit Public Holiday' : 'Add Public Holiday' }}
                </h3>
                @if(!$editingEventId && !$canEditCalendarEvents)
                    <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                        Ad-hoc holidays you add here stay pending until an HR setup editor approves them.
                    </div>
                @endif

                <form wire:submit.prevent="saveEvent">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Name</label>
                            <input type="text" wire:model="title" placeholder="e.g. Independence Day" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            @error('title') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Start Date</label>
                            <input type="date" wire:model="startsOn" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            @error('startsOn') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">End Date <span class="text-gray-400">(optional)</span></label>
                            <input type="date" wire:model="endsOn" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            @error('endsOn') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Notes <span class="text-gray-400">(optional)</span></label>
                            <textarea wire:model="description" rows="2" placeholder="Optional details" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"></textarea>
                            @error('description') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Reward Type</label>
                            <select wire:model="rewardType" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                <option value="multiplier_pay">Multiplier Pay</option>
                                <option value="leave_day">+1 Leave Day</option>
                                <option value="none">None</option>
                            </select>
                            <p class="mt-2 text-xs text-gray-500">Compensatory leave credit behavior for leave-day public holidays is governed from HR Policies and follows the active policy version.</p>
                            @error('rewardType') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="repeatsYearly" class="rounded border-gray-300 text-gray-900 shadow-sm focus:ring-gray-900">
                            Repeats yearly
                        </label>
                        <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="isActive" class="rounded border-gray-300 text-gray-900 shadow-sm focus:ring-gray-900">
                            Active
                        </label>
                        <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 sm:col-span-2">
                            <input type="checkbox" wire:model="blocksRosters" class="rounded border-gray-300 text-gray-900 shadow-sm focus:ring-gray-900">
                            Block roster assignments on this holiday
                        </label>
                    </div>

                    <div class="mt-5 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="inline-flex w-full justify-center rounded-md border border-transparent bg-gray-900 px-4 py-2 text-base font-medium text-white hover:bg-gray-800 sm:ml-3 sm:w-auto sm:text-sm">
                            Save Holiday
                        </button>
                        <button type="button" wire:click="$set('showEventModal', false)" class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script>
function hrCalendar({ googleApiKey, googleCalendarId, eventsUrl, weekendDays, canAdd, canEdit }) {
    return {
        calendar: null,

        buildBusinessHours(weekendDays) {
            const allDays = [0, 1, 2, 3, 4, 5, 6];
            const workingDays = allDays.filter(d => !weekendDays.includes(d));
            if (workingDays.length === 0) return false;
            return { daysOfWeek: workingDays, startTime: '00:00', endTime: '24:00' };
        },

        init() {
            const eventSources = [
                {
                    url: eventsUrl,
                    failure() { console.warn('Could not load custom calendar events.'); },
                },
            ];

            if (googleApiKey && googleCalendarId) {
                eventSources.unshift({
                    googleCalendarId: googleCalendarId,
                    color: '#dc2626',
                    textColor: '#ffffff',
                    failure() { console.warn('Could not load Google Calendar holidays. Check your API key.'); },
                });
            }

            this.calendar = new FullCalendar.Calendar(this.$refs.el, {
                initialView: 'dayGridMonth',
                googleCalendarApiKey: googleApiKey || undefined,
                eventSources,
                firstDay: 1,
                height: 'auto',
                businessHours: this.buildBusinessHours(weekendDays),
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,listMonth',
                },
                buttonText: {
                    today: 'Today',
                    month: 'Month',
                    list: 'List',
                },
                eventClick: (info) => {
                    info.jsEvent.preventDefault();
                    const id = info.event.id;
                    if (canEdit && id && id.startsWith('custom-')) {
                        const parts = id.split('-');
                        const eventId = parseInt(parts[1]);
                        if (eventId) $wire.openEditModal(eventId);
                    }
                },
                dateClick: (info) => {
                    if (canAdd) $wire.openCreateModal(info.dateStr);
                },
                eventDidMount(info) {
                    if (info.event.extendedProps.repeatsYearly) {
                        info.el.title = info.event.title + ' (repeats yearly)';
                    }
                },
            });

            this.calendar.render();
        },

        onWeekendsUpdated(days) {
            if (!this.calendar) return;
            this.calendar.setOption('businessHours', this.buildBusinessHours(days));
        },
    };
}
</script>
@endpush
