<div>
    @php
        $categoryLabels = ['leave' => 'Leave', 'coverage' => 'Coverage', 'offsite_duty' => 'Official Workshop/Meeting'];
        $categoryClasses = [
            'leave' => 'bg-blue-100 text-blue-800',
            'coverage' => 'bg-yellow-100 text-yellow-800',
            'offsite_duty' => 'bg-green-100 text-green-800',
        ];
    @endphp

    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <p class="text-sm text-gray-500">Configure primary, secondary, and tertiary approvers for leave, coverage, and off-site duty. Leave workflows are scoped per client space. Staff already based in that client space use the configured chain, while linked routing-node staff use the direct superior of that client space as the primary approver. Each level needs at least 3 approvers, and any current-level approver can act.</p>
        @if($organizationId && $canAddSetup)
        <button wire:click="openCreateModal()" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Workflow
        </button>
        @endif
    </div>

    @if($message)
    <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-lg px-4 py-3 mb-4 text-sm">
        {{ $message }}
    </div>
    @endif

    @if(empty($staffOptions))
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-4">
        <p class="text-sm text-yellow-800">No staff records were returned by the API or local assignments. Add active staff assignments or check the KashTre API before configuring approvers.</p>
    </div>
    @endif

    @if(empty($workflows))
    <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 text-center">
        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p class="text-gray-600 font-medium">No approval workflows configured yet.</p>
    </div>
    @else
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($workflows as $wf)
        @php
            $categoryClass = $categoryClasses[$wf['approval_category']] ?? 'bg-gray-100 text-gray-800';
        @endphp
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $categoryClass }}">
                        {{ $categoryLabels[$wf['approval_category']] ?? $wf['approval_category'] }}
                    </span>
                    @if($wf['is_active'])
                    <span class="w-2 h-2 bg-green-500 rounded-full" title="Active"></span>
                    @endif
                </div>
                @if($canEditSetup)
                <div class="flex gap-1">
                    <button wire:click="openEditModal({{ $wf['id'] }})" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white p-2 text-gray-700 shadow-sm hover:bg-blue-50 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2" title="Edit workflow">
                        <span class="sr-only">Edit workflow</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                    <button wire:click="deleteWorkflow({{ $wf['id'] }})" wire:confirm="Deactivate this workflow?" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white p-2 text-gray-700 shadow-sm hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2" title="Deactivate workflow">
                        <span class="sr-only">Deactivate workflow</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </div>
                @endif
            </div>

            <div class="mb-4 space-y-1">
                @if($wf['approval_category'] === 'leave')
                <p class="text-sm font-semibold text-gray-900">{{ $wf['organizational_unit']['name'] ?? 'Unscoped Client Space' }}</p>
                <p class="text-xs text-gray-500">Scoped to this client space.</p>
                @else
                <p class="text-sm font-semibold text-gray-900">All Client Spaces</p>
                <p class="text-xs text-gray-500">Organization-wide {{ strtolower($categoryLabels[$wf['approval_category']] ?? $wf['approval_category']) }} workflow.</p>
                @endif
            </div>

            <div class="space-y-3">
                @foreach($wf['approvers'] as $approver)
                <div class="flex items-center gap-3">
                    <span class="flex-shrink-0 w-20 text-xs font-medium text-gray-500 uppercase">{{ $approver['approver_level'] }}</span>
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 bg-blue-100 rounded-full flex items-center justify-center">
                            <span class="text-xs font-bold text-blue-800">{{ strtoupper(substr($approver['approver_name'], 0, 1)) }}</span>
                        </div>
                        <span class="text-sm text-gray-900">{{ $approver['approver_name'] }}</span>
                    </div>
                </div>
                @endforeach
                @if(empty($wf['approvers']))
                <p class="text-sm text-gray-400 italic">No approvers configured</p>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif

    <!-- Create/Edit Modal -->
    @if($showModal && ($canAddSetup || $canEditSetup))
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="fixed inset-0 bg-gray-900/50" wire:click="$set('showModal', false)"></div>
        <div class="relative flex min-h-full items-start justify-center px-4 py-6 sm:items-center">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg relative z-10 flex max-h-[calc(100vh-3rem)] flex-col overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit' : 'Create' }} Approval Workflow</h3>
            </div>
            <form wire:submit.prevent="saveWorkflow" class="flex-1 overflow-y-auto p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select wire:model="category" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-blue focus:ring-brand-blue text-sm" {{ $editingId ? 'disabled' : '' }}>
                        @foreach($categoryLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('category') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                @if($category === 'leave')
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Client Space</label>
                    <select wire:model="clientSpaceId" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-blue focus:ring-brand-blue text-sm">
                        <option value="">Select client space</option>
                        @foreach($clientSpaceOptions as $clientSpaceOptionId => $clientSpaceOptionName)
                        <option value="{{ $clientSpaceOptionId }}">{{ $clientSpaceOptionName }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Leave approval workflows are configured one client space at a time.</p>
                    @error('clientSpaceId') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                @endif

                <div class="border-t pt-4">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">Approvers</h4>
                    <p class="mb-3 text-xs text-gray-500">
                        Select at least 3 approvers for each level. Any one approver at the active level can approve or reject the request.
                        @if($category === 'leave')
                        For staff already based in the client space, this primary list is used as configured. For linked routing-node staff, the direct superior of the selected client space becomes the primary approver at submission time.
                        @endif
                    </p>

                    <div class="space-y-4">
                        @foreach(['primary' => 'Primary', 'secondary' => 'Secondary', 'tertiary' => 'Tertiary'] as $level => $label)
                        <div class="rounded-lg border border-gray-200 p-3">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <label class="block text-xs font-medium text-gray-500 uppercase">{{ $label }} Approvers *</label>
                                <button type="button" wire:click="addApproverSlot('{{ $level }}')" class="text-xs font-semibold text-gray-600 hover:text-gray-900">
                                    Add Another
                                </button>
                            </div>
                            <div class="space-y-2">
                                @foreach($approverUuids[$level] ?? [] as $index => $uuid)
                                <div class="flex items-center gap-2">
                                    <select wire:model.live="approverUuids.{{ $level }}.{{ $index }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-blue focus:ring-brand-blue text-sm" required>
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
                    <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-sm text-white bg-gray-900 rounded-lg hover:bg-gray-800">{{ $editingId ? 'Update' : 'Create' }}</button>
                </div>
            </form>
        </div>
        </div>
    </div>
    @endif
</div>
