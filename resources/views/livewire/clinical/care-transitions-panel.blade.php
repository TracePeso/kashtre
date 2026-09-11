<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Care Transitions</h4>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
        v6.1 Volume 8 — admission/transfer/discharge/referral governance. This does not move a bed or halt an order
        itself; it adds a readiness gate, attested discharge documents, and re-anchors scheduled observations. Use
        the Clinical Process Registry above for the actual bed move or step execution.
    </p>

    @if ($resultMessage)
        <div class="mb-4 text-xs rounded p-2 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300">{{ $resultMessage }}</div>
    @endif

    @if ($errorMessage)
        <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 text-sm rounded p-3">
            {{ $errorMessage }}
        </div>
    @endif

    {{-- Start a transition --}}
    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 mb-4">
        <h5 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-3">Start a Transition</h5>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Type</label>
                <select wire:model="transitionType" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                    <option value="">Select…</option>
                    @foreach ($types as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('transitionType') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Planned At</label>
                <input type="datetime-local" wire:model="plannedAt" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">From Ward (optional)</label>
                <select wire:model="fromWardCode" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                    <option value="">None</option>
                    @foreach ($wards as $ward)
                        <option value="{{ $ward->ward_code }}">{{ $ward->ward_name ?? $ward->ward_code }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">To Ward (optional)</label>
                <select wire:model="toWardCode" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                    <option value="">None</option>
                    @foreach ($wards as $ward)
                        <option value="{{ $ward->ward_code }}">{{ $ward->ward_name ?? $ward->ward_code }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($transitionType === 'REFERRAL')
            <div class="mb-3">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">
                    Destination Organization Id
                    <span class="text-amber-600 dark:text-amber-400 font-normal normal-case">— strongly recommended for a referral</span>
                </label>
                <input type="text" wire:model="destinationOrganizationId" placeholder="No organisation directory exists yet — enter the id directly"
                    class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                @if ($destinationOrganizationId === '')
                    <p class="text-[10px] text-amber-600 dark:text-amber-400 mt-1">
                        Left blank, this referral's projection will carry a null destination — useless downstream. Nothing stops submission, but it is the wrong choice for a real referral.
                    </p>
                @endif
            </div>
        @endif

        <div class="mb-3">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Reason</label>
            <input type="text" wire:model="reason" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
        </div>

        <button wire:click="start" class="text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">
            Start Transition
        </button>
    </div>

    {{-- Recent transitions --}}
    <div class="mb-4">
        <h5 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Recent Care Transitions</h5>
        @if ($recentTransitions->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">None started yet on this device/session history.</p>
        @else
            <ul class="text-sm divide-y divide-gray-100 dark:divide-gray-700">
                @foreach ($recentTransitions as $t)
                    <li wire:key="ct-{{ $t->public_id }}" class="py-1.5 flex items-center justify-between">
                        <button wire:click="selectTransition('{{ $t->public_id }}')"
                            class="text-left {{ $activeTransitionPublicId === $t->public_id ? 'font-semibold text-blue-700 dark:text-blue-300' : 'text-gray-700 dark:text-gray-300 hover:underline' }}">
                            {{ $types[$t->transition_type] ?? $t->transition_type }}
                            <span class="text-xs text-gray-400 font-mono ml-1">{{ $t->public_id }}</span>
                        </button>
                        <span class="text-[10px] px-1.5 py-0.5 rounded uppercase
                            {{ $t->status === 'READY' ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                                : ($t->status === 'READINESS_CHECK' ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'
                                : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400') }}">
                            {{ $t->status }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Active transition detail --}}
    @if ($activeTransition)
        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 mb-4">
            <div class="flex items-center justify-between mb-3">
                <h5 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                    {{ $types[$activeTransition->transitionType] ?? $activeTransition->transitionType }}
                    <span class="font-mono normal-case text-gray-400">{{ $activeTransition->publicId }}</span>
                </h5>
                <span class="text-[10px] px-1.5 py-0.5 rounded uppercase
                    {{ $activeTransition->isReady() ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                        : ($activeTransition->isBlocked() ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'
                        : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400') }}">
                    {{ $activeTransition->status }}
                </span>
            </div>

            {{-- Readiness --}}
            <div class="mb-4">
                <h6 class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Readiness Check</h6>
                @php $items = $activeTransition->latestReadinessItems(); @endphp
                @if (empty($items))
                    <p class="text-xs text-gray-500 dark:text-gray-400">No blocking or warning items — clean pass.</p>
                @else
                    <ul class="text-sm space-y-1">
                        @foreach ($items as $item)
                            <li class="flex items-center gap-2">
                                <span class="text-[10px] px-1.5 py-0.5 rounded uppercase
                                    {{ $item->outcome === 'BLOCK' ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'
                                        : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' }}">
                                    {{ $item->outcome }}
                                </span>
                                <span class="text-gray-900 dark:text-gray-100">{{ $item->label() }}</span>
                                @if (!empty($item->evidence))
                                    <span class="text-xs text-gray-400">({{ collect($item->evidence)->map(fn ($v, $k) => "{$k}: {$v}")->implode(', ') }})</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                <p class="text-[10px] text-gray-400 mt-2">
                    Readiness is evaluated once, at start — there is no recheck action. If something changes on the
                    chart afterward, start a new transition to re-evaluate.
                </p>
            </div>

            {{-- Complete internal transfer --}}
            @if ($activeTransition->isInternalTransfer() && in_array($activeTransition->status, ['READY', 'AUTHORIZED', 'IN_PROGRESS'], true))
                <div class="border-t border-gray-100 dark:border-gray-700 pt-3 mb-3">
                    <h6 class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Complete Internal Transfer</h6>
                    <p class="text-[10px] text-gray-400 mb-2">
                        Does not move the bed — do that first via the Ward Census board or the Clinical Process
                        Registry above, which now shows the bed movement id after a BED_ALLOCATION/BED_CUSTODY_TRANSFER
                        step completes. This just records that it happened.
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Bed Movement Id</label>
                            <input type="text" wire:model="movementId" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                            @error('movementId') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Effective At</label>
                            <input type="datetime-local" wire:model="effectiveAt" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                            @error('effectiveAt') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <button wire:click="completeInternalTransfer" class="text-sm text-white bg-gray-700 hover:bg-gray-800 rounded px-4 py-2">
                        Complete Transfer
                    </button>
                </div>
            @endif

            {{-- Issue discharge document --}}
            @if ($activeTransition->isDischarge() && $activeTransition->status === 'READY')
                <div class="border-t border-gray-100 dark:border-gray-700 pt-3">
                    <h6 class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Issue Discharge Document</h6>
                    <p class="text-[10px] text-gray-400 mb-2">
                        Immutable once attested — there is no correction endpoint. A wrong document means issuing a new one.
                    </p>

                    @if (!empty($sections))
                        <ul class="text-xs mb-2 space-y-1">
                            @foreach ($sections as $index => $section)
                                <li class="flex items-start justify-between gap-2 bg-gray-50 dark:bg-gray-700/50 rounded p-2">
                                    <div>
                                        <span class="font-mono font-medium">{{ $section['code'] }}</span>
                                        <span class="text-gray-500 dark:text-gray-400"> — {{ \Illuminate\Support\Str::limit($section['content'], 80) }}</span>
                                    </div>
                                    <button wire:click="removeSectionRow({{ $index }})" class="text-red-600 hover:underline shrink-0">Remove</button>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mb-2">
                        <input type="text" wire:model="newSectionCode" placeholder="Section code (e.g. HOSPITAL_COURSE)"
                            class="text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                        <input type="text" wire:model="newSectionContent" placeholder="Content"
                            class="sm:col-span-2 text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                    </div>
                    @error('newSectionCode') <div class="text-[10px] text-red-600 mb-2">{{ $message }}</div> @enderror
                    @error('newSectionContent') <div class="text-[10px] text-red-600 mb-2">{{ $message }}</div> @enderror

                    <button wire:click="addSectionRow" class="text-sm text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded px-3 py-1.5 mb-3">
                        Add Section
                    </button>
                    <div>
                        <button wire:click="issueDischargeDocument" class="text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">
                            Issue &amp; Attest
                        </button>
                    </div>

                    @if ($lastDocumentPublicId)
                        <a href="{{ route('clinical.care-transitions.document-pdf', ['document' => $lastDocumentPublicId]) }}" target="_blank"
                            class="inline-block mt-3 text-sm text-blue-700 dark:text-blue-300 hover:underline">
                            Download PDF
                        </a>
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>
