<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <div class="flex items-center justify-between mb-4">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Bedside Scratchpad</h4>
        @if ($aiAvailable)
            <span class="text-[10px] px-2 py-0.5 rounded bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300">AI Gateway Available</span>
        @else
            <span class="text-[10px] px-2 py-0.5 rounded bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">AI Gateway Unavailable — Manual Entry Only</span>
        @endif
    </div>

    @if ($resultMessage)
        <div class="mb-3 text-xs rounded p-2 bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-200">{{ $resultMessage }}</div>
    @endif

    @if ($aiError)
        <div class="mb-3 text-xs rounded p-2 bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300">{{ $aiError }}</div>
    @endif

    <textarea wire:model="scratchpadText" rows="4" placeholder="Rough bedside notes&hellip;"
        class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600"></textarea>
    @error('scratchpadText') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror

    <div class="mt-2 flex gap-2">
        <button wire:click="saveAsNote" class="text-sm text-white bg-gray-600 hover:bg-gray-700 rounded px-4 py-2">
            Save as Note
        </button>
        @if ($aiAvailable)
            <button wire:click="extractWithAi" class="text-sm text-white bg-purple-600 hover:bg-purple-700 rounded px-4 py-2">
                Extract Observations with AI
            </button>
        @endif
    </div>

    @if (count($proposedObservations))
        <div class="mt-4 border-t border-gray-100 dark:border-gray-700 pt-4">
            <h5 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">
                AI-Proposed Observations — Review Required
            </h5>
            @foreach ($proposedObservations as $index => $observation)
                <div wire:key="proposed-{{ $index }}" class="flex items-center justify-between text-sm py-1.5 border-b border-gray-50 dark:border-gray-700">
                    <span class="text-gray-900 dark:text-gray-100">
                        {{ $observation['display_label'] ?? $observation['cde_code'] }}: {{ $observation['value'] }} {{ $observation['unit'] ?? '' }}
                        @if (($observation['cde_resolved'] ?? true) === false || ($observation['unit_resolved'] ?? true) === false)
                            {{-- An unmapped code or unit cannot be normalised, and charting an
                                 unconvertible number is worse than not charting it. --}}
                            <span class="ml-1 text-xs text-amber-600 dark:text-amber-400">unmapped — chart manually</span>
                        @endif
                    </span>
                    <div class="flex gap-2">
                        <button wire:click="commitObservation({{ $index }})"
                                @disabled(($observation['cde_resolved'] ?? true) === false || ($observation['unit_resolved'] ?? true) === false)
                                class="text-xs text-white bg-green-600 hover:bg-green-700 rounded px-2 py-1 disabled:opacity-40 disabled:cursor-not-allowed">
                            Validate &amp; Commit
                        </button>
                        <button wire:click="rejectObservation({{ $index }})" class="text-xs text-gray-500 hover:underline">
                            Discard
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($recentNotes->isNotEmpty())
        <div class="mt-4 border-t border-gray-100 dark:border-gray-700 pt-4">
            <h5 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Recent Notes</h5>
            @foreach ($recentNotes as $note)
                <div wire:key="note-{{ $note->id }}" class="text-xs text-gray-600 dark:text-gray-300 py-1">
                    {{ $note->captured_at }} — {{ $note->captured_value_text }}
                </div>
            @endforeach
        </div>
    @endif
</div>
