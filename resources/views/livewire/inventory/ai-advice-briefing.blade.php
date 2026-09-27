<div class="space-y-6">
    <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">What you are looking at</h3>
                <p class="mt-0.5 text-xs text-slate-500">{{ $briefing['scope_label'] }} · {{ $briefing['timezone'] }}</p>
            </div>
            <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-800 ring-1 ring-amber-200">Needs a person</span>
        </div>
        <div class="px-4 py-4 grid gap-4 md:grid-cols-3 text-sm text-slate-700">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">History</p>
                <p class="mt-1">{{ $briefing['usable_weeks'] }} of 12 weeks have numbers. Average {{ number_format($briefing['average_week'], 0) }} sale units / week.</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Forecast horizon</p>
                <p class="mt-1">{{ $briefing['horizon_label'] }}. The gateway writes this as P4W. It is a draft range, not a purchase quantity.</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Units</p>
                <p class="mt-1">{{ $briefing['unit_label'] }}</p>
            </div>
        </div>
        @if(! $itemId)
            <p class="px-4 pb-4 text-sm text-amber-800 bg-amber-50/60">
                Store-wide totals look huge because every SKU is added together — oxygen litres, tablets, and vials in one number.
                Pick one item below if you want a forecast you can compare to a shelf.
            </p>
        @endif
    </div>

    <div class="rounded-lg border border-slate-200 bg-white shadow-sm px-4 py-4 space-y-4">
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[12rem] flex-1 sm:flex-none sm:w-56">
                <label class="block text-xs font-medium text-gray-600 mb-1">Store</label>
                <select wire:model.live="storeId" class="h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">All stores</option>
                    @foreach($storeOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[12rem] flex-1 sm:flex-none sm:w-64">
                <label class="block text-xs font-medium text-gray-600 mb-1">Item</label>
                <select wire:model.live="itemId" @disabled(! $storeId) class="h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50">
                    <option value="">All items in scope</option>
                    @foreach($itemOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            @foreach($tasks as $key => $task)
                <button type="button"
                        wire:click="setUseCase('{{ $key }}')"
                        @class([
                            'rounded-md px-3 py-1.5 text-xs font-medium ring-1',
                            'bg-slate-900 text-white ring-slate-900' => $useCase === $key,
                            'bg-white text-slate-700 ring-slate-200 hover:bg-slate-50' => $useCase !== $key,
                        ])>
                    {{ $task['label'] }}
                </button>
            @endforeach
        </div>

        @unless($configured)
            <p class="text-sm text-slate-600">
                Set <span class="font-mono text-xs">AI_GATEWAY_INVENTORY_TOKEN</span> and
                <span class="font-mono text-xs">AI_GATEWAY_TENANT_ID</span> to ask the live gateway.
                History below still comes from this store.
            </p>
        @else
            <label class="block">
                <span class="text-xs font-medium text-slate-600">Optional note for the model</span>
                <textarea wire:model="question" rows="2"
                          placeholder="e.g. Focus on Paracetamol. What should we check before we order more?"
                          class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
            </label>
            <div class="flex flex-wrap gap-2">
                <button type="button"
                        wire:click="check"
                        wire:loading.attr="disabled"
                        wire:target="check,ask"
                        @disabled($needsHistory && $briefing['usable_weeks'] < 3)
                        class="inline-flex items-center px-3 py-2 rounded-md text-xs font-medium text-white bg-slate-900 hover:bg-slate-800 disabled:opacity-60">
                    <span wire:loading.remove wire:target="check">{{ $actionLabel }}</span>
                    <span wire:loading wire:target="check">Asking AI…</span>
                </button>
                <button type="button"
                        wire:click="ask"
                        wire:loading.attr="disabled"
                        wire:target="check,ask"
                        class="inline-flex items-center px-3 py-2 rounded-md text-xs font-medium text-slate-700 bg-white ring-1 ring-slate-200 hover:bg-slate-50 disabled:opacity-60">
                    <span wire:loading.remove wire:target="ask">Ask before ordering</span>
                    <span wire:loading wire:target="ask">Asking AI…</span>
                </button>
            </div>
        @endunless
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-900">Last 12 weeks</h3>
                <p class="mt-0.5 text-xs text-slate-500">What this store already recorded. Missing weeks stay blank — we do not invent zeros.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2 font-medium">Week</th>
                            <th class="px-4 py-2 font-medium text-right">Sale units</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($briefing['history'] as $point)
                            <tr>
                                <td class="px-4 py-2 text-slate-700">{{ $point['period'] }}</td>
                                <td class="px-4 py-2 text-right tabular-nums {{ $point['missing'] ? 'text-slate-400' : 'text-slate-900' }}">
                                    {{ $point['missing'] ? '—' : number_format((float) $point['value'], 0) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50 text-sm">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-slate-600">Usable total</th>
                            <th class="px-4 py-2 text-right tabular-nums font-medium text-slate-900">{{ number_format($briefing['history_total'], 0) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-900">Low stock snapshot</h3>
                <p class="mt-0.5 text-xs text-slate-500">Lowest on-hand in this scope. 15-day MA is recent daily usage, not a forecast.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2 font-medium">Item</th>
                            <th class="px-4 py-2 font-medium text-right">On hand</th>
                            <th class="px-4 py-2 font-medium text-right">15-day MA</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($briefing['snapshot'] as $item)
                            <tr>
                                <td class="px-4 py-2">
                                    <span class="text-slate-900">{{ $item['name'] }}</span>
                                    @if($item['code'])
                                        <span class="block text-xs text-slate-500">{{ $item['code'] }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums {{ $item['on_hand'] <= 0 ? 'text-amber-800' : 'text-slate-900' }}">{{ number_format($item['on_hand'], 1) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums text-slate-700">{{ number_format($item['ma_15'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center text-slate-500">No stock rows in this scope.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($advice)
        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="px-4 py-3 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-900">{{ $advice['title'] ?? 'Draft advice' }}</h3>
                @if(! empty($advice['summary']))
                    <p class="mt-1 text-sm text-slate-700">{{ $advice['summary'] }}</p>
                @endif
            </div>

            <div class="px-4 py-4 space-y-4">
                @if(! empty($advice['error']))
                    <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                        {{ $advice['error'] }}
                        @if(! empty($advice['errorCode']))
                            <span class="block mt-1 text-xs font-medium">{{ $advice['errorCode'] }}</span>
                        @endif
                    </div>
                @else
                    @if(! empty($advice['series']))
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Draft weekly range</p>
                            <p class="mt-1 text-sm text-slate-600">
                                <strong>Central</strong> is the model’s best guess.
                                <strong>Low / high</strong> is the band it thinks is plausible.
                                Compare these to the history table — they should be in the same sale-unit scale.
                            </p>
                            <div class="mt-3 overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th class="px-3 py-2 font-medium">Week</th>
                                            <th class="px-3 py-2 font-medium text-right">Low</th>
                                            <th class="px-3 py-2 font-medium text-right">Central</th>
                                            <th class="px-3 py-2 font-medium text-right">High</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach($advice['series'] as $point)
                                            <tr>
                                                <td class="px-3 py-2 text-slate-700">{{ $point['period'] }}</td>
                                                <td class="px-3 py-2 text-right tabular-nums text-slate-600">{{ $point['lower'] === null ? '—' : number_format($point['lower'], 0) }}</td>
                                                <td class="px-3 py-2 text-right tabular-nums font-medium text-slate-900">{{ number_format($point['central'], 0) }}</td>
                                                <td class="px-3 py-2 text-right tabular-nums text-slate-600">{{ $point['upper'] === null ? '—' : number_format($point['upper'], 0) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    @if(! empty($advice['risks']))
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Risks to review</p>
                            <ul class="mt-1 list-disc list-inside text-sm text-slate-700 space-y-1">
                                @foreach($advice['risks'] as $risk)
                                    <li>{{ $risk }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(! empty($advice['patterns']))
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Patterns</p>
                            <ul class="mt-1 list-disc list-inside text-sm text-slate-700 space-y-1">
                                @foreach($advice['patterns'] as $pattern)
                                    <li>{{ $pattern }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(! empty($advice['assumptions']))
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Assumptions the model made</p>
                            <p class="mt-1 text-sm text-slate-600">These are not facts from Inventory. Challenge them before anyone orders.</p>
                            <ul class="mt-1 list-disc list-inside text-sm text-slate-700 space-y-1">
                                @foreach($advice['assumptions'] as $assumption)
                                    <li>{{ $assumption }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(! empty($advice['warnings']))
                        <ul class="text-xs text-amber-800 space-y-0.5">
                            @foreach($advice['warnings'] as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if(empty($advice['series']) && empty($advice['risks']) && empty($advice['patterns']) && empty($advice['summary']))
                        <p class="text-sm text-slate-600">AI returned a draft with no extra detail. Inventory numbers are unchanged.</p>
                    @endif
                @endif

                @if(! empty($advice['requestId']))
                    <p class="font-mono text-[11px] text-slate-400">{{ $advice['requestId'] }}</p>
                @endif

                @if(! empty($advice['sent']) || ! empty($advice['received']))
                    <div class="grid gap-4 lg:grid-cols-2">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">What we sent</p>
                            <pre class="mt-1 max-h-80 overflow-auto rounded-md bg-slate-900 px-3 py-2 text-[11px] leading-5 text-slate-100">{{ json_encode($advice['sent'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">What AI returned</p>
                            <pre class="mt-1 max-h-80 overflow-auto rounded-md bg-slate-900 px-3 py-2 text-[11px] leading-5 text-slate-100">{{ json_encode($advice['received'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @livewire('inventory.ai-advice-log-table', [
        'storeId' => $storeId,
        'itemId' => $itemId,
    ], key('ai-log-briefing-'.$useCase.'-'.$storeId.'-'.$itemId))
</div>
