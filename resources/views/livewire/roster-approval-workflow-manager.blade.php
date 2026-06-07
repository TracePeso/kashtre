<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Roster Approval Rules</h3>
            <p class="mt-1 text-sm text-gray-500">Assign roster approvers by client space. Rosters resolve their approval chain automatically from the selected client space, with an optional all-client-spaces fallback rule. Each level needs at least 3 approvers, and any current-level approver can act.</p>
            @unless($canDesignateRosterApprovers)
                <p class="mt-2 text-xs font-medium text-amber-700">Only users with `Designate HR Roster Approvers` can assign or change primary, secondary, and tertiary roster approvers.</p>
            @endunless
        </div>
        @if($organizationId && $canDesignateRosterApprovers)
            <button wire:click="openCreateModal()" class="inline-flex items-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Roster Rule
            </button>
        @endif
    </div>

    @if($message)
        <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            {{ $message }}
        </div>
    @endif

    @if(empty($rules))
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-6 text-center">
            <p class="font-medium text-gray-700">No roster approval rules configured yet.</p>
            <p class="mt-2 text-sm text-gray-500">Add a client-space rule first, or create one all-client-spaces fallback rule for shared approval chains.</p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            @foreach($rules as $rule)
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-800">Roster</span>
                                @if($rule['is_active'])
                                    <span class="h-2 w-2 rounded-full bg-green-500" title="Active"></span>
                                @endif
                            </div>
                            <p class="mt-3 text-sm font-semibold text-gray-900">{{ $rule['organizational_unit']['name'] ?? 'All Client Spaces' }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ isset($rule['organizational_unit']['name']) ? 'Applies to all rosters in this client space.' : 'Applies to all rosters across client spaces that do not have a specific rule.' }}</p>
                        </div>

                        @if($canDesignateRosterApprovers)
                            <div class="flex gap-1">
                                <button wire:click="openEditModal({{ $rule['id'] }})" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white p-2 text-gray-700 shadow-sm hover:bg-blue-50 hover:text-blue-700" title="Edit rule">
                                    <span class="sr-only">Edit rule</span>
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button wire:click="deleteRule({{ $rule['id'] }})" wire:confirm="Deactivate this roster approval rule?" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white p-2 text-gray-700 shadow-sm hover:bg-red-50 hover:text-red-700" title="Deactivate rule">
                                    <span class="sr-only">Deactivate rule</span>
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        @endif
                    </div>

                    <div class="mt-4 space-y-3">
                        @foreach($rule['approvers'] as $approver)
                            <div class="flex items-center gap-3">
                                <span class="w-20 flex-shrink-0 text-xs font-medium uppercase text-gray-500">{{ $approver['approver_level'] }}</span>
                                <div class="flex items-center gap-2">
                                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-100">
                                        <span class="text-xs font-bold text-blue-800">{{ strtoupper(substr($approver['approver_name'], 0, 1)) }}</span>
                                    </div>
                                    <span class="text-sm text-gray-900">{{ $approver['approver_name'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if($showModal && $canDesignateRosterApprovers)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-gray-900/50" wire:click="$set('showModal', false)"></div>
            <div class="relative flex min-h-full items-start justify-center px-4 py-6 sm:items-center">
                <div class="relative z-10 flex w-full max-w-lg flex-col overflow-hidden rounded-xl bg-white shadow-xl max-h-[calc(100vh-3rem)]">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit' : 'Create' }} Roster Approval Rule</h3>
                </div>
                <form wire:submit.prevent="saveRule" class="flex-1 space-y-4 overflow-y-auto p-6">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Client Space</label>
                        <select wire:model.live="clientSpaceId" class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                            <option value="">All Client Spaces</option>
                            @foreach($clientSpaceOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs text-gray-500">Rosters now pick approvers automatically from the selected client space. Leave this blank only when you want one fallback rule for client spaces without a dedicated setup.</p>
                        @error('clientSpaceId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900">Existing Rosters</h4>
                                <p class="mt-1 text-xs text-gray-500">Rosters already created in the selected client space load here automatically.</p>
                            </div>
                            @if($clientSpaceId)
                                <span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 shadow-sm">{{ count($existingRosters) }}</span>
                            @endif
                        </div>

                        @if(!$clientSpaceId)
                            <p class="mt-3 text-sm text-gray-500">Select a client space to load its rosters.</p>
                        @elseif(empty($existingRosters))
                            <p class="mt-3 text-sm text-gray-500">No rosters exist in this client space yet.</p>
                        @else
                            <div class="mt-3 max-h-52 space-y-2 overflow-y-auto pr-1">
                                @foreach($existingRosters as $roster)
                                    <div class="rounded-md border border-gray-200 bg-white px-3 py-3">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-gray-900">{{ $roster['name'] }}</p>
                                                <p class="mt-1 text-xs text-gray-500">{{ implode(', ', $roster['titles']) }}</p>
                                            </div>
                                            <div class="flex flex-wrap gap-2">
                                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-700">{{ $roster['status'] }}</span>
                                                <span class="rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-blue-700">{{ $roster['approval_status'] }}</span>
                                            </div>
                                        </div>
                                        <p class="mt-2 text-xs text-gray-500">{{ $roster['start_date'] }} to {{ $roster['end_date'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="border-t pt-4">
                        <h4 class="mb-3 text-sm font-semibold text-gray-700">Approvers</h4>
                        <p class="mb-3 text-xs text-gray-500">Primary is always required. Choose how many approval levels this rule should use, then select at least 3 approvers for each enabled level. Any one approver at the active level can approve or reject the request.</p>

                        <div class="mb-4">
                            <label class="mb-1 block text-sm font-medium text-gray-700">Approval Levels</label>
                            <select wire:model.live="approvalLevelCount" class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                                <option value="1">1 Level: Primary only</option>
                                <option value="2">2 Levels: Primary and Secondary</option>
                                <option value="3">3 Levels: Primary, Secondary, and Tertiary</option>
                            </select>
                            @error('approvalLevelCount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-4">
                            @foreach(array_slice(['primary' => 'Primary', 'secondary' => 'Secondary', 'tertiary' => 'Tertiary'], 0, $approvalLevelCount, true) as $level => $label)
                                <div class="rounded-lg border border-gray-200 p-3">
                                    <div class="mb-3 flex items-center justify-between gap-3">
                                        <label class="mb-0 block text-xs font-medium uppercase text-gray-500">{{ $label }} Approvers *</label>
                                        <button type="button" wire:click="addApproverSlot('{{ $level }}')" class="text-xs font-semibold text-gray-600 hover:text-gray-900">
                                            Add Another
                                        </button>
                                    </div>

                                    <div class="space-y-2">
                                        @foreach($approverUuids[$level] ?? [] as $index => $uuid)
                                            <div class="flex items-center gap-2">
                                                <select wire:model.live="approverUuids.{{ $level }}.{{ $index }}" class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue" required>
                                                    <option value="">Select staff</option>
                                                    @foreach($staffOptions as $staffUuid => $name)
                                                        <option value="{{ $staffUuid }}">{{ $name }}</option>
                                                    @endforeach
                                                </select>
                                                @if(count($approverUuids[$level] ?? []) > 3)
                                                    <button type="button" wire:click="removeApproverSlot('{{ $level }}', {{ $index }})" class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                                        Remove
                                                    </button>
                                                @endif
                                            </div>
                                            @error("approverUuids.$level.$index") <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                        @endforeach
                                    </div>

                                    @error("approverUuids.$level") <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="$set('showModal', false)" class="rounded-lg bg-gray-100 px-4 py-2 text-sm text-gray-700 hover:bg-gray-200">Cancel</button>
                        <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm text-white hover:bg-gray-800">{{ $editingId ? 'Update' : 'Create' }}</button>
                    </div>
                </form>
            </div>
            </div>
        </div>
    @endif
</div>
