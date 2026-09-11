<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Triage Assessment</h4>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Scores whatever vitals are already charted above — capture them first if a score comes back incomplete.</p>

    @if ($errorMessage)
        <div class="mb-4 text-xs rounded p-2 bg-amber-50 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-200">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="flex gap-2 mb-4">
        <button wire:click="preview" class="text-xs text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded px-3 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-700">
            Preview
        </button>
        <button wire:click="assess" class="text-xs text-white bg-blue-600 hover:bg-blue-700 rounded px-3 py-1.5">
            Assess &amp; Announce to Queue
        </button>
    </div>

    @if ($result)
        @php $priority = $result['priority'] ?? []; @endphp
        <div class="mb-4 flex items-center gap-3">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold uppercase
                {{ match(strtoupper($priority['colour'] ?? '')) {
                    'RED' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                    'ORANGE' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
                    'YELLOW' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',
                    'GREEN' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
                    default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                } }}">
                {{ $priority['colour'] ?? 'UNSCORED' }}
            </span>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                {{ $priority['score_code'] ?? '' }}
                @if (isset($priority['total']))
                    &middot; total {{ $priority['total'] }}
                @endif
            </span>
            @if ($result['announced'] ?? false)
                <span class="text-[10px] text-blue-600 dark:text-blue-400">Announced to queue</span>
            @endif
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach (($result['scores'] ?? []) as $code => $score)
                <div wire:key="score-{{ $code }}" class="text-xs rounded border border-gray-100 dark:border-gray-700 p-2">
                    <div class="font-medium text-gray-800 dark:text-gray-100">{{ $score['score_name'] ?? $code }}</div>
                    @if (($score['status'] ?? '') === 'CALCULATED')
                        <div class="text-gray-600 dark:text-gray-300">Score: {{ $score['score'] ?? '—' }} &middot; {{ $score['priority'] ?? '' }}</div>
                    @else
                        <div class="text-gray-400">
                            Incomplete
                            @if (! empty($score['missing_parameters']))
                                — missing {{ implode(', ', $score['missing_parameters']) }}
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
