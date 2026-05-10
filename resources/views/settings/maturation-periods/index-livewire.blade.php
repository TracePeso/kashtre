<x-app-layout>
    @php
        $activeTab = request()->query('tab', 'system-defaults');
        if (! in_array($activeTab, ['system-defaults', 'entities', 'service-charges'], true)) {
            $activeTab = 'system-defaults';
        }
        $systemDefaultsTabUrl = route('maturation-periods.index', ['tab' => 'system-defaults']);
        $entitiesTabUrl = route('maturation-periods.index', ['tab' => 'entities']);
        $serviceChargesTabUrl = route('maturation-periods.index', ['tab' => 'service-charges']);

        $maturationDefaultLabels = $maturationDefaultLabels ?? [
            'insurance' => 'Insurance',
            'credit_arrangement' => 'Credit Arrangement',
            'mobile_money' => 'Mobile Money',
            'v_card' => 'V Card (Virtual Card)',
            'p_card' => 'P Card (Physical Card)',
            'bank_transfer' => 'Bank Transfer',
            'cash' => 'Cash',
        ];
        $entityDefaults = $entityDefaultsMap ?? config('maturation_defaults.entity', []);
        $serviceChargeDefaults = $serviceChargeDefaultsMap ?? config('maturation_defaults.service_charge', []);
    @endphp
    <div class="min-h-screen bg-gray-50 py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="md:flex md:items-center md:justify-between mb-6">
                <div class="flex-1 min-w-0">
                    <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                        Maturation periods
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Platform-wide defaults, then per-entity overrides where needed.
                    </p>
                </div>
            </div>

            @if(session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-md text-sm">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-md text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex" aria-label="Maturation tabs">
                        <a href="{{ $systemDefaultsTabUrl }}"
                           id="tab-maturation-system-defaults"
                           class="maturation-tab-link flex-1 py-4 px-2 sm:px-4 text-center border-b-2 font-medium text-xs sm:text-sm {{ $activeTab === 'system-defaults' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                            System defaults
                        </a>
                        <a href="{{ $entitiesTabUrl }}"
                           id="tab-maturation-entities"
                           class="maturation-tab-link flex-1 py-4 px-2 sm:px-4 text-center border-b-2 font-medium text-xs sm:text-sm {{ $activeTab === 'entities' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                            Entities (payments)
                        </a>
                        <a href="{{ $serviceChargesTabUrl }}"
                           id="tab-maturation-service-charges"
                           class="maturation-tab-link flex-1 py-4 px-2 sm:px-4 text-center border-b-2 font-medium text-xs sm:text-sm {{ $activeTab === 'service-charges' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                            Service charges
                        </a>
                    </nav>
                </div>
                <div class="p-6">
                    @if ($activeTab === 'system-defaults')
                        <div id="maturation-panel-system-defaults" class="maturation-tab-panel">
                            @if(! empty($entityDefaults) || ! empty($serviceChargeDefaults))
                                <div class="rounded-lg border border-slate-200 bg-slate-50 shadow-sm overflow-hidden">
                                    <div class="border-b border-slate-200 bg-white px-4 py-3 sm:px-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <span class="text-sm font-medium text-slate-900">Days per payment method</span>
                                        @if(in_array('Edit Maturation Periods', auth()->user()->permissions ?? [], true))
                                            <a href="{{ route('maturation-periods.system-defaults.edit') }}"
                                               class="inline-flex items-center justify-center rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 shrink-0">
                                                Edit system defaults
                                            </a>
                                        @endif
                                    </div>
                                    <div class="grid gap-0 md:grid-cols-2 md:divide-x md:divide-slate-200">
                                        @if(! empty($entityDefaults))
                                            <div class="p-4 sm:p-5">
                                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-3">
                                                    Invoice / entity payments
                                                </p>
                                                <dl class="space-y-2">
                                                    @foreach ($entityDefaults as $method => $days)
                                                        <div class="flex justify-between gap-4 text-sm">
                                                            <dt class="text-slate-700">{{ $maturationDefaultLabels[$method] ?? ucfirst(str_replace('_', ' ', $method)) }}</dt>
                                                            <dd class="font-medium text-slate-900 tabular-nums whitespace-nowrap">
                                                                {{ (int) $days }} day{{ (int) $days === 1 ? '' : 's' }}
                                                            </dd>
                                                        </div>
                                                    @endforeach
                                                </dl>
                                            </div>
                                        @endif
                                        @if(! empty($serviceChargeDefaults))
                                            <div class="p-4 sm:p-5 border-t border-slate-200 md:border-t-0">
                                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-3">
                                                    Service charges / fees
                                                </p>
                                                <dl class="space-y-2">
                                                    @foreach ($serviceChargeDefaults as $method => $days)
                                                        <div class="flex justify-between gap-4 text-sm">
                                                            <dt class="text-slate-700">{{ $maturationDefaultLabels[$method] ?? ucfirst(str_replace('_', ' ', $method)) }}</dt>
                                                            <dd class="font-medium text-slate-900 tabular-nums whitespace-nowrap">
                                                                {{ (int) $days }} day{{ (int) $days === 1 ? '' : 's' }}
                                                            </dd>
                                                        </div>
                                                    @endforeach
                                                </dl>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <p class="text-sm text-gray-500">No default payment methods are configured.</p>
                            @endif
                        </div>
                    @elseif ($activeTab === 'entities')
                        <div id="maturation-panel-entities" class="maturation-tab-panel">
                            <p class="text-sm text-gray-600 mb-4">Invoice payments by entity and payment method.</p>
                            <livewire:maturation-periods.list-maturation-periods wire:key="maturation-periods-entities-table" />
                        </div>
                    @elseif ($activeTab === 'service-charges')
                        <div id="maturation-panel-service-charges" class="maturation-tab-panel">
                            <p class="text-sm text-gray-600 mb-4">Service fees by entity and payment method.</p>
                            <livewire:maturation-periods.list-service-charge-maturation-periods wire:key="maturation-periods-service-charges-table" />
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
