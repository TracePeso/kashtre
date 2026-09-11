@if (in_array('clinical.break_glass.review', auth()->user()->permissions ?? []))
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Break-Glass Independent Review</h4>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
            v6.1 Volume 9 — a retrospective review of an emergency-access override. Not the override itself; that's
            triggered from the chart-access refusal screen.
        </p>

        @if ($resultMessage)
            <div class="mb-4 text-xs rounded p-2 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300">{{ $resultMessage }}</div>
        @endif

        @if ($errorMessage)
            <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 text-sm rounded p-3">
                {{ $errorMessage }}
            </div>
        @endif

        @if (session('break_glass_episode_id'))
            <p class="text-xs text-amber-600 dark:text-amber-400 mb-3">
                An override was just granted on this chart — episode <span class="font-mono">{{ session('break_glass_episode_id') }}</span>, pre-filled below.
            </p>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Episode Id</label>
                <input type="text" wire:model="episodeId" placeholder="01J..."
                    class="w-full text-sm font-mono rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                @error('episodeId') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Outcome</label>
                <select wire:model="outcome" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                    <option value="">Select…</option>
                    <option value="JUSTIFIED">Justified</option>
                    <option value="UNJUSTIFIED">Unjustified</option>
                    <option value="INCONCLUSIVE">Inconclusive</option>
                </select>
                @error('outcome') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="mb-4">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Finding</label>
            <textarea wire:model="finding" rows="2" placeholder="Reviewed against the resuscitation record; override was warranted."
                class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600"></textarea>
            @error('finding') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>

        <button wire:click="review" class="text-sm text-white bg-gray-700 hover:bg-gray-800 rounded px-4 py-2">
            Record Review
        </button>
    </div>
@endif
