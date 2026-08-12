<x-app-layout>
    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h1 class="text-xl font-semibold text-gray-900">Crash Carts</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Mobile emergency stock. Deploy during a code, reconcile usage after the event, then return the cart to service with a new seal.
                </p>
            </div>

            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                    {{ $errors->first() }}
                </div>
            @endif

            @forelse ($carts as $cart)
                @php
                    $status = $cart->crash_cart_status ?? 'ready';
                    $statusLabel = $cart->crashCartStatusLabel() ?? ucfirst($status);
                    $badgeColor = match ($cart->crashCartStatusBadgeColor()) {
                        'success' => 'bg-green-100 text-green-800 ring-green-200',
                        'warning' => 'bg-amber-100 text-amber-900 ring-amber-200',
                        'danger' => 'bg-red-100 text-red-800 ring-red-200',
                        default => 'bg-gray-100 text-gray-700 ring-gray-200',
                    };
                    $usageCount = (int) ($cart->reconciliation_usage_count ?? 0);
                    $usageQty = (float) ($cart->reconciliation_usage_qty ?? 0);
                @endphp

                <div class="mb-5 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    {{-- Header --}}
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-5 py-4">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900">{{ $cart->name }}</h2>
                            <p class="mt-0.5 text-sm text-gray-500">
                                Parent End {{ strtolower(inventory_label('store')) }}:
                                <span class="font-medium text-gray-700">{{ $cart->parent?->name ?? '—' }}</span>
                            </p>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $badgeColor }}">
                                {{ $statusLabel }}
                            </span>
                            @if ($cart->crash_cart_seal_number)
                                <p class="mt-1.5 text-xs text-gray-500">
                                    Current seal:
                                    <span class="font-mono font-medium text-gray-700">{{ $cart->crash_cart_seal_number }}</span>
                                </p>
                            @endif
                        </div>
                    </div>

                    {{-- Workflow --}}
                    <div class="px-5 py-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Lifecycle</p>
                        <ol class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                            @foreach ([
                                ['key' => 'ready', 'label' => 'Ready', 'desc' => 'Sealed & stocked'],
                                ['key' => 'deployed', 'label' => 'Deployed', 'desc' => 'Emergency in progress'],
                                ['key' => 'reconciling', 'label' => 'Reconciling', 'desc' => 'Document usage'],
                                ['key' => 'complete', 'label' => 'Return to service', 'desc' => 'Seal & restock'],
                            ] as $step)
                                @php
                                    $stepActive = match ($status) {
                                        'ready' => $step['key'] === 'ready',
                                        'deployed' => $step['key'] === 'deployed',
                                        'reconciling' => in_array($step['key'], ['reconciling', 'complete'], true),
                                        default => false,
                                    };
                                    $stepDone = match ($status) {
                                        'deployed' => $step['key'] === 'ready',
                                        'reconciling' => in_array($step['key'], ['ready', 'deployed'], true),
                                        default => false,
                                    };
                                @endphp
                                <li @class([
                                    'rounded-md border px-3 py-2',
                                    'border-blue-200 bg-blue-50' => $stepActive && ! $stepDone,
                                    'border-green-200 bg-green-50' => $stepDone,
                                    'border-gray-200 bg-gray-50' => ! $stepActive && ! $stepDone,
                                ])>
                                    <p @class([
                                        'text-xs font-semibold',
                                        'text-blue-800' => $stepActive && ! $stepDone,
                                        'text-green-800' => $stepDone,
                                        'text-gray-700' => ! $stepActive && ! $stepDone,
                                    ])>{{ $step['label'] }}</p>
                                    <p class="mt-0.5 text-[11px] text-gray-500">{{ $step['desc'] }}</p>
                                </li>
                            @endforeach
                        </ol>
                    </div>

                    {{-- Actions --}}
                    <div class="border-t border-gray-100 bg-gray-50/70 px-5 py-4">
                        @if ($status === 'ready')
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <p class="text-sm text-gray-600">
                                    Cart is sealed and available. Deploy when taken for an emergency.
                                </p>
                                <form method="POST" action="{{ route('inventory.crash-carts.deploy', $cart) }}">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700">
                                        Deploy for emergency
                                    </button>
                                </form>
                            </div>
                        @elseif ($status === 'deployed')
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-medium text-amber-900">Emergency in progress</p>
                                    <p class="mt-0.5 text-sm text-gray-600">
                                        Clinical use only — do not record inventory until the event ends.
                                    </p>
                                </div>
                                <form method="POST" action="{{ route('inventory.crash-carts.reconcile', $cart) }}">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-amber-700">
                                        Begin reconciliation
                                    </button>
                                </form>
                            </div>
                        @elseif ($status === 'reconciling')
                            <div class="space-y-5">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900">Complete reconciliation</h3>
                                    <p class="mt-1 text-sm text-gray-600">
                                        Document everything used during this deployment, then seal the cart and return it to service.
                                        A replenishment draft will be created for items recorded since deploy.
                                    </p>
                                </div>

                                {{-- Step 1 --}}
                                <div class="rounded-lg border border-gray-200 bg-white p-4">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">
                                                <span class="mr-2 inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-800">1</span>
                                                Record emergency usage
                                            </p>
                                            <p class="mt-1 ml-8 text-sm text-gray-500">
                                                Log each item and {{ strtolower(inventory_label('client')) }} under
                                                <span class="font-medium">Crash cart</span> context.
                                            </p>
                                            @if ($usageCount > 0)
                                                <p class="mt-2 ml-8 text-sm text-green-700">
                                                    {{ $usageCount }} {{ Str::plural('entry', $usageCount) }} recorded
                                                    ({{ rtrim(rtrim(number_format($usageQty, 2), '0'), '.') }} units total).
                                                </p>
                                            @else
                                                <p class="mt-2 ml-8 text-sm text-amber-700">
                                                    No usage recorded yet for this deployment.
                                                </p>
                                            @endif
                                        </div>
                                        <a href="{{ route('inventory.usage.index') }}"
                                           class="inline-flex shrink-0 items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                                            Open {{ inventory_label('usage_record') }}
                                        </a>
                                    </div>
                                </div>

                                {{-- Step 2 --}}
                                <div class="rounded-lg border border-gray-200 bg-white p-4">
                                    <p class="text-sm font-medium text-gray-900">
                                        <span class="mr-2 inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-800">2</span>
                                        Seal cart &amp; return to service
                                    </p>
                                    <p class="mt-1 ml-8 text-sm text-gray-500">
                                        After the cart is physically restocked and sealed, enter the new tamper-evident seal number below.
                                    </p>

                                    <form method="POST"
                                          action="{{ route('inventory.crash-carts.ready', $cart) }}"
                                          class="mt-4 ml-8 space-y-4 max-w-lg">
                                        @csrf
                                        <div>
                                            <label for="seal_number_{{ $cart->id }}" class="block text-sm font-medium text-gray-700">
                                                New seal number <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text"
                                                   id="seal_number_{{ $cart->id }}"
                                                   name="seal_number"
                                                   value="{{ old('seal_number') }}"
                                                   required
                                                   maxlength="100"
                                                   placeholder="e.g. SEAL-2026-001"
                                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-green-500 focus:ring-green-500">
                                            <p class="mt-1 text-xs text-gray-500">
                                                Printed on the tamper seal applied after restocking.
                                            </p>
                                        </div>
                                        <div>
                                            <label for="notes_{{ $cart->id }}" class="block text-sm font-medium text-gray-700">
                                                Notes <span class="font-normal text-gray-400">(optional)</span>
                                            </label>
                                            <textarea id="notes_{{ $cart->id }}"
                                                      name="notes"
                                                      rows="2"
                                                      maxlength="1000"
                                                      placeholder="Restock details, ward location, etc."
                                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-green-500 focus:ring-green-500">{{ old('notes') }}</textarea>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-3 pt-1">
                                            <button type="submit"
                                                    class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700">
                                                Complete reconciliation
                                            </button>
                                            <p class="text-xs text-gray-500">
                                                Sets status to <span class="font-medium">Ready</span> and opens a replenishment draft if usage was recorded.
                                            </p>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-12 text-center">
                    <p class="text-sm font-medium text-gray-900">No crash carts configured</p>
                    <p class="mt-1 text-sm text-gray-500">
                        Create a Satellite {{ strtolower(inventory_label('store')) }} and mark it as a crash cart under Manage Stores.
                    </p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
