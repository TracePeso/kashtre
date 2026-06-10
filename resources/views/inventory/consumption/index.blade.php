<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">Daily Consumption</h2>
            <p class="mt-1 text-sm text-gray-500">
                Read-only log of automatically recorded usage from POS and service delivery.
                This data powers <strong>moving averages</strong>, <strong>stock-days</strong>, and <strong>order form</strong> suggestions on Monitor Stock.
            </p>
        </div>

        @include('inventory.partials.subnav')

        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white shadow sm:rounded-lg p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Total entries</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($summary['total_entries']) }}</p>
            </div>
            <div class="bg-white shadow sm:rounded-lg p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Items tracked</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($summary['distinct_items']) }}</p>
            </div>
            <div class="bg-white shadow sm:rounded-lg p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Last 30 days</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($summary['last_30_days']) }}</p>
            </div>
            <div class="bg-white shadow sm:rounded-lg p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Date range</p>
                <p class="mt-1 text-sm font-semibold text-gray-900">
                    @if($summary['date_from'] && $summary['date_to'])
                        {{ \Carbon\Carbon::parse($summary['date_from'])->format('M d, Y') }}
                        –
                        {{ \Carbon\Carbon::parse($summary['date_to'])->format('M d, Y') }}
                    @else
                        —
                    @endif
                </p>
            </div>
        </div>

        @if($summary['total_entries'] === 0)
            <div class="mt-4 bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded text-sm">
                No consumption has been recorded for your organisation yet. Entries are created automatically when goods are sold or issued.
                For test data on <strong>Exquisite Test Life</strong>, run:
                <code class="ml-1 text-xs bg-amber-100 px-1.5 py-0.5 rounded">php artisan db:seed --class=SampleHospitalConsumptionSeeder</code>
            </div>
        @endif

        <div class="mt-6 bg-white shadow sm:rounded-lg p-6">
            @livewire('inventory.list-daily-consumptions')
        </div>
    </div>
</div>
</x-app-layout>
