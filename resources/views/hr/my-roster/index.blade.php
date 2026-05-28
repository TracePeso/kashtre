<x-hr-layout>
    <x-slot name="header">My Roster</x-slot>

    @php($user = Auth::user())

    @if(! $user?->staff_uuid)
    <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-5">
        <h2 class="text-lg font-semibold text-yellow-900">No linked staff account</h2>
        <p class="mt-1 text-sm text-yellow-800">Your login is not linked to a staff UUID yet, so no personal roster can be shown.</p>
    </div>
    @else
    <div class="space-y-5">
        <div class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">{{ $monthStart->format('F Y') }}</h2>
                <p class="mt-1 text-sm text-gray-500">Calendar-based individual roster with one row per date and separate client-space and shift-detail columns.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('hr.my-roster.index', ['month' => $monthStart->copy()->subMonth()->format('Y-m')]) }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>
                <a href="{{ route('hr.my-roster.index', ['month' => $monthStart->copy()->addMonth()->format('Y-m')]) }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>
            </div>
        </div>

        @if($clientSpaceLegend->isNotEmpty() || $shiftLegend->isNotEmpty())
        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            @if($clientSpaceLegend->isNotEmpty())
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-900">Client Space Key</h3>
                <p class="mt-1 text-sm text-gray-500">Each client space keeps the same colour throughout the roster.</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach($clientSpaceLegend as $legend)
                    <span
                        class="inline-flex items-center rounded-full border px-3 py-1 text-sm font-medium"
                        style="background-color: {{ $legend['tone']['background'] }}; border-color: {{ $legend['tone']['border'] }}; color: {{ $legend['tone']['text'] }};"
                    >
                        {{ $legend['name'] }}
                    </span>
                    @endforeach
                </div>
            </section>
            @endif

            @if($shiftLegend->isNotEmpty())
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-900">Shift Key</h3>
                <p class="mt-1 text-sm text-gray-500">Shift colours help you scan the month quickly across different client spaces.</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach($shiftLegend as $legend)
                    <span
                        class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm font-medium"
                        style="background-color: {{ $legend['tone']['background'] }}; border-color: {{ $legend['tone']['border'] }}; color: {{ $legend['tone']['text'] }};"
                    >
                        <span>{{ $legend['code'] }}</span>
                        <span class="opacity-80">{{ $legend['name'] }}</span>
                        <span class="opacity-70">{{ $legend['time'] }}</span>
                    </span>
                    @endforeach
                </div>
            </section>
            @endif
        </div>
        @endif

        @if($dateRows->every(fn ($row) => $row['assignments'] === []))
        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center">
            <p class="text-sm font-medium text-gray-700">No roster entries for {{ $monthStart->format('F Y') }}.</p>
            <p class="mt-1 text-sm text-gray-500">Your assigned client-space shifts for this month will appear in the calendar register here.</p>
        </div>
        @else
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="border-b border-gray-200 px-4 py-3 text-left font-semibold text-gray-900">Date</th>
                            <th class="border-b border-gray-200 px-4 py-3 text-left font-semibold text-gray-900">Client Space</th>
                            <th class="border-b border-gray-200 px-4 py-3 text-left font-semibold text-gray-900">Shift Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($dateRows as $row)
                        @php($date = $row['date'])
                        <tr class="{{ $row['is_today'] ? 'bg-blue-50/60' : ($row['is_weekend'] ? 'bg-gray-50/70' : 'bg-white') }}">
                            <td class="border-b border-gray-200 px-4 py-4 align-top">
                                <div class="flex flex-col">
                                    <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ $date->format('D') }}</span>
                                    <span class="mt-1 text-base font-semibold text-gray-900">{{ $date->format('j M Y') }}</span>
                                    @if($row['is_today'])
                                    <span class="mt-2 inline-flex w-fit items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700">Today</span>
                                    @elseif($row['is_weekend'])
                                    <span class="mt-2 inline-flex w-fit items-center rounded-full bg-gray-200 px-2 py-0.5 text-xs font-semibold text-gray-700">Weekend</span>
                                    @endif
                                </div>
                            </td>
                            <td class="border-b border-gray-200 px-4 py-4 align-top">
                                @if($row['assignments'] === [])
                                <span class="text-sm text-gray-400">No client space assigned</span>
                                @else
                                <div class="space-y-2">
                                    @foreach($row['assignments'] as $assignment)
                                    <span
                                        class="inline-flex items-center rounded-full border px-3 py-1 text-sm font-medium"
                                        style="background-color: {{ $assignment['client_space_tone']['background'] }}; border-color: {{ $assignment['client_space_tone']['border'] }}; color: {{ $assignment['client_space_tone']['text'] }};"
                                    >
                                        {{ $assignment['client_space_name'] }}
                                    </span>
                                    @endforeach
                                </div>
                                @endif
                            </td>
                            <td class="border-b border-gray-200 px-4 py-4 align-top">
                                @if($row['assignments'] === [])
                                <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-3 py-3 text-sm text-gray-500">
                                    No shift scheduled for this date.
                                </div>
                                @else
                                <div class="space-y-3">
                                    @foreach($row['assignments'] as $assignment)
                                    <div class="rounded-lg border border-gray-200 bg-white px-3 py-3 shadow-sm">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span
                                                class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold"
                                                style="background-color: {{ $assignment['shift_tone']['background'] }}; border-color: {{ $assignment['shift_tone']['border'] }}; color: {{ $assignment['shift_tone']['text'] }};"
                                            >
                                                {{ $assignment['shift_code'] }}
                                            </span>
                                            <span class="text-sm font-semibold text-gray-900">{{ $assignment['shift_name'] }}</span>
                                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">{{ $assignment['roster_status'] }}</span>
                                        </div>
                                        <p class="mt-2 text-sm text-gray-600">{{ $assignment['shift_time'] }}</p>
                                    </div>
                                    @endforeach
                                </div>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
    @endif
</x-hr-layout>
