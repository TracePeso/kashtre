@php
    $status = $cart->crash_cart_status ?? 'ready';
    $isSealed = $status === 'ready';
    $isOpen = $status === 'open';
    $badgeColor = match ($cart->crashCartStatusBadgeColor()) {
        'success' => 'bg-green-100 text-green-800 ring-green-200',
        'danger' => 'bg-red-100 text-red-800 ring-red-200',
        default => 'bg-gray-100 text-gray-700 ring-gray-200',
    };
@endphp
<div class="space-y-4">
    {{-- Summary --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">End {{ inventory_label('store') }}</p>
            <p class="mt-1 text-sm font-semibold text-gray-900">{{ $cart->parent?->name ?? '—' }}</p>
            @if ($cart->branch?->name)
                <p class="mt-0.5 text-xs text-gray-500">{{ $cart->branch->name }}</p>
            @endif
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Seal</p>
            <p class="mt-1 font-mono text-sm text-gray-900">{{ $cart->crash_cart_seal_number ?: '—' }}</p>
            @if ($cart->crash_cart_sealed_at && $isSealed)
                <p class="mt-0.5 text-xs text-gray-500">Sealed {{ $cart->crash_cart_sealed_at->format('d M Y H:i') }}</p>
            @endif
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Manifest lines</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{{ $cart->crashCartItems()->count() }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Status</p>
            <p class="mt-1">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $badgeColor }}">
                    {{ $cart->crashCartStatusLabel() }}
                </span>
            </p>
            @if ($isOpen && $cart->crash_cart_deployed_at)
                <p class="mt-1 text-xs text-gray-500">Opened {{ $cart->crash_cart_deployed_at->format('d M Y H:i') }}</p>
            @endif
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-200 px-4 sm:px-6">
            <nav class="-mb-px flex flex-wrap gap-1" aria-label="Crash cart tabs">
                @foreach([
                    'overview' => 'Overview',
                    'usage' => 'Usage',
                    'history' => 'History',
                ] as $tab => $label)
                    <button type="button"
                            wire:click="setActiveTab('{{ $tab }}')"
                            @class([
                                'whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium transition',
                                'border-blue-600 text-blue-700' => $activeTab === $tab,
                                'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' => $activeTab !== $tab,
                            ])>
                        {{ $label }}
                        @if ($tab === 'usage' && $this->usageCount() > 0)
                            <span class="ml-1 inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-gray-100 px-1.5 text-[11px] font-semibold text-gray-700">
                                {{ $this->usageCount() }}
                            </span>
                        @endif
                        @if ($tab === 'history' && $this->historyCount() > 0)
                            <span class="ml-1 inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-gray-100 px-1.5 text-[11px] font-semibold text-gray-700">
                                {{ $this->historyCount() }}
                            </span>
                        @endif
                    </button>
                @endforeach
            </nav>
        </div>

        <div class="p-4 sm:p-6 crash-cart-show-table">
            @if ($activeTab === 'overview' && $isOpen)
                @php $plan = $this->restockPlan(); @endphp
                @if (($plan['shortages'] ?? []) !== [])
                    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        Cannot restock until
                        <span class="font-medium">{{ $cart->parent?->name ?? 'the End Store' }}</span>
                        has enough of every short line:
                        {{ implode('; ', $plan['shortages']) }}
                    </div>
                @elseif (($plan['lines'] ?? []) !== [])
                    <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        Ready to pull
                        {{ count($plan['lines']) }}
                        line{{ count($plan['lines']) === 1 ? '' : 's' }}
                        from
                        <span class="font-medium">{{ $cart->parent?->name ?? 'End Store' }}</span>
                        via <span class="font-medium">Restock &amp; reseal</span>.
                    </div>
                @endif
            @endif

            <div class="fi-ta-ctn w-full overflow-x-auto">
                {{ $this->table }}
            </div>

            @if ($activeTab === 'overview' && $isSealed)
                <p class="mt-3 text-xs text-gray-500">
                    While sealed, change the manifesto (par) under Manage Stores — that restocks the cart to par.
                </p>
            @endif
        </div>
    </div>
</div>
