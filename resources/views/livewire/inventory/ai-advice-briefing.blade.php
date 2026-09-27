@php
    $ready = ! $needsHistory || $briefing['usable_weeks'] >= 3;
    $mixedUnits = ! $itemId;
@endphp

<div class="space-y-6">
    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Step 1</p>
            <h3 class="mt-1 text-base font-semibold text-slate-900">Choose what to review</h3>
        </div>
        <div class="px-5 py-4 space-y-4">
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
                <div class="min-w-[12rem] flex-1 sm:flex-none sm:w-72">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Item</label>
                    <select wire:model.live="itemId" @disabled(! $storeId) class="h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50">
                        <option value="">All items in this store</option>
                        @foreach($itemOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <p class="text-sm text-slate-700">
                <span class="font-medium text-slate-900">{{ $briefing['scope_label'] }}</span>
                <span class="text-slate-400"> · {{ $briefing['timezone'] }}</span>
            </p>

            @if($mixedUnits)
                <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    All-item totals mix different units (for example litres and tablets) into one number.
                    Pick one item if you want a figure you can compare to a shelf.
                </div>
            @endif
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Step 2</p>
                <h3 class="mt-1 text-base font-semibold text-slate-900">Last 12 weeks</h3>
                <p class="mt-0.5 text-sm text-slate-500">What this store already recorded. Blank weeks were not invented as zero.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 {{ $ready ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : 'bg-amber-50 text-amber-800 ring-amber-200' }}">
                    {{ $briefing['usable_weeks'] }} of 12 weeks recorded
                    @unless($ready)
                        · need 3 for a forecast
                    @endunless
                </span>
            </div>
        </div>

        <div class="px-5 py-4 grid gap-4 sm:grid-cols-3">
            <div class="rounded-md bg-slate-50 px-3 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Typical week</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">{{ number_format($briefing['average_week'], 0) }}</p>
                <p class="mt-0.5 text-xs text-slate-500">{{ $itemId ? 'Units of this item' : 'Mixed units — not money' }}</p>
            </div>
            <div class="rounded-md bg-slate-50 px-3 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Recorded total</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">{{ number_format($briefing['history_total'], 0) }}</p>
                <p class="mt-0.5 text-xs text-slate-500">Across weeks that have numbers</p>
            </div>
            <div class="rounded-md bg-slate-50 px-3 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Draft looks ahead</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">4 weeks</p>
                <p class="mt-0.5 text-xs text-slate-500">A range to review, not an order quantity</p>
            </div>
        </div>

        <div class="px-5 pb-4">
            <div class="flex items-end gap-1 h-16" aria-hidden="true">
                @foreach($briefing['history'] as $point)
                    @php
                        $height = ($point['missing'] ?? false)
                            ? 8
                            : max(8, (int) round(((float) ($point['value'] ?? 0) / $historyMax) * 100));
                    @endphp
                    <div class="flex-1 rounded-t {{ ($point['missing'] ?? false) ? 'bg-slate-100' : 'bg-slate-700' }}"
                         style="height: {{ $height }}%"
                         title="{{ \App\Services\Inventory\InventoryAiAdvisor::formatIsoWeek($point['period']) }}"></div>
                @endforeach
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2 border-t border-slate-100">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-2 font-medium">Week</th>
                            <th class="px-5 py-2 font-medium text-right">Used</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($briefing['history'] as $point)
                            <tr>
                                <td class="px-5 py-2 text-slate-700">{{ \App\Services\Inventory\InventoryAiAdvisor::formatIsoWeek($point['period']) }}</td>
                                <td class="px-5 py-2 text-right tabular-nums {{ $point['missing'] ? 'text-slate-400' : 'text-slate-900' }}">
                                    {{ $point['missing'] ? '—' : number_format((float) $point['value'], 0) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-2 font-medium">Lowest on hand</th>
                            <th class="px-5 py-2 font-medium text-right">Now</th>
                            <th class="px-5 py-2 font-medium text-right">Daily use</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($briefing['snapshot'] as $item)
                            <tr>
                                <td class="px-5 py-2">
                                    <span class="text-slate-900">{{ $item['name'] }}</span>
                                    @if($item['code'])
                                        <span class="block text-xs text-slate-500">{{ $item['code'] }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-2 text-right tabular-nums {{ $item['on_hand'] <= 0 ? 'text-amber-800' : 'text-slate-900' }}">{{ number_format($item['on_hand'], 1) }}</td>
                                <td class="px-5 py-2 text-right tabular-nums text-slate-700">{{ number_format($item['ma_15'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-5 py-8 text-center text-slate-500">No stock rows in this scope.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <p class="px-5 py-2 text-xs text-slate-500">Daily use is the last 15 days’ average — not the forecast.</p>
            </div>
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Step 3</p>
            <h3 class="mt-1 text-base font-semibold text-slate-900">Ask for a draft</h3>
            <p class="mt-0.5 text-sm text-slate-500">Choose one question. The answer is advice only.</p>
        </div>
        <div class="px-5 py-4 space-y-4">
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach($tasks as $key => $task)
                    <button type="button"
                            wire:click="setUseCase('{{ $key }}')"
                            @class([
                                'text-left rounded-lg border px-4 py-3 transition',
                                'border-slate-900 bg-slate-900 text-white' => $useCase === $key,
                                'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50' => $useCase !== $key,
                            ])>
                        <span class="block text-sm font-semibold">{{ $task['short'] ?? $task['label'] }}</span>
                        <span @class(['mt-1 block text-xs', 'text-slate-300' => $useCase === $key, 'text-slate-500' => $useCase !== $key])>
                            {{ $task['help'] ?? '' }}
                        </span>
                    </button>
                @endforeach
            </div>

            @unless($configured)
                <p class="text-sm text-slate-600">
                    AI is not connected for this organisation yet. You can still review the history above.
                </p>
            @else
                <label class="block">
                    <span class="text-xs font-medium text-slate-600">Optional note</span>
                    <textarea wire:model="question" rows="2"
                              placeholder="e.g. Focus on Paracetamol. What should we check before we order more?"
                              class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                </label>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button"
                            wire:click="submit"
                            wire:loading.attr="disabled"
                            wire:target="submit,check,ask"
                            @disabled($needsHistory && ! $ready)
                            class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-white bg-slate-900 hover:bg-slate-800 disabled:opacity-60">
                        <span wire:loading.remove wire:target="submit">Get draft advice</span>
                        <span wire:loading wire:target="submit">Asking AI…</span>
                    </button>
                    @unless($ready)
                        <p class="text-sm text-amber-800">Record at least three weeks before a forecast will run.</p>
                    @endunless
                </div>
            @endunless
        </div>
    </section>

    @if($advice)
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm" wire:key="advice-{{ $advice['requestId'] ?? 'latest' }}">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Draft</p>
                    <h3 class="mt-1 text-base font-semibold text-slate-900">{{ $advice['title'] ?? 'Draft advice' }}</h3>
                    @if(! empty($advice['summary']))
                        <p class="mt-1 text-sm text-slate-700">{{ $advice['summary'] }}</p>
                    @endif
                </div>
                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800 ring-1 ring-amber-200">Needs a person</span>
            </div>

            <div class="px-5 py-4 space-y-5">
                @if(! empty($advice['error']))
                    <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                        {{ $advice['error'] }}
                    </div>
                @else
                    @if(! empty($advice['series']))
                        <div>
                            <p class="text-sm font-medium text-slate-900">Suggested weekly range</p>
                            <p class="mt-1 text-sm text-slate-600">
                                <strong>Likely</strong> is the best guess.
                                <strong>Low / high</strong> is the band around it.
                                Compare these with the 12-week table above — they should be in the same unit.
                            </p>
                            <div class="mt-3 overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th class="px-3 py-2 font-medium">Week</th>
                                            <th class="px-3 py-2 font-medium text-right">Low</th>
                                            <th class="px-3 py-2 font-medium text-right">Likely</th>
                                            <th class="px-3 py-2 font-medium text-right">High</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach($advice['series'] as $point)
                                            <tr>
                                                <td class="px-3 py-2 text-slate-700">{{ \App\Services\Inventory\InventoryAiAdvisor::formatIsoWeek($point['period']) }}</td>
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
                            <p class="text-sm font-medium text-slate-900">Risks to review</p>
                            <ul class="mt-1 list-disc list-inside text-sm text-slate-700 space-y-1">
                                @foreach($advice['risks'] as $risk)
                                    <li>{{ $risk }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(! empty($advice['patterns']))
                        <div>
                            <p class="text-sm font-medium text-slate-900">Patterns</p>
                            <ul class="mt-1 list-disc list-inside text-sm text-slate-700 space-y-1">
                                @foreach($advice['patterns'] as $pattern)
                                    <li>{{ $pattern }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(! empty($advice['assumptions']))
                        <div>
                            <p class="text-sm font-medium text-slate-900">Assumptions to challenge</p>
                            <p class="mt-1 text-sm text-slate-600">These came from the model, not from Inventory records.</p>
                            <ul class="mt-1 list-disc list-inside text-sm text-slate-700 space-y-1">
                                @foreach($advice['assumptions'] as $assumption)
                                    <li>{{ $assumption }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(! empty($advice['warnings']))
                        <ul class="text-sm text-amber-800 space-y-0.5">
                            @foreach($advice['warnings'] as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if(empty($advice['series']) && empty($advice['risks']) && empty($advice['patterns']) && empty($advice['summary']))
                        <p class="text-sm text-slate-600">AI returned a draft with no extra detail. Inventory numbers are unchanged.</p>
                    @endif
                @endif
            </div>
        </section>
    @endif

    @livewire('inventory.ai-advice-log-table', [
        'storeId' => $storeId,
        'itemId' => $itemId,
    ], key('ai-log-briefing-'.$useCase.'-'.$storeId.'-'.$itemId))
</div>
