<div class="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100">
        <h3 class="text-sm font-semibold text-slate-900">AI request log</h3>
        <p class="mt-0.5 text-xs text-slate-500">Open View for the full page of what Inventory sent and what the gateway returned.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2 font-medium">When</th>
                    <th class="px-4 py-2 font-medium">Task</th>
                    <th class="px-4 py-2 font-medium">Scope</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2 font-medium">Feedback</th>
                    <th class="px-4 py-2 font-medium">By</th>
                    <th class="px-4 py-2 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($logs as $log)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2 whitespace-nowrap text-slate-600">{{ $log->created_at?->format('d M Y H:i') }}</td>
                        <td class="px-4 py-2">
                            <span class="text-slate-900">{{ $log->title ?? $log->use_case }}</span>
                            <span class="block font-mono text-[11px] text-slate-400">{{ $log->capability }}</span>
                        </td>
                        <td class="px-4 py-2 text-slate-700">{{ $log->scopeLabel() }}</td>
                        <td class="px-4 py-2">
                            @if($log->ok)
                                <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-800">Draft back</span>
                            @else
                                <span class="inline-flex rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-medium text-red-800">{{ $log->error_code ?: 'Failed' }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-slate-600 max-w-xs truncate">{{ $log->summary ?: $log->error ?: '—' }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $log->recordedBy?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            <a href="{{ route('inventory.ai.logs.show', $log) }}"
                               class="inline-flex items-center rounded-md bg-white px-2.5 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">
                                View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-slate-500">No AI asks logged yet for this scope.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
