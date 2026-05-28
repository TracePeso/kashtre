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
                <p class="mt-1 text-sm text-gray-500">Dates run across the top. Each roster box shows colour only: shift on the top-left triangle and client space on the bottom-right triangle.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('hr.my-roster.index', ['month' => $monthStart->copy()->subMonth()->format('Y-m')]) }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>
                <a href="{{ route('hr.my-roster.index', ['month' => $monthStart->copy()->addMonth()->format('Y-m')]) }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,19rem)_minmax(0,1fr)_minmax(0,1fr)]">
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-900">Diagonal Key</h3>
                <div class="mt-4 flex items-center gap-4">
                    <div
                        class="relative h-20 w-20 overflow-hidden rounded-xl border border-gray-300"
                        style="background: linear-gradient(135deg, rgba(37, 99, 235, 0.18) 0 49.5%, rgba(15, 118, 110, 0.18) 50.5% 100%);"
                    >
                        <span class="absolute left-2 top-2 text-[11px] font-semibold text-blue-900">Shift</span>
                        <span class="absolute bottom-2 right-2 text-[11px] font-semibold text-teal-900">Space</span>
                    </div>
                    <div class="space-y-2 text-sm text-gray-600">
                        <p><span class="font-semibold text-gray-900">Top-left triangle:</span> shift colour</p>
                        <p><span class="font-semibold text-gray-900">Bottom-right triangle:</span> client-space colour</p>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-900">Shift Key</h3>
                <p class="mt-1 text-sm text-gray-500">Shift colours used in the diagonal boxes.</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    @forelse($shiftLegend as $legend)
                    <span
                        class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm font-medium"
                        style="background-color: {{ $legend['tone']['background'] }}; border-color: {{ $legend['tone']['border'] }}; color: {{ $legend['tone']['text'] }};"
                    >
                        <span>{{ $legend['code'] }}</span>
                        <span class="opacity-85">{{ $legend['name'] }}</span>
                    </span>
                    @empty
                    <span class="text-sm text-gray-400">No shifts scheduled this month.</span>
                    @endforelse
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-900">Client Space Key</h3>
                <p class="mt-1 text-sm text-gray-500">Client-space colours used in the diagonal boxes.</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    @forelse($clientSpaceLegend as $legend)
                    <span
                        class="inline-flex items-center rounded-full border px-3 py-1 text-sm font-medium"
                        style="background-color: {{ $legend['tone']['background'] }}; border-color: {{ $legend['tone']['border'] }}; color: {{ $legend['tone']['text'] }};"
                    >
                        {{ $legend['name'] }}
                    </span>
                    @empty
                    <span class="text-sm text-gray-400">No client spaces scheduled this month.</span>
                    @endforelse
                </div>
            </section>
        </div>

        @if($scheduleCells->every(fn ($cell) => $cell['assignments'] === []))
        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center">
            <p class="text-sm font-medium text-gray-700">No roster entries for {{ $monthStart->format('F Y') }}.</p>
            <p class="mt-1 text-sm text-gray-500">Your assigned client-space shifts for this month will appear in the calendar here.</p>
        </div>
        @else
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="sticky left-0 z-10 min-w-[8rem] border-b border-r border-gray-200 bg-gray-50 px-4 py-3 text-left font-semibold text-gray-900">Roster</th>
                            @foreach($calendarDays as $day)
                            <th class="min-w-[7.5rem] border-b border-r border-gray-200 px-2 py-3 text-center align-top">
                                <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ $day->format('D') }}</div>
                                <div class="mt-1 text-sm font-semibold text-gray-900">{{ $day->format('j') }}</div>
                            </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th class="sticky left-0 z-10 border-b border-r border-gray-200 bg-white px-4 py-4 text-left align-top">
                                <p class="text-sm font-semibold text-gray-900">Shift / Space</p>
                            </th>
                            @foreach($scheduleCells as $cell)
                            <td class="border-b border-r border-gray-200 px-2 py-3 align-top {{ $cell['is_today'] ? 'bg-blue-50/50' : ($cell['is_weekend'] ? 'bg-gray-50/70' : 'bg-white') }}">
                                <div class="space-y-2">
                                    @forelse($cell['assignments'] as $assignment)
                                    <div
                                        class="relative mx-auto h-20 w-20 overflow-hidden rounded-xl border shadow-sm"
                                        style="background: {{ $assignment['cell_background'] }}; border-color: {{ $assignment['client_space_tone']['border'] }};"
                                        title="{{ $assignment['shift_name'] }} ({{ $assignment['shift_time'] }}) / {{ $assignment['client_space_name'] }} / {{ $assignment['roster_status'] }}"
                                    ></div>
                                    @empty
                                    <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 text-[11px] font-semibold uppercase tracking-wide text-gray-400">
                                        Off
                                    </div>
                                    @endforelse

                                    @if($cell['is_today'])
                                    <span class="block text-center text-[11px] font-semibold uppercase tracking-wide text-blue-700">Today</span>
                                    @elseif($cell['is_weekend'])
                                    <span class="block text-center text-[11px] font-semibold uppercase tracking-wide text-gray-500">Weekend</span>
                                    @endif
                                </div>
                            </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
    @endif
</x-hr-layout>
