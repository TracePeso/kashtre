<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800 dark:text-white">Contractor Profile Details</h2>
                    <div class="flex space-x-2">
                        <a href="{{ route('contractor-profiles.edit', $contractorProfile->id) }}" 
                           class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Edit
                        </a>
                        <a href="{{ route('contractor-profiles.index') }}" 
                           class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                            Back to List
                        </a>
                    </div>
                </div>

                @if(session('success'))
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        {{ session('success') }}
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Basic Information -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white border-b pb-2">Basic Information</h3>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">UUID</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $contractorProfile->uuid }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Business</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $contractorProfile->business->name ?? 'N/A' }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">User</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                {{ $contractorProfile->user->name ?? 'N/A' }}
                                @if($contractorProfile->user)
                                    <br><span class="text-gray-500 text-xs">{{ $contractorProfile->user->email }}</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    <!-- Bank Information -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white border-b pb-2">Bank Information</h3>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Bank Name</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $contractorProfile->bank_name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Account Name</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $contractorProfile->account_name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Account Number</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $contractorProfile->account_number }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Account Balance</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">UGX {{ number_format($contractorProfile->account_balance, 2) }}</p>
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white border-b pb-2">Additional Information</h3>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Kashtre Account Number</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $contractorProfile->kashtre_account_number ?? 'N/A' }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Signing Qualifications</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $contractorProfile->signing_qualifications ?? 'N/A' }}</p>
                        </div>
                    </div>

                    <!-- Timestamps -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white border-b pb-2">Timestamps</h3>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Created At</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $contractorProfile->created_at->inOperationalTimezone()->format('F j, Y g:i A') }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Updated At</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $contractorProfile->updated_at->inOperationalTimezone()->format('F j, Y g:i A') }}</p>
                        </div>

                        @if($contractorProfile->deleted_at)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Deleted At</label>
                                <p class="mt-1 text-sm text-red-600">{{ $contractorProfile->deleted_at->inOperationalTimezone()->format('F j, Y g:i A') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout> 