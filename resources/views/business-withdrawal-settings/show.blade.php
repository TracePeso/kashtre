<x-app-layout>
    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <!-- Header Section -->
            <div class="bg-gradient-to-r from-[#011478] to-[#1e40af] rounded-lg shadow-lg mb-8">
                <div class="px-6 py-8">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="p-3 bg-white/10 rounded-lg">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <h1 class="text-3xl font-bold text-white">Business Withdrawal Charge Details</h1>
                                <p class="text-blue-100 mt-1">{{ $businessWithdrawalSetting->business->name ?? 'Unknown Business' }}</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <!-- Status Badge -->
                            <span class="inline-flex px-4 py-2 text-sm font-semibold rounded-full {{ $businessWithdrawalSetting->is_active ? 'bg-green-500 text-white' : 'bg-red-500 text-white' }}">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                {{ $businessWithdrawalSetting->is_active ? 'Active' : 'Inactive' }}
                            </span>
                            @if(in_array('Edit Business Withdrawal Charges', (array) $permissions ?? []))
                            <a href="{{ route('business-withdrawal-settings.edit', $businessWithdrawalSetting->id) }}" class="inline-flex items-center px-4 py-2 bg-white text-[#011478] text-sm font-semibold rounded-md hover:bg-gray-100 transition duration-150">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                                Edit Charge
                            </a>
                            @endif
                            <a href="{{ route('business-withdrawal-settings.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-semibold rounded-md hover:bg-gray-700 transition duration-150">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                </svg>
                                Back to List
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">

                <!-- Key Metrics Overview -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-blue-100 text-sm font-medium">Lower Bound</p>
                                <p class="text-2xl font-bold">UGX {{ number_format($businessWithdrawalSetting->lower_bound) }}</p>
                            </div>
                            <svg class="w-8 h-8 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-green-100 text-sm font-medium">Upper Bound</p>
                                <p class="text-2xl font-bold">UGX {{ number_format($businessWithdrawalSetting->upper_bound) }}</p>
                            </div>
                            <svg class="w-8 h-8 text-green-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                            </svg>
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-purple-100 text-sm font-medium">Charge Amount</p>
                                <p class="text-2xl font-bold">
                                    @if($businessWithdrawalSetting->charge_type === 'percentage')
                                        {{ $businessWithdrawalSetting->charge_amount }}%
                                    @else
                                        UGX {{ number_format($businessWithdrawalSetting->charge_amount) }}
                                    @endif
                                </p>
                                <p class="text-purple-100 text-xs">{{ ucfirst($businessWithdrawalSetting->charge_type) }}</p>
                            </div>
                            <svg class="w-8 h-8 text-purple-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Details Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Business Information -->
                    <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900 mb-6 flex items-center">
                            <div class="p-2 bg-blue-100 rounded-lg mr-3">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                            Business Information
                        </h3>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between py-3 border-b border-gray-100">
                                <span class="text-sm font-medium text-gray-500">Business Name</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $businessWithdrawalSetting->business->name ?? 'N/A' }}</span>
                            </div>
                            <div class="flex items-center justify-between py-3 border-b border-gray-100">
                                <span class="text-sm font-medium text-gray-500">Business Email</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $businessWithdrawalSetting->business->email ?? 'N/A' }}</span>
                            </div>
                            <div class="flex items-center justify-between py-3">
                                <span class="text-sm font-medium text-gray-500">Business Phone</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $businessWithdrawalSetting->business->phone ?? 'N/A' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Charge Configuration -->
                    <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900 mb-6 flex items-center">
                            <div class="p-2 bg-green-100 rounded-lg mr-3">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                            </div>
                            Charge Configuration
                        </h3>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between py-3 border-b border-gray-100">
                                <span class="text-sm font-medium text-gray-500">Charge Type</span>
                                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full {{ $businessWithdrawalSetting->charge_type === 'percentage' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                    {{ ucfirst($businessWithdrawalSetting->charge_type) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between py-3 border-b border-gray-100">
                                <span class="text-sm font-medium text-gray-500">Amount Range</span>
                                <span class="text-sm font-semibold text-gray-900">
                                    UGX {{ number_format($businessWithdrawalSetting->lower_bound) }} - {{ number_format($businessWithdrawalSetting->upper_bound) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between py-3">
                                <span class="text-sm font-medium text-gray-500">Description</span>
                                <span class="text-sm text-gray-900">{{ $businessWithdrawalSetting->description ?? 'No description' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- System Information -->
                    <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm lg:col-span-2">
                        <h3 class="text-lg font-semibold text-gray-900 mb-6 flex items-center">
                            <div class="p-2 bg-gray-100 rounded-lg mr-3">
                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            System Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <p class="text-sm font-medium text-gray-500 mb-2">Created At</p>
                                <p class="text-sm font-semibold text-gray-900">{{ $businessWithdrawalSetting->created_at->inOperationalTimezone()->format('M d, Y') }}</p>
                                <p class="text-xs text-gray-500">{{ $businessWithdrawalSetting->created_at->inOperationalTimezone()->format('H:i:s') }}</p>
                            </div>
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <p class="text-sm font-medium text-gray-500 mb-2">Last Updated</p>
                                <p class="text-sm font-semibold text-gray-900">{{ $businessWithdrawalSetting->updated_at->inOperationalTimezone()->format('M d, Y') }}</p>
                                <p class="text-xs text-gray-500">{{ $businessWithdrawalSetting->updated_at->inOperationalTimezone()->format('H:i:s') }}</p>
                            </div>
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <p class="text-sm font-medium text-gray-500 mb-2">Created By</p>
                                <p class="text-sm font-semibold text-gray-900">{{ $businessWithdrawalSetting->creator->name ?? 'Unknown' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-8 bg-gray-50 rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="flex items-center text-sm text-gray-600">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Last updated {{ $businessWithdrawalSetting->updated_at->diffForHumans() }}
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            @if(in_array('Edit Business Withdrawal Charges', (array) $permissions ?? []))
                            <a href="{{ route('business-withdrawal-settings.edit', $businessWithdrawalSetting->id) }}" class="inline-flex items-center px-6 py-3 bg-[#011478] text-white text-sm font-semibold rounded-lg hover:bg-[#011478]/90 transition duration-150 shadow-sm">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                                Edit Charge
                            </a>
                            @endif
                            <a href="{{ route('business-withdrawal-settings.index') }}" class="inline-flex items-center px-6 py-3 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition duration-150 shadow-sm">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                </svg>
                                Back to List
                            </a>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

