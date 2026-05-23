<div class="space-y-6">
    @if ($message)
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ $message }}
        </div>
    @endif

    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Locum & Coverage Board</h2>
                <p class="text-sm text-slate-500">
                    {{ $canManageCoverage
                        ? 'Review open shifts, assign qualified cover, and manage staff bids.'
                        : 'Bid for shifts where your active cadre matches the client-space requirement.' }}
                </p>
            </div>
            <div class="rounded-2xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700">
                {{ $openShifts->count() }} open shift{{ $openShifts->count() === 1 ? '' : 's' }}
            </div>
        </div>
    </section>

    @forelse ($openShifts as $openShift)
        @php
            $myAssignments = $myEligibleAssignmentsByShift[$openShift->id] ?? collect();
            $eligibleAssignments = $eligibleAssignmentsByShift[$openShift->id] ?? collect();
            $myBid = $currentStaffUuid
                ? $openShift->bids
                    ->first(fn ($bid) => $bid->staffAssignment?->staff_uuid === $currentStaffUuid && $bid->status === \App\Models\HrOpenShiftBid::STATUS_PENDING)
                : null;
        @endphp

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-800">
                            {{ \Illuminate\Support\Str::headline(str_replace('_', ' ', $openShift->source_type)) }}
                        </span>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700">
                            {{ $openShift->roster_date->format('D, M j, Y') }}
                        </span>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">
                            {{ $openShift->clientSpace?->name ?? 'Client Space' }}
                            <span class="text-slate-400">/</span>
                            {{ $openShift->shiftType?->name ?? 'Shift' }}
                        </h3>
                        <p class="text-sm text-slate-500">
                            {{ $openShift->discipline_label ?: 'Matching cadre required' }}
                            @if ($openShift->dutyRoster?->name)
                                <span class="mx-1 text-slate-300">•</span>
                                {{ $openShift->dutyRoster->name }}
                            @endif
                        </p>
                    </div>
                    @if ($openShift->source_reason)
                        <p class="text-sm text-slate-600">{{ $openShift->source_reason }}</p>
                    @endif
                </div>

                <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    <div><span class="font-medium text-slate-800">Required slots:</span> {{ $openShift->expected_headcount }}</div>
                    @if ($openShift->sourceStaffAssignment?->staff_name)
                        <div class="mt-1"><span class="font-medium text-slate-800">Removed staff:</span> {{ $openShift->sourceStaffAssignment->staff_name }}</div>
                    @endif
                </div>
            </div>

            @if ($canManageCoverage)
                <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                    <div class="space-y-3">
                        <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Direct Assignment</h4>
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <div class="sm:flex-1">
                                <select wire:model="assignmentSelections.{{ $openShift->id }}" class="w-full rounded-2xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Select qualified staff...</option>
                                    @foreach ($eligibleAssignments as $assignment)
                                        <option value="{{ $assignment->id }}">
                                            {{ $assignment->staff_name }}
                                            @if ($assignment->staff_title)
                                                / {{ $assignment->staff_title }}
                                            @endif
                                            @if ($assignment->organizationalUnit?->name)
                                                / {{ $assignment->organizationalUnit->name }}
                                            @endif
                                            @if (($assignment->locum_scope ?? 'local') === 'cross_branch')
                                                / Cross-branch
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('assignmentSelections.'.$openShift->id) <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <button wire:click="assignShift({{ $openShift->id }})" type="button" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                                Assign Cover
                            </button>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Pending Bids</h4>
                        <div class="space-y-3">
                            @forelse ($openShift->bids->where('status', \App\Models\HrOpenShiftBid::STATUS_PENDING) as $bid)
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <p class="font-medium text-slate-900">{{ $bid->staffAssignment?->staff_name ?? 'Staff' }}</p>
                                            <p class="text-sm text-slate-500">
                                                {{ $bid->staffAssignment?->staff_title ?: $bid->staffAssignment?->staff_cadre ?: 'Qualified staff' }}
                                                @if ($bid->staffAssignment?->organizationalUnit?->name)
                                                    <span class="mx-1 text-slate-300">•</span>
                                                    {{ $bid->staffAssignment->organizationalUnit->name }}
                                                @endif
                                            </p>
                                            @if ($bid->notes)
                                                <p class="mt-2 text-sm text-slate-600">{{ $bid->notes }}</p>
                                            @endif
                                        </div>
                                        <div class="flex gap-2">
                                            <button wire:click="acceptBid({{ $bid->id }})" type="button" class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-500">
                                                Accept
                                            </button>
                                            <button wire:click="rejectBid({{ $bid->id }})" type="button" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                                                Reject
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-2xl border border-dashed border-slate-300 px-4 py-5 text-sm text-slate-500">
                                    No pending bids yet.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @else
                <div class="mt-6 rounded-3xl border border-slate-200 bg-slate-50 p-5">
                    @if ($myBid)
                        <p class="text-sm font-medium text-emerald-700">You have already submitted a bid for this shift.</p>
                    @else
                        <div class="space-y-3">
                            @if ($myAssignments->count() > 1)
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-slate-700">Bid as</label>
                                    <select wire:model="staffBidAssignmentSelections.{{ $openShift->id }}" class="w-full rounded-2xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="">Select your assignment...</option>
                                        @foreach ($myAssignments as $assignment)
                                            <option value="{{ $assignment->id }}">
                                                {{ $assignment->staff_name }}
                                                @if ($assignment->staff_title)
                                                    / {{ $assignment->staff_title }}
                                                @endif
                                                @if ($assignment->organizationalUnit?->name)
                                                    / {{ $assignment->organizationalUnit->name }}
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('staffBidAssignmentSelections.'.$openShift->id) <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror
                                </div>
                            @endif

                            <div>
                                <label class="mb-2 block text-sm font-medium text-slate-700">Note</label>
                                <textarea wire:model="bidNotes.{{ $openShift->id }}" rows="2" class="w-full rounded-2xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Add a short note for the manager (optional)."></textarea>
                            </div>

                            <button wire:click="submitBid({{ $openShift->id }})" type="button" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                                Submit Bid
                            </button>
                        </div>
                    @endif
                </div>
            @endif
        </section>
    @empty
        <section class="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500 shadow-sm">
            No open shifts are available right now.
        </section>
    @endforelse
</div>
