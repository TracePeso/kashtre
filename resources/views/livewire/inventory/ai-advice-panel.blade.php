<div class="rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-slate-900">AI advice</h3>
            <p class="mt-0.5 text-xs text-slate-500">
                Draft only. Inventory still creates orders, transfers, and write-offs.
                Calls <span class="font-mono">{{ $gatewayUrl }}</span>
            </p>
        </div>
        @if($advice['requiresHumanReview'] ?? false)
            <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-800 ring-1 ring-amber-200">Needs a person</span>
        @endif
    </div>

    <div class="px-4 py-4 space-y-3">
        @unless($configured)
            <p class="text-sm text-slate-600">
                Inventory is aimed at the live gateway, but it needs a module token.
                Set <span class="font-mono text-xs">AI_GATEWAY_INVENTORY_TOKEN</span> from the Inventory app on the AI gateway.
            </p>
        @else
            <label class="block">
                <span class="text-xs font-medium text-slate-600">Optional note</span>
                <textarea wire:model="question" rows="2"
                          placeholder="e.g. Paracetamol is running low. What should we check before we order more?"
                          class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
            </label>

            <div class="flex flex-wrap gap-2">
                <button type="button"
                        wire:click="check"
                        wire:loading.attr="disabled"
                        wire:target="check,ask"
                        class="inline-flex items-center px-3 py-2 rounded-md text-xs font-medium text-white bg-slate-900 hover:bg-slate-800 disabled:opacity-60">
                    <span wire:loading.remove wire:target="check">{{ $actionLabel }}</span>
                    <span wire:loading wire:target="check">Asking AI…</span>
                </button>
                @if($allowAsk)
                    <button type="button"
                            wire:click="ask"
                            wire:loading.attr="disabled"
                            wire:target="check,ask"
                            class="inline-flex items-center px-3 py-2 rounded-md text-xs font-medium text-slate-700 bg-white ring-1 ring-slate-200 hover:bg-slate-50 disabled:opacity-60">
                        <span wire:loading.remove wire:target="ask">Ask before ordering</span>
                        <span wire:loading wire:target="ask">Asking AI…</span>
                    </button>
                @endif
            </div>
        @endunless

        @if($advice)
            @if(! empty($advice['error']))
                <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                    {{ $advice['error'] }}
                    @if(! empty($advice['errorCode']))
                        <span class="block mt-1 text-xs font-medium">{{ $advice['errorCode'] }}</span>
                    @endif
                    @if(! empty($advice['details']['errors']) && is_array($advice['details']['errors']))
                        <ul class="mt-1 text-xs font-mono space-y-0.5">
                            @foreach($advice['details']['errors'] as $schemaError)
                                <li>{{ $schemaError['path'] ?? '/' }} · {{ $schemaError['rule'] ?? 'invalid' }}@if(! empty($schemaError['missing'])) · missing {{ implode(', ', $schemaError['missing']) }}@endif</li>
                            @endforeach
                        </ul>
                    @endif
                    @if(! empty($advice['requestId']))
                        <span class="block mt-1 font-mono text-[11px] text-red-700">{{ $advice['requestId'] }}</span>
                    @endif
                </div>
            @else
                <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-3 space-y-2">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $advice['title'] ?? 'Advice' }}</p>
                    @if(! empty($advice['summary']))
                        <p class="text-sm text-slate-800 whitespace-pre-wrap">{{ $advice['summary'] }}</p>
                    @endif
                    @if(! empty($advice['lines']))
                        <ul class="list-disc list-inside text-sm text-slate-700 space-y-1">
                            @foreach($advice['lines'] as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if(empty($advice['summary']) && empty($advice['lines']))
                        <p class="text-sm text-slate-600">AI returned a draft with no extra detail. Inventory numbers are unchanged.</p>
                    @endif
                    @if(! empty($advice['warnings']))
                        <ul class="text-xs text-amber-800 space-y-0.5">
                            @foreach($advice['warnings'] as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif
        @endif
    </div>
</div>
