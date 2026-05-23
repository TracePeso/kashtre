<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Roster Approval Rules</h3>
            <p class="mt-1 text-sm text-gray-500">Assign different approvers per client space and, when needed, per title. Single-title rosters use title-specific rules first, then fall back to the client-space rule. Each level needs at least 3 approvers, and any current-level approver can act.</p>
        </div>
        @if($organizationId && $canAddSetup)
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
            <p class="mt-2 text-sm text-gray-500">Add a client-space fallback rule first, then add title-specific rules where one roster needs a different approval chain.</p>
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
                            <p class="mt-1 text-xs text-gray-500">{{ $rule['discipline_title'] ?: 'All Titles In This Client Space' }}</p>
                        </div>

                        @if($canEditSetup)
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

    @if($showModal && ($canAddSetup || $canEditSetup))
        <div class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="fixed inset-0 bg-gray-900/50" wire:click="$set('showModal', false)"></div>
            <div class="relative z-10 mx-4 w-full max-w-lg rounded-xl bg-white shadow-xl">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit' : 'Create' }} Roster Approval Rule</h3>
                </div>
                <form wire:submit.prevent="saveRule" class="space-y-4 p-6">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Client Space</label>
                        <select wire:model.live="clientSpaceId" class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                            <option value="">All Client Spaces</option>
                            @foreach($clientSpaceOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('clientSpaceId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Title Scope</label>
                        <select wire:model="disciplineTitle" class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                            <option value="">{{ $clientSpaceId ? 'All Titles In This Client Space' : 'All Titles In All Client Spaces' }}</option>
                            @foreach($disciplineOptions as $disciplineOption)
                                <option value="{{ $disciplineOption }}">{{ $disciplineOption }}</option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs text-gray-500">Choose a title for single-title roster rules. Leave this as all titles to create the fallback rule for the selected scope.</p>
                        @error('disciplineTitle') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="border-t pt-4">
                        <h4 class="mb-3 text-sm font-semibold text-gray-700">Approvers</h4>
                        <p class="mb-3 text-xs text-gray-500">Select at least 3 approvers for each level. Any one approver at the active level can approve or reject the request.</p>

                        <div class="space-y-4">
                            @foreach(['primary' => 'Primary', 'secondary' => 'Secondary', 'tertiary' => 'Tertiary'] as $level => $label)
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
    @endif
</div>
