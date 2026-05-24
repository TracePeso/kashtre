<x-hr-layout>
    <x-slot name="header">My Roster</x-slot>

    @php($user = Auth::user())

    @if(! $user?->staff_uuid)
    <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-5">
        <h2 class="text-lg font-semibold text-yellow-900">No linked staff account</h2>
        <p class="mt-1 text-sm text-yellow-800">Your login is not linked to a staff UUID yet, so no personal roster can be shown.</p>
    </div>
    @else
    <div class="space-y-4">
        <div class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">{{ $monthStart->format('F Y') }}</h2>
                <p class="mt-1 text-sm text-gray-500">Days run across the top and your client spaces run down the side.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('hr.my-roster.index', ['month' => $monthStart->copy()->subMonth()->format('Y-m')]) }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>
                <a href="{{ route('hr.my-roster.index', ['month' => $monthStart->copy()->addMonth()->format('Y-m')]) }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>
            </div>
        </div>

        @if($clientSpaceRows->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center">
            <p class="text-sm font-medium text-gray-700">No roster entries for {{ $monthStart->format('F Y') }}.</p>
            <p class="mt-1 text-sm text-gray-500">Your assigned client-space shifts for this month will appear in the table here.</p>
        </div>
        @else
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full border-collapse text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="sticky left-0 z-10 border-b border-r border-gray-200 bg-gray-50 px-4 py-3 text-left font-semibold text-gray-900">Client Space</th>
                        @foreach($calendarDays as $day)
                        <th class="min-w-[4.5rem] border-b border-r border-gray-200 px-2 py-3 text-center align-top">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ $day->format('D') }}</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900">{{ $day->format('j') }}</div>
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($clientSpaceRows as $row)
                    <tr>
                        <th class="sticky left-0 z-10 border-b border-r border-gray-200 bg-white px-4 py-3 text-left align-top font-semibold text-gray-900">
                            {{ $row['client_space_name'] }}
                        </th>
                        @foreach($calendarDays as $day)
                            @php($cellEntries = $row['days'][$day->toDateString()] ?? [])
                            <td class="border-b border-r border-gray-200 px-2 py-3 align-top text-center">
                                @if($cellEntries === [])
                                    <span class="text-xs text-gray-300">-</span>
                                @else
                                    <div class="space-y-1">
                                        @foreach($cellEntries as $shift)
                                        <div class="rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700">
                                            {{ $shift['code'] }}
                                        </div>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
    @endif
</x-hr-layout>
