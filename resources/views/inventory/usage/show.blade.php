@php
    $billingLabel = match ($event->main_billing_status) {
        'pending' => 'Pending',
        'completed' => 'Invoiced',
        'failed' => 'Failed',
        'skipped' => 'Skipped',
        default => $event->main_billing_status ? ucfirst($event->main_billing_status) : '—',
    };
    $billingTone = match ($event->main_billing_status) {
        'completed' => 'bg-emerald-100 text-emerald-800',
        'pending' => 'bg-amber-100 text-amber-900',
        'failed' => 'bg-red-100 text-red-800',
        'skipped' => 'bg-gray-100 text-gray-700',
        default => 'bg-gray-100 text-gray-600',
    };
@endphp
<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <a href="{{ route('inventory.usage.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to Record usage</a>

        <div class="mt-4 md:flex md:items-start md:justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 tracking-tight">Usage event</h1>
                <p class="mt-1 text-sm text-gray-500 font-mono">{{ $event->uuid }}</p>
            </div>
            @if($event->billed_main_module && $event->main_billing_status === 'failed' && ! \App\Support\InventoryBusinessContext::isAdminBrowsing())
                <form method="POST" action="{{ route('inventory.usage.retry-billing', $event) }}" class="mt-4 md:mt-0">
                    @csrf
                    <button type="submit" class="rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        Retry billing
                    </button>
                </form>
            @endif
        </div>

        @include('inventory.partials.subnav')

        @if(session('success'))
            <div class="mt-4 rounded-md bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        @if($event->main_billing_status === 'failed' && $event->main_billing_error)
            <div class="mt-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                <p class="font-medium">Billing failed</p>
                <p class="mt-1 text-red-700">{{ $event->main_billing_error }}</p>
            </div>
        @endif

        <div class="mt-6 bg-white border border-gray-200 shadow-sm sm:rounded-lg overflow-hidden">
            <dl class="divide-y divide-gray-100 text-sm">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 px-4 py-3">
                    <dt class="text-gray-500">When</dt>
                    <dd class="sm:col-span-2 text-gray-900">{{ $event->occurred_at?->format('d M Y H:i') ?? '—' }}</dd>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 px-4 py-3">
                    <dt class="text-gray-500">Context</dt>
                    <dd class="sm:col-span-2 text-gray-900">{{ $event->contextLabel() }}</dd>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 px-4 py-3">
                    <dt class="text-gray-500">{{ inventory_label('client') }}</dt>
                    <dd class="sm:col-span-2 text-gray-900">
                        @if($event->client)
                            {{ $event->client->name }}
                            @if($event->client->client_id)
                                <span class="text-gray-500">[{{ $event->client->client_id }}]</span>
                            @endif
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 px-4 py-3">
                    <dt class="text-gray-500">{{ inventory_label('item') }}</dt>
                    <dd class="sm:col-span-2 text-gray-900">
                        {{ $event->item?->name ?? '—' }}
                        @if($event->item?->code)
                            <span class="block text-xs text-gray-500">{{ $event->item->code }}</span>
                        @endif
                    </dd>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 px-4 py-3">
                    <dt class="text-gray-500">Quantity</dt>
                    <dd class="sm:col-span-2 text-gray-900 tabular-nums">{{ rtrim(rtrim(number_format((float) $event->quantity, 2), '0'), '.') }}</dd>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 px-4 py-3">
                    <dt class="text-gray-500">Resolved via</dt>
                    <dd class="sm:col-span-2 text-gray-900">{{ $event->resolutionLabel() }}</dd>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 px-4 py-3">
                    <dt class="text-gray-500">{{ inventory_label('store') }}</dt>
                    <dd class="sm:col-span-2 text-gray-900">{{ $event->store?->name ?? '—' }}</dd>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 px-4 py-3">
                    <dt class="text-gray-500">Bill patient</dt>
                    <dd class="sm:col-span-2 text-gray-900">{{ $event->billed_main_module ? 'Yes' : 'No' }}</dd>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 px-4 py-3">
                    <dt class="text-gray-500">Billing</dt>
                    <dd class="sm:col-span-2">
                        @if($event->billed_main_module)
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $billingTone }}">{{ $billingLabel }}</span>
                            @if($event->invoice)
                                <a href="{{ route('invoices.show', $event->invoice) }}" class="ml-2 text-blue-600 hover:text-blue-800">
                                    {{ $event->invoice->invoice_number }}
                                </a>
                            @endif
                            @if($event->main_billed_at)
                                <span class="block mt-1 text-xs text-gray-500">Invoiced {{ $event->main_billed_at->format('d M Y H:i') }}</span>
                            @endif
                        @else
                            <span class="text-gray-500">Not applicable</span>
                        @endif
                    </dd>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 px-4 py-3">
                    <dt class="text-gray-500">Recorded by</dt>
                    <dd class="sm:col-span-2 text-gray-900">{{ $event->recordedBy?->name ?? '—' }}</dd>
                </div>
                @if($event->notes)
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 px-4 py-3">
                        <dt class="text-gray-500">Notes</dt>
                        <dd class="sm:col-span-2 text-gray-900 whitespace-pre-wrap">{{ $event->notes }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        @if(is_array($event->pool_allocations) && $event->pool_allocations !== [])
            <div class="mt-6 bg-white border border-gray-200 shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h2 class="text-sm font-medium text-gray-900">Approved Pool allocations</h2>
                </div>
                <ul class="divide-y divide-gray-100 px-4 py-2 text-sm text-gray-700">
                    @foreach($event->pool_allocations as $allocation)
                        <li class="py-2">
                            Pool line #{{ $allocation['pool_line_id'] ?? '—' }}
                            · {{ rtrim(rtrim(number_format((float) ($allocation['quantity'] ?? 0), 2), '0'), '.') }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @include('inventory.usage.partials.collect-payment', [
            'event' => $event,
            'canCollectPayment' => $canCollectPayment ?? false,
            'paymentMethods' => $paymentMethods ?? [],
            'defaultPhone' => $defaultPhone ?? null,
        ])
    </div>
</div>
</x-app-layout>
