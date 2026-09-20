<x-app-layout>
    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <!-- Header Section -->
            <div class="bg-gradient-to-r from-[#011478] to-[#1e40af] rounded-lg shadow-lg mb-8">
                <div class="px-6 py-8">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="p-3 bg-white/10 rounded-lg">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <h1 class="text-3xl font-bold text-white">Withdrawal Setting Details</h1>
                                <p class="text-blue-100 mt-1">{{ $withdrawalSetting->business->name ?? 'Unknown Business' }}</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <!-- Status Badge -->
                            <span class="inline-flex px-4 py-2 text-sm font-semibold rounded-full {{ $withdrawalSetting->is_active ? 'bg-green-500 text-white' : 'bg-red-500 text-white' }}">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                {{ $withdrawalSetting->is_active ? 'Active' : 'Inactive' }}
                            </span>
                            @if(in_array('Edit Withdrawal Settings', (array) $permissions ?? []))
                            <a href="{{ route('withdrawal-settings.edit', $withdrawalSetting->uuid) }}" class="inline-flex items-center px-4 py-2 bg-white text-[#011478] text-sm font-semibold rounded-md hover:bg-gray-100 transition duration-150">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                                Edit Setting
                            </a>
                            @endif
                            <a href="{{ route('withdrawal-settings.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-semibold rounded-md hover:bg-gray-700 transition duration-150">
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
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-blue-100 text-sm font-medium">Minimum Amount</p>
                                <p class="text-2xl font-bold">UGX {{ number_format($withdrawalSetting->minimum_withdrawal_amount) }}</p>
                            </div>
                            <svg class="w-8 h-8 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-green-100 text-sm font-medium">Free Withdrawals</p>
                                <p class="text-2xl font-bold">{{ $withdrawalSetting->number_of_free_withdrawals_per_day }}</p>
                                <p class="text-green-100 text-xs">per day</p>
                            </div>
                            <svg class="w-8 h-8 text-green-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-purple-100 text-sm font-medium">Business Approvers</p>
                                <p class="text-2xl font-bold">3 Steps</p>
                                <p class="text-purple-100 text-xs">Initiator → Authorizer → Approver</p>
                            </div>
                            <svg class="w-8 h-8 text-purple-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                            </svg>
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-orange-100 text-sm font-medium">Kashtre Approvers</p>
                                <p class="text-2xl font-bold">3 Steps</p>
                                <p class="text-orange-100 text-xs">Initiator → Authorizer → Approver</p>
                            </div>
                            <svg class="w-8 h-8 text-orange-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
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
                                <span class="text-sm font-semibold text-gray-900">{{ $withdrawalSetting->business->name ?? 'N/A' }}</span>
                            </div>
                            <div class="flex items-center justify-between py-3 border-b border-gray-100">
                                <span class="text-sm font-medium text-gray-500">Business Email</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $withdrawalSetting->business->email ?? 'N/A' }}</span>
                            </div>
                            <div class="flex items-center justify-between py-3 border-b border-gray-100">
                                <span class="text-sm font-medium text-gray-500">Business Phone</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $withdrawalSetting->business->phone ?? 'N/A' }}</span>
                            </div>
                            <div class="flex items-center justify-between py-3">
                                <span class="text-sm font-medium text-gray-500">Business Type</span>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $withdrawalSetting->business_id == 1 ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $withdrawalSetting->business_id == 1 ? 'Kashtre (Super Business)' : 'Regular Business' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Withdrawal Configuration -->
                    <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900 mb-6 flex items-center">
                            <div class="p-2 bg-green-100 rounded-lg mr-3">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                            </div>
                            Withdrawal Configuration
                        </h3>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between py-3 border-b border-gray-100">
                                <span class="text-sm font-medium text-gray-500">Withdrawal Type</span>
                                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full {{ $withdrawalSetting->withdrawal_type === 'express' ? 'bg-orange-100 text-orange-800' : 'bg-blue-100 text-blue-800' }}">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                    {{ ucfirst($withdrawalSetting->withdrawal_type) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between py-3 border-b border-gray-100">
                                <span class="text-sm font-medium text-gray-500">Minimum Withdrawal Amount</span>
                                <span class="text-sm font-semibold text-gray-900">UGX {{ number_format($withdrawalSetting->minimum_withdrawal_amount) }}</span>
                            </div>
                            <div class="flex items-center justify-between py-3 border-b border-gray-100">
                                <span class="text-sm font-medium text-gray-500">Free Withdrawals Per Day</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $withdrawalSetting->number_of_free_withdrawals_per_day }}</span>
                            </div>
                            <div class="flex items-center justify-between py-3 border-b border-gray-100">
                                <span class="text-sm font-medium text-gray-500">Business Initiators Range</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $withdrawalSetting->min_business_initiators }}-{{ $withdrawalSetting->max_business_initiators }}</span>
                            </div>
                            <div class="flex items-center justify-between py-3 border-b border-gray-100">
                                <span class="text-sm font-medium text-gray-500">Business Authorizers Range</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $withdrawalSetting->min_business_authorizers }}-{{ $withdrawalSetting->max_business_authorizers }}</span>
                            </div>
                            <div class="flex items-center justify-between py-3 border-b border-gray-100">
                                <span class="text-sm font-medium text-gray-500">Business Approvers Range</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $withdrawalSetting->min_business_approvers }}-{{ $withdrawalSetting->max_business_approvers }}</span>
                            </div>
                            <div class="flex items-center justify-between py-3 border-b border-gray-100">
                                <span class="text-sm font-medium text-gray-500">Kashtre Initiators Range</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $withdrawalSetting->min_kashtre_initiators }}-{{ $withdrawalSetting->max_kashtre_initiators }}</span>
                            </div>
                            <div class="flex items-center justify-between py-3 border-b border-gray-100">
                                <span class="text-sm font-medium text-gray-500">Kashtre Authorizers Range</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $withdrawalSetting->min_kashtre_authorizers }}-{{ $withdrawalSetting->max_kashtre_authorizers }}</span>
                            </div>
                            <div class="flex items-center justify-between py-3">
                                <span class="text-sm font-medium text-gray-500">Kashtre Approvers Range</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $withdrawalSetting->min_kashtre_approvers }}-{{ $withdrawalSetting->max_kashtre_approvers }}</span>
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
                                <p class="text-sm font-semibold text-gray-900">{{ $withdrawalSetting->created_at->inOperationalTimezone()->format('M d, Y') }}</p>
                                <p class="text-xs text-gray-500">{{ $withdrawalSetting->created_at->inOperationalTimezone()->format('H:i:s') }}</p>
                            </div>
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <p class="text-sm font-medium text-gray-500 mb-2">Last Updated</p>
                                <p class="text-sm font-semibold text-gray-900">{{ $withdrawalSetting->updated_at->inOperationalTimezone()->format('M d, Y') }}</p>
                                <p class="text-xs text-gray-500">{{ $withdrawalSetting->updated_at->inOperationalTimezone()->format('H:i:s') }}</p>
                            </div>
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <p class="text-sm font-medium text-gray-500 mb-2">Setting ID</p>
                                <p class="text-xs font-mono text-gray-900 break-all">{{ $withdrawalSetting->uuid }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3-Level Approval System Section -->
                <div class="mt-8">
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-gray-900 mb-2">3-Level Approval System</h2>
                        <p class="text-gray-600">Detailed breakdown of all approval levels and assigned approvers</p>
                    </div>

                    <!-- Business Approvers -->
                    <div class="mb-8">
                        <h3 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                            <div class="p-2 bg-purple-100 rounded-lg mr-3">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                            Business Approvers (3-Step Process)
                            <span class="ml-3 inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-purple-100 text-purple-800">
                                {{ $withdrawalSetting->businessApprovers->count() }} total assigned
                            </span>
                        </h3>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Business Initiators -->
                            <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                                <div class="px-4 py-3 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-blue-100">
                                    <h4 class="text-lg font-semibold text-gray-900 flex items-center">
                                        <div class="p-1 bg-blue-100 rounded mr-2">
                                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                            </svg>
                                        </div>
                                        Level 1: Initiators
                                        <span class="ml-2 inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                            {{ $withdrawalSetting->businessInitiators->count() }}
                                        </span>
                                    </h4>
                                    <p class="text-xs text-blue-600 mt-1">{{ $withdrawalSetting->min_business_initiators }}-{{ $withdrawalSetting->max_business_initiators }} range</p>
                                </div>
                                <div class="px-4 py-3">
                                    @if($withdrawalSetting->businessInitiators->count() > 0)
                                        <div class="space-y-2">
                                            @foreach($withdrawalSetting->businessInitiators as $approver)
                                                <div class="flex items-center p-2 bg-gray-50 rounded border">
                                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                        </svg>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-medium text-gray-900 truncate">
                                                            @if($approver->approver_type === 'user')
                                                                {{ $approver->approver->name ?? 'Unknown User' }}
                                                            @else
                                                                {{ $approver->approver->user->name ?? 'Unknown Contractor' }}
                                                            @endif
                                                        </p>
                                                        <p class="text-xs text-gray-500 truncate">{{ $approver->approver->email ?? 'No email' }}</p>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-center py-4">
                                            <p class="text-gray-500 text-sm italic">No initiators assigned</p>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Business Authorizers -->
                            <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                                <div class="px-4 py-3 border-b border-gray-200 bg-gradient-to-r from-yellow-50 to-yellow-100">
                                    <h4 class="text-lg font-semibold text-gray-900 flex items-center">
                                        <div class="p-1 bg-yellow-100 rounded mr-2">
                                            <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        </div>
                                        Level 2: Authorizers
                                        <span class="ml-2 inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            {{ $withdrawalSetting->businessAuthorizers->count() }}
                                        </span>
                                    </h4>
                                    <p class="text-xs text-yellow-600 mt-1">{{ $withdrawalSetting->min_business_authorizers }}-{{ $withdrawalSetting->max_business_authorizers }} range</p>
                                </div>
                                <div class="px-4 py-3">
                                    @if($withdrawalSetting->businessAuthorizers->count() > 0)
                                        <div class="space-y-2">
                                            @foreach($withdrawalSetting->businessAuthorizers as $approver)
                                                <div class="flex items-center p-2 bg-gray-50 rounded border">
                                                    <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center mr-3">
                                                        <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                        </svg>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-medium text-gray-900 truncate">
                                                            @if($approver->approver_type === 'user')
                                                                {{ $approver->approver->name ?? 'Unknown User' }}
                                                            @else
                                                                {{ $approver->approver->user->name ?? 'Unknown Contractor' }}
                                                            @endif
                                                        </p>
                                                        <p class="text-xs text-gray-500 truncate">{{ $approver->approver->email ?? 'No email' }}</p>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-center py-4">
                                            <p class="text-gray-500 text-sm italic">No authorizers assigned</p>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Business Approvers -->
                            <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                                <div class="px-4 py-3 border-b border-gray-200 bg-gradient-to-r from-green-50 to-green-100">
                                    <h4 class="text-lg font-semibold text-gray-900 flex items-center">
                                        <div class="p-1 bg-green-100 rounded mr-2">
                                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                        Level 3: Approvers
                                        <span class="ml-2 inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            {{ $withdrawalSetting->businessApprovers->count() }}
                                        </span>
                                    </h4>
                                    <p class="text-xs text-green-600 mt-1">{{ $withdrawalSetting->min_business_approvers }}-{{ $withdrawalSetting->max_business_approvers }} range</p>
                                </div>
                                <div class="px-4 py-3">
                                    @if($withdrawalSetting->businessApprovers->count() > 0)
                                        <div class="space-y-2">
                                            @foreach($withdrawalSetting->businessApprovers as $approver)
                                                <div class="flex items-center p-2 bg-gray-50 rounded border">
                                                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                                        <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                        </svg>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-medium text-gray-900 truncate">
                                                            @if($approver->approver_type === 'user')
                                                                {{ $approver->approver->name ?? 'Unknown User' }}
                                                            @else
                                                                {{ $approver->approver->user->name ?? 'Unknown Contractor' }}
                                                            @endif
                                                        </p>
                                                        <p class="text-xs text-gray-500 truncate">{{ $approver->approver->email ?? 'No email' }}</p>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-center py-4">
                                            <p class="text-gray-500 text-sm italic">No approvers assigned</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Kashtre Approvers -->
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                            <div class="p-2 bg-orange-100 rounded-lg mr-3">
                                <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                            Kashtre Approvers (3-Step Process)
                            <span class="ml-3 inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-orange-100 text-orange-800">
                                {{ $withdrawalSetting->kashtreApprovers->count() }} total assigned
                            </span>
                        </h3>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Kashtre Initiators -->
                            <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                                <div class="px-4 py-3 border-b border-gray-200 bg-gradient-to-r from-red-50 to-red-100">
                                    <h4 class="text-lg font-semibold text-gray-900 flex items-center">
                                        <div class="p-1 bg-red-100 rounded mr-2">
                                            <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                            </svg>
                                        </div>
                                        Level 1: Initiators
                                        <span class="ml-2 inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                            {{ $withdrawalSetting->kashtreInitiators->count() }}
                                        </span>
                                    </h4>
                                    <p class="text-xs text-red-600 mt-1">{{ $withdrawalSetting->min_kashtre_initiators }}-{{ $withdrawalSetting->max_kashtre_initiators }} range</p>
                                </div>
                                <div class="px-4 py-3">
                                    @if($withdrawalSetting->kashtreInitiators->count() > 0)
                                        <div class="space-y-2">
                                            @foreach($withdrawalSetting->kashtreInitiators as $approver)
                                                <div class="flex items-center p-2 bg-gray-50 rounded border">
                                                    <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center mr-3">
                                                        <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                        </svg>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-medium text-gray-900 truncate">
                                                            @if($approver->approver_type === 'user')
                                                                {{ $approver->approver->name ?? 'Unknown User' }}
                                                            @else
                                                                {{ $approver->approver->user->name ?? 'Unknown Contractor' }}
                                                            @endif
                                                        </p>
                                                        <p class="text-xs text-gray-500 truncate">{{ $approver->approver->email ?? 'No email' }}</p>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-center py-4">
                                            <p class="text-gray-500 text-sm italic">No initiators assigned</p>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Kashtre Authorizers -->
                            <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                                <div class="px-4 py-3 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-indigo-100">
                                    <h4 class="text-lg font-semibold text-gray-900 flex items-center">
                                        <div class="p-1 bg-indigo-100 rounded mr-2">
                                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        </div>
                                        Level 2: Authorizers
                                        <span class="ml-2 inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800">
                                            {{ $withdrawalSetting->kashtreAuthorizers->count() }}
                                        </span>
                                    </h4>
                                    <p class="text-xs text-indigo-600 mt-1">{{ $withdrawalSetting->min_kashtre_authorizers }}-{{ $withdrawalSetting->max_kashtre_authorizers }} range</p>
                                </div>
                                <div class="px-4 py-3">
                                    @if($withdrawalSetting->kashtreAuthorizers->count() > 0)
                                        <div class="space-y-2">
                                            @foreach($withdrawalSetting->kashtreAuthorizers as $approver)
                                                <div class="flex items-center p-2 bg-gray-50 rounded border">
                                                    <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center mr-3">
                                                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                        </svg>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-medium text-gray-900 truncate">
                                                            @if($approver->approver_type === 'user')
                                                                {{ $approver->approver->name ?? 'Unknown User' }}
                                                            @else
                                                                {{ $approver->approver->user->name ?? 'Unknown Contractor' }}
                                                            @endif
                                                        </p>
                                                        <p class="text-xs text-gray-500 truncate">{{ $approver->approver->email ?? 'No email' }}</p>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-center py-4">
                                            <p class="text-gray-500 text-sm italic">No authorizers assigned</p>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Kashtre Approvers -->
                            <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                                <div class="px-4 py-3 border-b border-gray-200 bg-gradient-to-r from-teal-50 to-teal-100">
                                    <h4 class="text-lg font-semibold text-gray-900 flex items-center">
                                        <div class="p-1 bg-teal-100 rounded mr-2">
                                            <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                        Level 3: Approvers
                                        <span class="ml-2 inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-teal-100 text-teal-800">
                                            {{ $withdrawalSetting->kashtreApprovers->count() }}
                                        </span>
                                    </h4>
                                    <p class="text-xs text-teal-600 mt-1">{{ $withdrawalSetting->min_kashtre_approvers }}-{{ $withdrawalSetting->max_kashtre_approvers }} range</p>
                                </div>
                                <div class="px-4 py-3">
                                    @if($withdrawalSetting->kashtreApprovers->count() > 0)
                                        <div class="space-y-2">
                                            @foreach($withdrawalSetting->kashtreApprovers as $approver)
                                                <div class="flex items-center p-2 bg-gray-50 rounded border">
                                                    <div class="w-8 h-8 bg-teal-100 rounded-full flex items-center justify-center mr-3">
                                                        <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                        </svg>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-medium text-gray-900 truncate">
                                                            @if($approver->approver_type === 'user')
                                                                {{ $approver->approver->name ?? 'Unknown User' }}
                                                            @else
                                                                {{ $approver->approver->user->name ?? 'Unknown Contractor' }}
                                                            @endif
                                                        </p>
                                                        <p class="text-xs text-gray-500 truncate">{{ $approver->approver->email ?? 'No email' }}</p>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-center py-4">
                                            <p class="text-gray-500 text-sm italic">No approvers assigned</p>
                                        </div>
                                    @endif
                                </div>
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
                                Last updated {{ $withdrawalSetting->updated_at->diffForHumans() }}
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            @if(in_array('Edit Withdrawal Settings', (array) $permissions ?? []))
                            <a href="{{ route('withdrawal-settings.edit', $withdrawalSetting->uuid) }}" class="inline-flex items-center px-6 py-3 bg-[#011478] text-white text-sm font-semibold rounded-lg hover:bg-[#011478]/90 transition duration-150 shadow-sm">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                                Edit Setting
                            </a>
                            @endif
                            <a href="{{ route('withdrawal-settings.index') }}" class="inline-flex items-center px-6 py-3 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition duration-150 shadow-sm">
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
