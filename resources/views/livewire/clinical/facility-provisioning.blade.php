@if ($canManage && ! $needsSelection)
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 mb-4">
        @if ($resultMessage)
            <div class="mb-3 text-xs rounded p-2 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300">{{ $resultMessage }}</div>
        @endif

        @if ($errorMessage)
            <div class="mb-3 text-xs rounded p-2 bg-amber-50 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-200">
                {{ $errorMessage }}
            </div>
        @endif

        @if (! $status)
            <p class="text-xs text-gray-400">Could not read this facility's provisioning status.</p>
        @elseif ($status['is_provisioned'] && empty($status['empty_dictionaries']))
            <div class="flex items-center justify-between">
                <div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium uppercase bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">
                        Provisioned
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">
                        {{ $status['populated_dictionaries'] }}/{{ $status['dictionary_count'] }} dictionaries, {{ $status['total_rows'] }} rows
                    </span>
                </div>
                <button wire:click="provision(true)" wire:loading.attr="disabled"
                    wire:confirm="Re-sync this facility's clinical dictionaries? Seeded rows refresh and any edited labels reset to their defaults — activate/deactivate choices are preserved. This can take up to a minute."
                    class="text-xs text-gray-600 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded px-3 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="provision(true)">Re-sync</span>
                    <span wire:loading wire:target="provision(true)">Re-syncing&hellip; (can take a minute)</span>
                </button>
            </div>
        @else
            <div class="flex items-center justify-between">
                <div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium uppercase
                        {{ $status['is_provisioned'] ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' }}">
                        {{ $status['status'] }}
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">
                        This facility has no clinical dictionaries yet — every picker on this screen will render empty until it is provisioned.
                        @if (! empty($status['empty_dictionaries']))
                            ({{ count($status['empty_dictionaries']) }} empty)
                        @endif
                    </span>
                </div>
                <button wire:click="provision(false)" wire:loading.attr="disabled" wire:target="provision(false)"
                    class="text-xs text-white bg-blue-600 hover:bg-blue-700 rounded px-3 py-1.5 disabled:opacity-50">
                    <span wire:loading.remove wire:target="provision(false)">Provision</span>
                    <span wire:loading wire:target="provision(false)">Provisioning&hellip; (can take a minute)</span>
                </button>
            </div>
        @endif
    </div>
@endif
