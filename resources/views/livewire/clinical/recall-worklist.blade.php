<div class="space-y-4">
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 flex items-center justify-between">
        <div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Recall Worklist</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">Chronic-disease and post-discharge follow-up, generated automatically from this facility's recall rules.</p>
        </div>
        <select wire:model.live="status" class="text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
            <option value="DUE">Due</option>
            <option value="OVERDUE">Overdue</option>
            <option value="">All</option>
        </select>
    </div>

    @if ($statusMessage)
        <div class="text-xs rounded p-2 bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-200">{{ $statusMessage }}</div>
    @endif

    @if ($errorMessage)
        <div class="text-xs rounded p-2 bg-amber-50 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-200">
            {{ $errorMessage }}
        </div>
    @endif

    @if (empty($result['recalls']))
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 text-sm text-gray-500 dark:text-gray-400">
            Nothing on the recall worklist right now.
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr class="text-left text-xs text-gray-500 dark:text-gray-400 uppercase">
                        <th class="px-4 py-2">Patient</th>
                        <th class="px-4 py-2">Recall</th>
                        <th class="px-4 py-2">Due</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($result['recalls'] as $recall)
                        <tr wire:key="recall-{{ $recall['id'] ?? $loop->index }}" class="border-t border-gray-100 dark:border-gray-700">
                            <td class="px-4 py-2 font-medium text-gray-900 dark:text-gray-100">
                                {{ $recall['patient_id'] ?? $recall['global_client_id'] ?? '—' }}
                            </td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                                {{ $recall['rule_name'] ?? $recall['recall_type'] ?? $recall['rule_code'] ?? '—' }}
                            </td>
                            <td class="px-4 py-2 text-gray-500 dark:text-gray-400">{{ $recall['due_at'] ?? $recall['due_date'] ?? '—' }}</td>
                            <td class="px-4 py-2">
                                <span class="text-[10px] px-1.5 py-0.5 rounded uppercase
                                    {{ ($recall['status'] ?? '') === 'OVERDUE' ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }}">
                                    {{ $recall['status'] ?? 'DUE' }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                <button wire:click="complete('{{ $recall['id'] }}')" class="text-xs text-blue-700 dark:text-blue-300 hover:underline mr-3">Complete</button>
                                <button wire:click="cancel('{{ $recall['id'] }}')"
                                    wire:confirm="Cancel this recall? It will no longer appear as outstanding."
                                    class="text-xs text-gray-400 hover:underline">Cancel</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
