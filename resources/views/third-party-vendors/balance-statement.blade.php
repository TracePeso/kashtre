<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Balance Statement') }} - {{ $vendor['name'] }}
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('third-party-vendors.show', $vendor['id']) }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    Back to Vendor Details
                </a>
                <a href="{{ route('third-party-vendors.index') }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    All Vendors
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Vendor Information Card -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">{{ $vendor['name'] }}</h3>
                            <p class="text-sm text-gray-500">Code: <code class="bg-gray-100 px-2 py-1 rounded">{{ $vendor['code'] }}</code></p>
                            @if($vendor['email'])
                            <p class="text-sm text-gray-500">Email: {{ $vendor['email'] }}</p>
                            @endif
                            @if($vendor['phone'])
                            <p class="text-sm text-gray-500">Phone: {{ $vendor['phone'] }}</p>
                            @endif
                            <p class="text-sm text-gray-500">
                                Status: 
                                @if($vendor['is_active'])
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Active
                                    </span>
                                @else
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        Inactive
                                    </span>
                                @endif
                            </p>
                        </div>
                        <div class="text-right">
                            <div class="space-y-2">
                                <div>
                                    <p class="text-sm text-gray-500">Credit Limit</p>
                                    <p class="text-lg font-semibold text-gray-700">
                                        UGX {{ number_format((($thirdPartyPayer->credit_limit ?? 0) > 0
                                            ? (float) $thirdPartyPayer->credit_limit
                                            : (float) ($business->max_third_party_credit_limit ?? 0)), 2) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @php
                $availableBalance = (float) ($balanceSummary['available_balance'] ?? 0);
                $totalBalance = (float) ($balanceSummary['total_balance'] ?? 0);
            @endphp
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-sm font-medium text-gray-500 mb-2">Available balance</h3>
                        <p class="text-2xl font-bold {{ $availableBalance < 0 ? 'text-red-600' : ($availableBalance > 0 ? 'text-green-600' : 'text-gray-700') }}">
                            UGX {{ number_format($availableBalance, 2) }}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">Total credits minus total debits</p>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-sm font-medium text-gray-500 mb-2">Total balance</h3>
                        <p class="text-2xl font-bold text-gray-700">UGX {{ number_format($totalBalance, 2) }}</p>
                        <p class="text-xs text-gray-500 mt-1">Available balance plus suspense</p>
                    </div>
                </div>
            </div>

            <!-- Balance Statement Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:justify-between sm:items-center mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">
                                {{ ($statementView ?? 'items') === 'items' ? 'Statement by item' : 'Statement by transaction' }}
                            </h3>
                            <p class="text-sm text-gray-500 mt-1">
                                Default view itemizes ledger lines against invoice items where possible; switch to transactions for raw ledger rows.
                            </p>
                        </div>
                        <div class="flex rounded-lg border border-gray-200 p-1 bg-gray-50 shrink-0">
                            <a href="{{ route('third-party-vendors.balance-statement', ['vendorId' => $vendor['id'], 'view' => 'items']) }}"
                               class="px-4 py-2 text-sm font-medium rounded-md {{ ($statementView ?? 'items') === 'items' ? 'bg-white shadow text-blue-700' : 'text-gray-600 hover:text-gray-900' }}">
                                By item
                            </a>
                            <a href="{{ route('third-party-vendors.balance-statement', ['vendorId' => $vendor['id'], 'view' => 'transactions']) }}"
                               class="px-4 py-2 text-sm font-medium rounded-md {{ ($statementView ?? '') === 'transactions' ? 'bg-white shadow text-blue-700' : 'text-gray-600 hover:text-gray-900' }}">
                                By transaction
                            </a>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 mb-4">
                        Filament table: search and column controls apply to the <strong>transactions</strong> view. The <strong>by item</strong> view paginates ledger batches (50 histories per page); debit lines are split per invoice item on each page.
                    </p>

                    <div class="overflow-x-auto filament-tables-wrapper">
                        <livewire:third-party-vendor-balance-statement-table
                            :third-party-payer-id="$thirdPartyPayer->id"
                            :statement-view="$statementView"
                            wire:key="tp-vendor-bs-{{ $thirdPartyPayer->id }}-{{ $statementView }}"
                        />
                    </div>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Account Information</h3>
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Third-Party Payer ID</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $thirdPartyPayer->id }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Account Type</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ ucfirst(str_replace('_', ' ', $thirdPartyPayer->type)) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Account Status</dt>
                            <dd class="mt-1">
                                <span class="px-2 py-1 text-xs rounded-full {{ $thirdPartyPayer->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ ucfirst($thirdPartyPayer->status) }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created At</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $thirdPartyPayer->created_at->format('M d, Y H:i:s') }}</dd>
                        </div>
                        @if($thirdPartyPayer->notes)
                        <div class="md:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">Notes</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $thirdPartyPayer->notes }}</dd>
                        </div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
