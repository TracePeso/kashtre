<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Handover Record</h4>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
        v6.1 Phase 9 — the accountable handover event: who prepared it, who it was sent to, and whether the
        receiver actually accepted it. Distinct from the live ward handover list above — sending is not
        acceptance; only the receiver's own acknowledgement transfers clinical responsibility.
    </p>

    @if ($resultMessage)
        <div class="mb-4 text-xs rounded p-2 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300">{{ $resultMessage }}</div>
    @endif
    @if ($errorMessage)
        <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 text-sm rounded p-3">{{ $errorMessage }}</div>
    @endif

    @if (! $active)
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Intended receiver (user id)</label>
                <input type="text" wire:model="intendedReceiverId" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                @error('intendedReceiverId') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Situation</label>
                <input type="text" wire:model="situation" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                @error('situation') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Plan (optional)</label>
                <input type="text" wire:model="plan" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            </div>
        </div>
        <button wire:click="prepare" class="text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">Prepare handover</button>
    @else
        <div class="border border-gray-200 dark:border-gray-700 rounded p-4">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-medium">
                    Status: <span class="uppercase">{{ $active['status'] ?? '—' }}</span>
                    · Version {{ $active['version_no'] ?? 1 }}
                </span>
                <button wire:click="selectHandover('')" class="text-xs text-gray-500 hover:underline">Start a new handover</button>
            </div>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs mb-4">
                <div><dt class="text-gray-400 uppercase">Situation</dt><dd>{{ $active['content']['situation'] ?? '—' }}</dd></div>
                <div><dt class="text-gray-400 uppercase">Plan</dt><dd>{{ $active['content']['plan'] ?? '—' }}</dd></div>
            </dl>

            <div class="flex flex-wrap items-center gap-2 mb-4">
                @if (($active['status'] ?? null) === 'DRAFT')
                    <button wire:click="send" class="text-xs text-white bg-blue-600 hover:bg-blue-700 rounded px-3 py-1.5">Send</button>
                @endif
                @if (in_array($active['status'] ?? null, ['SENT'], true))
                    <div class="flex items-center gap-2">
                        <input type="text" wire:model="acknowledgeNote" placeholder="Optional note…" class="text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                        <button wire:click="acknowledge" class="text-xs text-white bg-green-600 hover:bg-green-700 rounded px-3 py-1.5">Acknowledge</button>
                    </div>
                @endif
            </div>

            <div class="border-t border-gray-100 dark:border-gray-700 pt-3">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Amend (creates a new version — never edits this one in place)</label>
                <div class="flex items-center gap-2">
                    <input type="text" wire:model="amendSituation" placeholder="Updated situation…" class="flex-1 text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                    <button wire:click="amend" class="text-xs text-white bg-gray-700 hover:bg-gray-800 rounded px-3 py-1.5">Amend</button>
                </div>
                @error('amendSituation') <div class="text-[10px] text-red-600 mt-1">{{ $message }}</div> @enderror
            </div>
        </div>
    @endif
</div>
