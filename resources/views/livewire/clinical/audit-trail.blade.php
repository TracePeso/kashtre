<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Audit Trail</h4>

    @if ($entries->isEmpty())
        <p class="text-xs text-gray-500 dark:text-gray-400">No compliance-sensitive events recorded for this patient.</p>
    @else
        <div class="space-y-1">
            @foreach ($entries as $entry)
                <div wire:key="audit-{{ $entry->id }}" class="text-xs text-gray-600 dark:text-gray-300 py-1.5 border-b border-gray-50 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <span>
                            <span class="font-medium text-gray-800 dark:text-gray-100">{{ str_replace('_', ' ', $entry->action) }}</span>
                            @if ($entry->actorName)
                                — {{ $entry->actorName }}
                            @elseif ($entry->actorUserId)
                                — user #{{ $entry->actorUserId }}
                            @endif
                            @if (! empty($entry->actorRoles))
                                <span class="text-gray-400">({{ implode(', ', $entry->actorRoles) }})</span>
                            @endif
                        </span>
                        <span class="text-gray-400 whitespace-nowrap ml-2">{{ $entry->createdAt }}</span>
                    </div>

                    @if (! empty($entry->context))
                        <div class="text-gray-400 mt-0.5">
                            @foreach ($entry->context as $key => $value)
                                @continue(is_null($value) || $value === '')
                                <span class="mr-2">{{ str_replace('_', ' ', $key) }}: {{ is_scalar($value) ? $value : json_encode($value) }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
