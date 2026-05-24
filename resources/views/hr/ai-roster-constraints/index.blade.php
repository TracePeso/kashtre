<x-hr-layout>
    <x-slot name="header">AI Duty Roster Constraints</x-slot>

    <div class="space-y-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Live Prompt Preview</p>
                    <h2 class="mt-2 text-2xl font-bold text-slate-900">Constraints passed to AI roster generation</h2>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">
                        This page shows the real chunked Gemini prompt payload for a selected roster, including policy limits, fairness rules, scheduling requirements, shift options, eligible staff constraints, and reroll avoidance data.
                    </p>
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                    {{ $organization?->name ?: 'No organization selected' }}
                </span>
            </div>
        </section>

        @if(! $organization)
            <section class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-6 text-sm text-slate-600">
                Select an HR organization first, then return to this page.
            </section>
        @else
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <form method="GET" action="{{ route('hr.ai-roster-constraints.index') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end">
                    <div>
                        <label for="client_space" class="block text-sm font-medium text-slate-700">Client space</label>
                        <select id="client_space" name="client_space" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            @foreach($clientSpaces as $clientSpace)
                                <option value="{{ $clientSpace->id }}" @selected((int) $selectedClientSpaceId === (int) $clientSpace->id)>
                                    {{ $clientSpace->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="roster" class="block text-sm font-medium text-slate-700">Roster</label>
                        <select id="roster" name="roster" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            @forelse($rosters as $roster)
                                <option value="{{ $roster->id }}" @selected($selectedRoster && (int) $selectedRoster->id === (int) $roster->id)>
                                    {{ $roster->name }} | {{ $roster->organizationalUnit?->name }} | {{ $roster->start_date?->toDateString() }} to {{ $roster->end_date?->toDateString() }}
                                </option>
                            @empty
                                <option value="">No rosters found</option>
                            @endforelse
                        </select>
                    </div>

                    <button type="submit" class="inline-flex items-center justify-center rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        Load Constraints
                    </button>
                </form>

                @if($rosters->isEmpty())
                    <p class="mt-4 text-sm text-slate-500">
                        No accessible rosters were found for the selected client space. Create a roster first, then return here.
                    </p>
                @endif
            </section>

            @if($previewError)
                <section class="rounded-3xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900">
                    {{ $previewError }}
                </section>
            @endif

            @if($selectedRoster && $preview)
                <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Roster</p>
                        <p class="mt-2 text-lg font-semibold text-slate-900">{{ $selectedRoster->name }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $selectedRoster->organizationalUnit?->name }}</p>
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Titles</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ implode(', ', $selectedRoster->disciplineTitles()) }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $selectedRoster->start_date?->toDateString() }} to {{ $selectedRoster->end_date?->toDateString() }}</p>
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Prompt Shape</p>
                        <p class="mt-2 text-lg font-semibold text-slate-900">{{ $preview['generation_mode'] }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $preview['chunk_count'] }} chunk(s)</p>
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Coverage Inputs</p>
                        <p class="mt-2 text-lg font-semibold text-slate-900">{{ $preview['eligible_staff_count'] }} staff</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $preview['shift_type_count'] }} active rosterable shift(s)</p>
                    </div>
                </section>

                @foreach($preview['chunks'] as $chunk)
                    @php($payload = $chunk['payload'])
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-500">Chunk {{ $chunk['chunk_index'] }}</p>
                                <h3 class="mt-2 text-xl font-bold text-slate-900">{{ $chunk['chunk_start'] }} to {{ $chunk['chunk_end'] }}</h3>
                                <p class="mt-2 text-sm text-slate-600">
                                    Variation seed: <span class="font-mono text-slate-800">{{ $payload['variation_seed'] }}</span>
                                </p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                <p>{{ count($payload['dates'] ?? []) }} workday date(s)</p>
                                <p>{{ count($payload['existing_assignments'] ?? []) }} current assignment(s)</p>
                                <p>{{ count($payload['previous_assignments_to_avoid'] ?? []) }} previous assignment(s) to avoid</p>
                            </div>
                        </div>

                        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
                            <div class="space-y-6">
                                <div>
                                    <h4 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-500">Instructions</h4>
                                    <ul class="mt-3 space-y-2 text-sm text-slate-700">
                                        @foreach($payload['instructions'] ?? [] as $instruction)
                                            <li class="rounded-2xl bg-slate-50 px-4 py-3">{{ $instruction }}</li>
                                        @endforeach
                                    </ul>
                                </div>

                                <div>
                                    <h4 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-500">Scheduling Requirements</h4>
                                    <ul class="mt-3 space-y-2 text-sm text-slate-700">
                                        @foreach($payload['scheduling_requirements'] ?? [] as $requirement)
                                            <li class="rounded-2xl bg-slate-50 px-4 py-3">{{ $requirement }}</li>
                                        @endforeach
                                    </ul>
                                </div>

                                <div>
                                    <h4 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-500">Fairness Goals</h4>
                                    <div class="mt-3 space-y-2">
                                        @foreach(($payload['fairness_goals'] ?? []) as $goal => $description)
                                            <div class="rounded-2xl bg-slate-50 px-4 py-3">
                                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">{{ str_replace('_', ' ', $goal) }}</p>
                                                <p class="mt-1 text-sm text-slate-700">{{ $description }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-6">
                                <div>
                                    <h4 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-500">Policy Limits</h4>
                                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        @foreach(($payload['policy'] ?? []) as $metric => $value)
                                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">{{ str_replace('_', ' ', $metric) }}</p>
                                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $value }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div>
                                    <h4 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-500">Roster Context</h4>
                                    <div class="mt-3 rounded-2xl bg-slate-50 p-4 text-sm text-slate-700">
                                        <p><span class="font-semibold text-slate-900">Discipline:</span> {{ $payload['roster']['discipline'] ?? 'N/A' }}</p>
                                        <p class="mt-2"><span class="font-semibold text-slate-900">Weekend days:</span> {{ implode(', ', $payload['roster']['weekend_days'] ?? []) }}</p>
                                        <p class="mt-2"><span class="font-semibold text-slate-900">Teams:</span> {{ implode(', ', $payload['roster']['teams'] ?? []) ?: 'None' }}</p>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-500">Shift Types</h4>
                                    <div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200">
                                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">
                                                <tr>
                                                    <th class="px-4 py-3">ID</th>
                                                    <th class="px-4 py-3">Shift</th>
                                                    <th class="px-4 py-3">Time</th>
                                                    <th class="px-4 py-3">Net Minutes</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 bg-white text-slate-700">
                                                @foreach($payload['shift_types'] ?? [] as $shiftType)
                                                    <tr>
                                                        <td class="px-4 py-3 font-mono text-xs">{{ $shiftType['id'] }}</td>
                                                        <td class="px-4 py-3">{{ trim(($shiftType['code'] ? $shiftType['code'].' - ' : '').$shiftType['name']) }}</td>
                                                        <td class="px-4 py-3">{{ $shiftType['start_time'] }} to {{ $shiftType['end_time'] }}</td>
                                                        <td class="px-4 py-3">{{ $shiftType['net_minutes'] }}{{ !empty($shiftType['is_night_shift']) ? ' | Night' : '' }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <h4 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-500">Eligible Staff Constraints</h4>
                            <div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200">
                                <table class="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">
                                        <tr>
                                            <th class="px-4 py-3">Staff ID</th>
                                            <th class="px-4 py-3">Title</th>
                                            <th class="px-4 py-3">Assignment</th>
                                            <th class="px-4 py-3">Team</th>
                                            <th class="px-4 py-3">Rostering Profile</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white text-slate-700">
                                        @foreach($payload['eligible_staff'] ?? [] as $staff)
                                            <tr>
                                                <td class="px-4 py-3 font-mono text-xs">{{ $staff['id'] }}</td>
                                                <td class="px-4 py-3">{{ $staff['title'] ?: 'Title not set' }}</td>
                                                <td class="px-4 py-3">{{ $staff['assignment_type'] ?: 'primary' }}</td>
                                                <td class="px-4 py-3">{{ $staff['team'] ?: 'No team' }}</td>
                                                <td class="px-4 py-3">{{ $staff['rostering_profile'] ?: 'Dynamic rotation with no custom shift constraints.' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="mt-6">
                            <h4 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-500">Raw Prompt Payload</h4>
                            <pre class="mt-3 overflow-x-auto rounded-2xl bg-slate-950 p-4 text-xs leading-6 text-slate-100">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    </section>
                @endforeach
            @endif
        @endif
    </div>
</x-hr-layout>
