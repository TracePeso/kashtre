<div class="space-y-6">
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
        <div class="text-sm text-gray-500 dark:text-gray-400">My Patients</div>
        <div class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $myPatientCount }}</div>
    </div>

    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">My Current Work</h4>

        @if ($pendingTasks->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">Nothing pending for your patients.</p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach ($pendingTasks as $itemName => $tasks)
                    <li wire:key="task-{{ \Illuminate\Support\Str::slug($itemName) }}" class="py-2 flex items-center justify-between">
                        <span class="text-sm text-gray-900 dark:text-gray-100">{{ $itemName }}</span>
                        <span class="text-xs px-2 py-0.5 rounded bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                            {{ $tasks->count() }} Waiting
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">My Ward</h4>

        @if ($myWards->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">None of your patients are currently admitted.</p>
        @else
            @foreach ($myWards as $wardName => $beds)
                <div wire:key="ward-{{ \Illuminate\Support\Str::slug($wardName) }}" class="mb-3">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">{{ $wardName }} &mdash; {{ $beds->count() }} Patients</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($beds as $bed)
                            <a href="{{ route('clinical.observations.show', $bed->current_client_id) }}"
                                class="text-xs px-2 py-1 rounded border border-gray-200 dark:border-gray-600 text-blue-700 dark:text-blue-300 hover:underline">
                                {{ $bed->bed_code }} &middot; {{ $bed->current_client_id }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>
