<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Audit Trail</h4>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div>
            <h5 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Break Glass Grants</h5>
            @if ($breakGlassLogs->isEmpty())
                <p class="text-xs text-gray-500 dark:text-gray-400">None.</p>
            @else
                @foreach ($breakGlassLogs as $log)
                    <div wire:key="bg-{{ $log->id }}" class="text-xs text-gray-600 dark:text-gray-300 py-1 border-b border-gray-50 dark:border-gray-700">
                        {{ $log->created_at }} — user #{{ $log->user_id }} — {{ $log->reason_code }}
                        <span class="text-gray-400">(until {{ $log->granted_until }})</span>
                    </div>
                @endforeach
            @endif
        </div>

        <div>
            <h5 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Major Transition Steps</h5>
            @if ($stepExecutions->isEmpty())
                <p class="text-xs text-gray-500 dark:text-gray-400">None.</p>
            @else
                @foreach ($stepExecutions as $execution)
                    <div wire:key="step-{{ $execution->id }}" class="text-xs text-gray-600 dark:text-gray-300 py-1 border-b border-gray-50 dark:border-gray-700">
                        {{ $execution->completed_at }} — {{ $execution->step?->step_name }} — {{ $execution->status }}
                        @if ($execution->override_reason)
                            <span class="text-amber-500">(override: {{ $execution->override_reason }})</span>
                        @endif
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>
