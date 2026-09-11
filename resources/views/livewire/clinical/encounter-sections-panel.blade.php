<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <div class="flex items-center justify-between mb-1">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Encounter Sections (MDT)</h4>
        <label class="flex items-center gap-1.5 text-[10px] text-gray-500 dark:text-gray-400">
            <input type="checkbox" wire:model.live="showWithdrawn" class="rounded border-gray-300">
            Show withdrawn
        </label>
    </div>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Each specialist signs their own section of this encounter — nutrition, nursing, the consultant — concurrently.</p>

    @if ($statusMessage)
        <div class="mb-4 text-xs rounded p-2 bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-200">{{ $statusMessage }}</div>
    @endif

    @if ($errorMessage)
        <div class="mb-4 text-xs rounded p-2 bg-amber-50 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-200">
            {{ $errorMessage }}
        </div>
    @endif

    @if (empty($sections))
        <p class="text-xs text-gray-400 mb-4">No sections signed yet.</p>
    @else
        <div class="space-y-2 mb-4">
            @foreach ($sections as $section)
                <div wire:key="section-{{ $section['id'] }}" class="text-xs border border-gray-100 dark:border-gray-700 rounded p-2 {{ ! empty($section['withdrawn_at']) ? 'opacity-50' : '' }}">
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-gray-800 dark:text-gray-100">{{ $section['section_name'] ?? $section['section_code'] }}</span>
                        <span class="text-gray-400">{{ $section['signed_at'] ?? '' }}</span>
                    </div>
                    <div class="text-gray-500 dark:text-gray-400">
                        {{ $section['signed_by_name'] ?? 'user #'.($section['signed_by_user_id'] ?? '?') }}
                        @if ($section['specialty'] ?? null)
                            &mdash; {{ $section['specialty'] }}
                        @elseif ($section['signed_role_code'] ?? null)
                            &mdash; {{ $section['signed_role_code'] }}
                        @endif
                    </div>
                    @if ($section['attestation_note'] ?? null)
                        <div class="text-gray-600 dark:text-gray-300 mt-1">&ldquo;{{ $section['attestation_note'] }}&rdquo;</div>
                    @endif

                    @if (! empty($section['withdrawn_at']))
                        <div class="text-red-500 mt-1">Withdrawn{{ ($section['withdrawal_reason'] ?? null) ? ': '.$section['withdrawal_reason'] : '' }}</div>
                    @elseif (auth()->id() === ($section['signed_by_user_id'] ?? null))
                        <div class="flex gap-2 mt-1.5">
                            <input type="text" wire:model="withdrawReason" placeholder="Reason to withdraw"
                                class="flex-1 text-[11px] rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                            <button wire:click="withdraw('{{ $section['id'] }}')" class="text-red-600 dark:text-red-400 hover:underline whitespace-nowrap">Withdraw</button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-end pt-3 border-t border-gray-100 dark:border-gray-700">
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Section Code</label>
            <input type="text" wire:model="sectionCode" placeholder="e.g. NUTRITION" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
            @error('sectionCode') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Section Name</label>
            <input type="text" wire:model="sectionName" placeholder="e.g. Nutrition Assessment" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
            @error('sectionName') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Attestation</label>
            <input type="text" wire:model="attestationNote" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
        </div>
    </div>
    <button wire:click="sign" class="mt-2 text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">
        Sign This Section
    </button>
</div>
