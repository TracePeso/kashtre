<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
                @if(($activeTab ?? 'insurance-companies') === 'vendor-service-charges')
                    {{ __('Settings — Vendor service charges') }}
                @else
                    {{ __('Settings — Third party vendors') }}
                @endif
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if(session('success'))
                        <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-4 rounded">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-green-700">{{ session('success') }}</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Tabs: admin-only Kashtre setup (no external APIs) --}}
                    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
                        <nav class="-mb-px flex flex-wrap gap-2" aria-label="Settings tabs">
                            <a href="{{ route('settings.index', ['tab' => 'insurance-companies']) }}"
                               class="inline-flex items-center px-4 py-2.5 text-sm font-medium rounded-t-lg border-b-2 transition
                               {{ ($activeTab ?? '') === 'insurance-companies'
                                  ? 'border-blue-600 text-blue-600 dark:text-blue-400'
                                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200' }}">
                                Third party vendors
                            </a>
                            @if($canViewVendorChargesTab ?? false)
                                <a href="{{ route('settings.index', ['tab' => 'vendor-service-charges']) }}"
                                   class="inline-flex items-center px-4 py-2.5 text-sm font-medium rounded-t-lg border-b-2 transition
                                   {{ ($activeTab ?? '') === 'vendor-service-charges'
                                      ? 'border-blue-600 text-blue-600 dark:text-blue-400'
                                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200' }}">
                                    Vendor service charges
                                </a>
                            @endif
                        </nav>
                    </div>

                    {{-- Tab: Third party vendors --}}
                    <div class="{{ ($activeTab ?? 'insurance-companies') === 'insurance-companies' ? '' : 'hidden' }}">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Third party vendors</h3>
                            <a href="{{ route('insurance-companies.create') }}"
                               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700 transition duration-150">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Create Third Party Vendor
                            </a>
                        </div>

                        @if($insuranceCompanies->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Code</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Email</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Phone</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Registered</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($insuranceCompanies as $company)
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ $company->name }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    @if($company->code)
                                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-mono font-semibold bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-900/40 dark:text-blue-200 dark:border-blue-700">
                                                            {{ $company->code }}
                                                        </span>
                                                    @else
                                                        <span class="text-sm text-gray-400">N/A</span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $company->email ?? 'N/A' }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $company->phone ?? 'N/A' }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                    @if($company->third_party_business_id)
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200">Yes</span>
                                                    @else
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200">No</span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <a href="{{ route('insurance-companies.show', $company) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400">View</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-4">
                                {{ $insuranceCompanies->links() }}
                            </div>
                        @else
                            <div class="text-center py-12">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No third party vendors</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating a new third party vendor.</p>
                                <div class="mt-6">
                                    <a href="{{ route('insurance-companies.create') }}"
                                       class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                        Create Third Party Vendor
                                    </a>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Tab: Vendor service charges (global tiers per clinic — same idea as Entity Service Charges; Kashtre DB only) --}}
                    @if($canViewVendorChargesTab ?? false)
                        <div class="{{ ($activeTab ?? '') === 'vendor-service-charges' ? '' : 'hidden' }}">
                            @include('settings.partials.vendor-service-charge-defaults-editor')

                            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Per clinic</h3>
                                @if((int)(Auth::user()->business_id ?? 0) === 1 || in_array('Manage Service Charges', Auth::user()->permissions ?? []))
                                    <a href="{{ route('third-party-vendor-service-charges.create') }}"
                                       class="text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                        + Configure clinic
                                    </a>
                                @endif
                            </div>

                            @livewire('third-party-vendor-service-charges-table')
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
