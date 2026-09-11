<div wire:init="check" class="rounded-lg border p-4 flex items-start justify-between gap-4
    {{ ! $checked
        ? 'border-gray-300 bg-gray-50 dark:bg-gray-700/40 dark:border-gray-600'
        : (! $isConfigured
            ? 'border-gray-300 bg-gray-50 dark:bg-gray-700/40 dark:border-gray-600'
            : ($ok
                ? 'border-green-300 bg-green-50 dark:bg-green-900/30 dark:border-green-700'
                : 'border-red-300 bg-red-50 dark:bg-red-900/30 dark:border-red-700')) }}">
    <div>
        <div class="flex items-center gap-2">
            @if (! $checked)
                <span class="inline-block w-2.5 h-2.5 rounded-full bg-gray-300 animate-pulse"></span>
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Checking Clinical Module…</span>
            @else
                <span class="inline-block w-2.5 h-2.5 rounded-full
                    {{ ! $isConfigured ? 'bg-gray-400' : ($ok ? 'bg-green-500' : 'bg-red-500') }}"></span>
                <span class="text-sm font-medium
                    {{ ! $isConfigured
                        ? 'text-gray-700 dark:text-gray-300'
                        : ($ok ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200') }}">
                    @if (! $isConfigured)
                        Clinical Module not configured
                    @elseif ($ok)
                        Clinical Module reachable
                    @else
                        Clinical Module unreachable
                    @endif
                </span>
                <span class="text-xs text-gray-400 dark:text-gray-500 font-mono">{{ $status }}</span>
            @endif
        </div>

        @if ($checked && ! $isConfigured)
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                No Clinical Module URL is configured — set it below.
            </p>
        @elseif ($checked && ! empty($checks))
            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                @foreach ($checks as $name => $passed)
                    <span>
                        {{ ucfirst(str_replace('_', ' ', $name)) }}:
                        <span class="{{ $passed ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ $passed ? 'ok' : 'failing' }}
                        </span>
                    </span>
                @endforeach
            </div>
        @elseif ($checked && $isConfigured && ! $ok)
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                GET /api/v1/health did not return a successful response. Confirm the URL and that
                the Clinical Module is running.
            </p>
        @endif

        @if ($checkedAt)
            <p class="mt-1 text-[10px] text-gray-400 dark:text-gray-500">
                Checked {{ \Illuminate\Support\Carbon::parse($checkedAt)->diffForHumans() }}
            </p>
        @endif
    </div>

    <button wire:click="check" wire:loading.attr="disabled"
        class="shrink-0 text-xs px-3 py-1.5 rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50">
        <span wire:loading.remove>Recheck</span>
        <span wire:loading>Checking…</span>
    </button>
</div>
