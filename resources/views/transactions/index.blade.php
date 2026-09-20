<x-app-layout>
    <div class="py-12" x-data="{ showModal: false }" x-cloak>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-gray-800 dark:text-white">Manage Transactions</h2>
                    @if(!empty($operationalTime['ianaId'] ?? null))
                        <p class="text-sm text-gray-500">
                            Times shown in {{ $operationalTime['ianaId'] }}
                            ({{ $operationalTime['sourceLabel'] }}).
                            Business date {{ $operationalTime['businessDate'] }}.
                        </p>
                    @endif

                </div>

                @livewire('transactions.transactions')
            </div>
        </div>


    </div>
</x-app-layout>
