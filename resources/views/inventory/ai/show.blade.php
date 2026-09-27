<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('inventory.ai.index') }}"
           class="text-sm text-blue-600 hover:text-blue-800">&larr; Back</a>
        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">{{ $log->title ?? 'AI request' }}</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $log->scopeLabel() }}
                    · {{ $log->created_at?->format('d M Y H:i') }}
                    @if($log->recordedBy)
                        · {{ $log->recordedBy->name }}
                    @endif
                </p>
            </div>
            @if($log->ok)
                <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-800 ring-1 ring-emerald-200">Draft back</span>
            @else
                <span class="inline-flex rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-red-800 ring-1 ring-red-200">{{ $log->error_code ?: 'Failed' }}</span>
            @endif
        </div>

        @include('inventory.partials.subnav')

        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Capability</p>
                <p class="mt-1 font-mono text-sm text-slate-900">{{ $log->capability }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Task</p>
                <p class="mt-1 text-sm text-slate-900">{{ $log->use_case }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Gateway request</p>
                <p class="mt-1 font-mono text-xs text-slate-900 break-all">{{ $log->request_id ?: '—' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Scope</p>
                <p class="mt-1 text-sm text-slate-900">{{ $log->scopeLabel() }}</p>
            </div>
        </div>

        @if($log->question)
            <div class="mt-4 rounded-lg border border-slate-200 bg-white px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Operator note</p>
                <p class="mt-1 text-sm text-slate-800">{{ $log->question }}</p>
            </div>
        @endif

        @if($log->summary)
            <div class="mt-4 rounded-lg border border-slate-200 bg-white px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Feedback</p>
                <p class="mt-1 text-sm text-slate-800">{{ $log->summary }}</p>
            </div>
        @endif

        @if($log->error)
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ $log->error }}
            </div>
        @endif

        @if($series !== [])
            <div class="mt-6 rounded-lg border border-slate-200 bg-white overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100">
                    <h3 class="text-sm font-semibold text-slate-900">Draft weekly range</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-2 font-medium">Week</th>
                                <th class="px-4 py-2 font-medium text-right">Low</th>
                                <th class="px-4 py-2 font-medium text-right">Central</th>
                                <th class="px-4 py-2 font-medium text-right">High</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($series as $point)
                                <tr>
                                    <td class="px-4 py-2 text-slate-700">{{ $point['period'] }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums text-slate-600">{{ $point['lower'] === null ? '—' : number_format($point['lower'], 0) }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums font-medium text-slate-900">{{ number_format($point['central'], 0) }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums text-slate-600">{{ $point['upper'] === null ? '—' : number_format($point['upper'], 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if($risks !== [] || $patterns !== [] || $assumptions !== [])
            <div class="mt-6 grid gap-4 lg:grid-cols-3">
                @if($risks !== [])
                    <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Risks</p>
                        <ul class="mt-2 list-disc list-inside text-sm text-slate-700 space-y-1">
                            @foreach($risks as $risk)
                                <li>{{ $risk }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if($patterns !== [])
                    <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Patterns</p>
                        <ul class="mt-2 list-disc list-inside text-sm text-slate-700 space-y-1">
                            @foreach($patterns as $pattern)
                                <li>{{ $pattern }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if($assumptions !== [])
                    <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Assumptions</p>
                        <ul class="mt-2 list-disc list-inside text-sm text-slate-700 space-y-1">
                            @foreach($assumptions as $assumption)
                                <li>{{ $assumption }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif

        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border border-slate-200 bg-white overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100">
                    <h3 class="text-sm font-semibold text-slate-900">What we sent</h3>
                </div>
                <pre class="max-h-[40rem] overflow-auto bg-slate-900 px-4 py-3 text-[12px] leading-5 text-slate-100">{{ json_encode($log->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100">
                    <h3 class="text-sm font-semibold text-slate-900">What AI returned</h3>
                </div>
                <pre class="max-h-[40rem] overflow-auto bg-slate-900 px-4 py-3 text-[12px] leading-5 text-slate-100">{{ json_encode($log->response_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </div>
    </div>
</div>
</x-app-layout>
