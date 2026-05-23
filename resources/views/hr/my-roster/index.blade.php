<x-app-layout>
    <x-slot name="header">My Roster</x-slot>

    @php($user = Auth::user())

    @if(! $user?->staff_uuid)
    <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-5">
        <h2 class="text-lg font-semibold text-yellow-900">No linked staff account</h2>
        <p class="mt-1 text-sm text-yellow-800">Your login is not linked to a staff UUID yet, so no personal roster can be shown.</p>
    </div>
    @else
    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Month</p>
                <p class="mt-1 text-xl font-semibold text-gray-900">{{ $monthStart->format('F Y') }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Assigned Shifts</p>
                <p class="mt-1 text-xl font-semibold text-gray-900">{{ number_format($entries->count()) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Client Spaces</p>
                <p class="mt-1 text-xl font-semibold text-gray-900">{{ number_format($clientSpaceCount) }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Personal Schedule</h2>
                    <p class="mt-1 text-sm text-gray-500">Roster entries assigned to you for this month, including drafts that have not been published yet.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('hr.my-roster.index', ['month' => $monthStart->copy()->subMonth()->format('Y-m')]) }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>
                    <a href="{{ route('hr.my-roster.index', ['month' => $monthStart->copy()->addMonth()->format('Y-m')]) }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>
                </div>
            </div>

            @if($nextEntry)
            <div class="mt-5 rounded-xl border border-blue-200 bg-blue-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Next Shift</p>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <p class="text-lg font-semibold text-gray-900">{{ $nextEntry->shiftType?->code ?: 'Shift' }} - {{ $nextEntry->shiftType?->name ?: 'Scheduled Shift' }}</p>
                    @php($isDraftNextEntry = $nextEntry->dutyRoster?->status === \App\Models\HrDutyRoster::STATUS_DRAFT)
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $isDraftNextEntry ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">
                        {{ $isDraftNextEntry ? 'Draft' : 'Published' }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-700">
                    {{ $nextEntry->roster_date?->format('D, M j, Y') }}
                    @if($nextEntry->shiftType?->start_time && $nextEntry->shiftType?->end_time)
                        | {{ \Illuminate\Support\Carbon::parse($nextEntry->shiftType->start_time)->format('H:i') }} to {{ \Illuminate\Support\Carbon::parse($nextEntry->shiftType->end_time)->format('H:i') }}
                    @endif
                </p>
                <p class="mt-1 text-sm text-blue-800">{{ $nextEntry->dutyRoster?->organizationalUnit?->name ?: 'Client Space' }}</p>
            </div>
            @endif

            @if($entriesByDate->isEmpty())
            <div class="mt-5 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center">
                <p class="text-sm font-medium text-gray-700">No roster entries for {{ $monthStart->format('F Y') }}.</p>
                <p class="mt-1 text-sm text-gray-500">Your published shifts and any draft schedule assigned to you will appear here.</p>
            </div>
            @else
            <div class="mt-5 space-y-4">
                @foreach($entriesByDate as $date => $dayEntries)
                @php($dayDate = \Illuminate\Support\Carbon::parse($date))
                <section class="rounded-xl border border-gray-200 bg-gray-50/70 p-4">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ $dayDate->format('l, M j, Y') }}</h3>
                            @if($dayDate->isToday())
                            <p class="text-xs font-medium uppercase tracking-wide text-blue-700">Today</p>
                            @endif
                        </div>
                        <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-gray-600">{{ $dayEntries->count() }} shift{{ $dayEntries->count() === 1 ? '' : 's' }}</span>
                    </div>

                    <div class="mt-3 grid gap-3 lg:grid-cols-2">
                        @foreach($dayEntries as $entry)
                        <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-semibold text-gray-900">{{ $entry->shiftType?->code ?: 'Shift' }} - {{ $entry->shiftType?->name ?: 'Scheduled Shift' }}</p>
                                        @php($isDraftEntry = $entry->dutyRoster?->status === \App\Models\HrDutyRoster::STATUS_DRAFT)
                                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $isDraftEntry ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">
                                            {{ $isDraftEntry ? 'Draft' : 'Published' }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-600">{{ $entry->dutyRoster?->organizationalUnit?->name ?: 'Client Space' }}</p>
                                </div>
                                @if($entry->shiftType?->start_time && $entry->shiftType?->end_time)
                                <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
                                    {{ \Illuminate\Support\Carbon::parse($entry->shiftType->start_time)->format('H:i') }} - {{ \Illuminate\Support\Carbon::parse($entry->shiftType->end_time)->format('H:i') }}
                                </span>
                                @endif
                            </div>
                            <p class="mt-3 text-xs text-gray-500">{{ $entry->dutyRoster?->name ?: 'Roster entry' }}</p>
                            @if($entry->notes)
                            <p class="mt-2 text-sm text-gray-700">{{ $entry->notes }}</p>
                            @endif
                        </article>
                        @endforeach
                    </div>
                </section>
                @endforeach
            </div>
            @endif
        </div>
    </div>
    @endif
</x-app-layout>
