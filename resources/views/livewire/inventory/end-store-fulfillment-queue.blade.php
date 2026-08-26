<div
    class="space-y-4"
    @if($this->unacknowledgedStatCount() > 0) wire:poll.15s @endif
>
    @if($lastHandoffRef)
        <div class="rounded-lg border border-sky-300 bg-sky-50 px-4 py-3 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-sky-900">Staged — waiting on Clinical handoff</p>
                <p class="mt-0.5 text-sm text-sky-800">
                    {{ $lastHandoffBasket ?? inventory_label('client') }} — ward was notified.
                    Ask the nurse for their Clinical 5-digit code, then use <span class="font-medium">Release</span>.
                </p>
                <p class="mt-1 font-mono text-xs text-sky-700">Ref: {{ $lastHandoffRef }}</p>
            </div>
            <button type="button" wire:click="clearLastHandoffBanner"
                    class="text-sm font-medium text-sky-900 underline hover:no-underline">
                Dismiss
            </button>
        </div>
    @endif

    @if($this->unacknowledgedStatCount() > 0)
        <div class="rounded-lg border-2 border-red-500 bg-red-50 px-4 py-3 flex flex-wrap items-center justify-between gap-3 animate-pulse"
             x-data="{
                play() {
                    try {
                        const Ctx = window.AudioContext || window.webkitAudioContext;
                        if (!Ctx) return;
                        const ctx = new Ctx();
                        const o = ctx.createOscillator();
                        const g = ctx.createGain();
                        o.type = 'square';
                        o.frequency.value = 880;
                        g.gain.value = 0.05;
                        o.connect(g); g.connect(ctx.destination);
                        o.start();
                        setTimeout(() => { o.stop(); ctx.close(); }, 180);
                    } catch (e) {}
                }
             }"
             x-init="play(); setInterval(() => play(), 60000)">
            <div>
                <p class="text-sm font-bold uppercase tracking-wide text-red-800">STAT alert</p>
                <p class="mt-0.5 text-sm text-red-700">
                    {{ $this->unacknowledgedStatCount() }} unacknowledged STAT line{{ $this->unacknowledgedStatCount() === 1 ? '' : 's' }}.
                    Open the STAT tab and acknowledge, then dispense or stage.
                </p>
            </div>
            <button type="button"
                    wire:click="setConsoleTab('stat')"
                    class="inline-flex items-center rounded-md bg-red-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-red-700">
                Open STAT
            </button>
        </div>
    @endif

        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <label class="block text-xs font-medium uppercase tracking-wide text-gray-500">End Store</label>
            <select wire:model.live="selectedStoreId"
                    class="mt-1 block w-full max-w-sm rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">All End Stores</option>
                @foreach($this->endStoreOptions() as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if($selectedStoreId)
                <a href="{{ route('inventory.fulfillment.ward-pick', $selectedStoreId) }}"
                   target="_blank"
                   class="inline-flex items-center rounded-md border border-amber-300 bg-amber-50 px-3 py-1.5 text-sm font-medium text-amber-900 hover:bg-amber-100">
                    Ward pick route
                </a>
            @endif
            <div class="flex flex-wrap gap-2 text-xs text-gray-500">
                <span class="rounded-full bg-gray-100 px-2.5 py-1">Open {{ $this->openCount() }}</span>
                <span class="rounded-full bg-sky-50 text-sky-800 px-2.5 py-1">OP {{ $this->outpatientOpenCount() }}</span>
                <span class="rounded-full bg-amber-50 text-amber-900 px-2.5 py-1">IP {{ $this->inpatientOpenCount() }}</span>
                <span class="rounded-full bg-red-50 text-red-800 px-2.5 py-1">STAT {{ $this->statOpenCount() }}</span>
            </div>
        </div>
    </div>

    <div class="border-b border-gray-200">
        <nav class="-mb-px flex flex-wrap gap-1" aria-label="EndStore tabs">
            @foreach([
                'all' => 'All',
                'outpatient' => 'Outpatient',
                'inpatient' => 'Inpatient',
                'stat' => 'STAT',
            ] as $tab => $label)
                <button type="button"
                        wire:click="setConsoleTab('{{ $tab }}')"
                        @class([
                            'whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition',
                            'border-blue-600 text-blue-700' => $consoleTab === $tab && $tab !== 'stat',
                            'border-red-600 text-red-700' => $consoleTab === $tab && $tab === 'stat',
                            'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' => $consoleTab !== $tab,
                        ])>
                    {{ $label }}
                    @if($tab === 'stat' && $this->unacknowledgedStatCount() > 0)
                        <span class="ml-1 inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-red-600 px-1.5 text-[11px] font-semibold text-white">
                            {{ $this->unacknowledgedStatCount() }}
                        </span>
                    @endif
                </button>
            @endforeach
        </nav>
    </div>

    <div class="end-store-console-table">
        <style>
            .end-store-console-table .fi-ta-row-stat-unacked,
            .end-store-console-table tr.fi-ta-row-stat-unacked {
                background-color: rgb(254 242 242) !important;
            }
            .end-store-console-table .fi-ta-row-stat-unacked > td:first-child,
            .end-store-console-table tr.fi-ta-row-stat-unacked > td:first-child {
                box-shadow: inset 3px 0 0 rgb(220 38 38);
            }
        </style>
        {{ $this->table }}
    </div>
</div>
