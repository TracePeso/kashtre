@if($aiExchange)
    @php
        $sent = is_array($aiExchange->request_payload) ? $aiExchange->request_payload : [];
        $received = is_array($aiExchange->response_payload) ? $aiExchange->response_payload : [];
        $input = is_array($sent['input'] ?? null) ? $sent['input'] : [];
        $history = is_array($input['history'] ?? null) ? $input['history'] : [];
        $result = is_array($received['result'] ?? null) ? $received['result'] : [];
        $series = is_array($result['series'] ?? null) ? $result['series'] : [];
        $periodDays = $sent['periodDays'] ?? null;
        $textList = function (mixed $items): array {
            if (! is_array($items)) {
                return [];
            }
            $out = [];
            foreach ($items as $item) {
                if (is_string($item) && trim($item) !== '' && trim($item) !== '[]') {
                    $out[] = trim($item);
                    continue;
                }
                if (! is_array($item)) {
                    continue;
                }
                foreach (['text', 'summary', 'message', 'pattern', 'risk', 'name', 'item'] as $key) {
                    if (is_string($item[$key] ?? null) && trim($item[$key]) !== '') {
                        $out[] = trim($item[$key]);
                        continue 2;
                    }
                }
            }

            return $out;
        };
        $warnings = array_values(array_unique(array_merge(
            $textList($received['warnings'] ?? []),
            $textList($result['warnings'] ?? []),
        )));
        $risks = $textList($result['risks'] ?? []);
        $assumptions = $textList($result['assumptions'] ?? []);
        $patterns = $textList($result['patterns'] ?? []);
    @endphp

    <div class="mt-6 space-y-6">
        <div class="bg-white shadow sm:rounded-lg overflow-hidden">
            <div class="px-4 py-3 sm:px-6 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900">Sent to AI</h3>
                <p class="mt-0.5 text-xs text-gray-500">The review request for this draft. Line quantities on the order still come from the inventory calculation for the same period.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2 sm:px-6 font-medium">Field</th>
                            <th class="px-4 py-2 sm:px-6 font-medium">Sent</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr>
                            <td class="px-4 py-2 sm:px-6 text-slate-500">Period</td>
                            <td class="px-4 py-2 sm:px-6 text-slate-900">{{ $periodDays !== null ? number_format((float) $periodDays, 0).' days' : '—' }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 sm:px-6 text-slate-500">Horizon</td>
                            <td class="px-4 py-2 sm:px-6 font-mono text-slate-900">{{ $input['horizon'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 sm:px-6 text-slate-500">Grain</td>
                            <td class="px-4 py-2 sm:px-6 text-slate-900">{{ $input['grain'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 sm:px-6 text-slate-500">Timezone</td>
                            <td class="px-4 py-2 sm:px-6 text-slate-900">{{ $input['timezone'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 sm:px-6 text-slate-500">Capability</td>
                            <td class="px-4 py-2 sm:px-6 font-mono text-slate-900">{{ $sent['capability'] ?? $aiExchange->capability }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 sm:px-6 align-top text-slate-500">Measure</td>
                            <td class="px-4 py-2 sm:px-6 text-slate-900 whitespace-pre-wrap">{{ $input['measure'] ?? $input['goal'] ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="overflow-x-auto border-t border-gray-100">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2 sm:px-6 font-medium">Week</th>
                            <th class="px-4 py-2 sm:px-6 font-medium">Period</th>
                            <th class="px-4 py-2 sm:px-6 font-medium text-right">Quantity</th>
                            <th class="px-4 py-2 sm:px-6 font-medium">Sent as</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($history as $point)
                            @php
                                $missing = (bool) ($point['missing'] ?? false) || ($point['value'] ?? null) === null;
                                $period = (string) ($point['period'] ?? '');
                            @endphp
                            <tr>
                                <td class="px-4 py-2 sm:px-6 text-slate-700">{{ $period !== '' ? \App\Services\Inventory\InventoryAiAdvisor::formatIsoWeek($period) : '—' }}</td>
                                <td class="px-4 py-2 sm:px-6 font-mono text-slate-500">{{ $period !== '' ? $period : '—' }}</td>
                                <td class="px-4 py-2 sm:px-6 text-right tabular-nums {{ $missing ? 'text-slate-400' : 'text-slate-900' }}">
                                    {{ $missing ? '—' : number_format((float) $point['value'], 2) }}
                                </td>
                                <td class="px-4 py-2 sm:px-6 {{ $missing ? 'text-slate-400' : 'text-slate-700' }}">{{ $missing ? 'Missing' : 'Recorded' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 sm:px-6 text-center text-slate-500">No weekly history was sent.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white shadow sm:rounded-lg overflow-hidden">
            <div class="px-4 py-3 sm:px-6 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900">Returned by AI</h3>
                <p class="mt-0.5 text-xs text-gray-500">Advice only. It does not replace the draft quantities.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2 sm:px-6 font-medium">Field</th>
                            <th class="px-4 py-2 sm:px-6 font-medium">Returned</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr>
                            <td class="px-4 py-2 sm:px-6 text-slate-500">Status</td>
                            <td class="px-4 py-2 sm:px-6 text-slate-900">{{ $received['status'] ?? ($aiExchange->ok ? 'OK' : 'Failed') }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 sm:px-6 text-slate-500">Request</td>
                            <td class="px-4 py-2 sm:px-6 font-mono text-slate-900">{{ $received['requestId'] ?? $aiExchange->request_id ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 sm:px-6 text-slate-500">Horizon</td>
                            <td class="px-4 py-2 sm:px-6 font-mono text-slate-900">{{ $result['horizon'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 sm:px-6 align-top text-slate-500">Summary</td>
                            <td class="px-4 py-2 sm:px-6 text-slate-900 whitespace-pre-wrap">{{ $aiExchange->summary ?: ($result['summary'] ?? $result['narrative'] ?? '—') }}</td>
                        </tr>
                        @if($aiExchange->error)
                            <tr>
                                <td class="px-4 py-2 sm:px-6 text-slate-500">Error</td>
                                <td class="px-4 py-2 sm:px-6 text-red-700">{{ $aiExchange->error }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            <div class="overflow-x-auto border-t border-gray-100">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2 sm:px-6 font-medium">Period</th>
                            <th class="px-4 py-2 sm:px-6 font-medium text-right">Central</th>
                            <th class="px-4 py-2 sm:px-6 font-medium text-right">Lower</th>
                            <th class="px-4 py-2 sm:px-6 font-medium text-right">Upper</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($series as $point)
                            @if(is_array($point))
                                <tr>
                                    <td class="px-4 py-2 sm:px-6 text-slate-700">
                                        {{ \App\Services\Inventory\InventoryAiAdvisor::formatIsoWeek((string) ($point['period'] ?? '')) }}
                                        <span class="block font-mono text-xs text-slate-400">{{ $point['period'] ?? '' }}</span>
                                    </td>
                                    <td class="px-4 py-2 sm:px-6 text-right tabular-nums text-slate-900">{{ is_numeric($point['central'] ?? null) ? number_format((float) $point['central'], 2) : '—' }}</td>
                                    <td class="px-4 py-2 sm:px-6 text-right tabular-nums text-slate-700">{{ is_numeric($point['lower'] ?? null) ? number_format((float) $point['lower'], 2) : '—' }}</td>
                                    <td class="px-4 py-2 sm:px-6 text-right tabular-nums text-slate-700">{{ is_numeric($point['upper'] ?? null) ? number_format((float) $point['upper'], 2) : '—' }}</td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 sm:px-6 text-center text-slate-500">No forecast series in the reply.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @foreach([
                'Warnings' => $warnings,
                'Risks' => $risks,
                'Assumptions' => $assumptions,
                'Patterns' => $patterns,
            ] as $label => $lines)
                @if($lines !== [])
                    <div class="overflow-x-auto border-t border-gray-100">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-2 sm:px-6 font-medium">{{ $label }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($lines as $line)
                                    <tr>
                                        <td class="px-4 py-2 sm:px-6 text-slate-800">{{ $line }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
@endif
