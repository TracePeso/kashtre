<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <nav class="flex" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-4">
                        <li>
                            <div>
                                <a href="{{ route('bank-schedules.index') }}" class="text-gray-400 hover:text-gray-500">
                                    <svg class="flex-shrink-0 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                                    </svg>
                                    <span class="sr-only">Bank Schedules</span>
                                </a>
                            </div>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <svg class="flex-shrink-0 h-5 w-5 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="ml-4 text-sm font-medium text-gray-500">View</span>
                            </div>
                        </li>
                    </ol>
                </nav>
                <h2 class="mt-2 text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                    View Bank Schedule
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $bankSchedule->client_name }} - {{ $bankSchedule->business->name }}
                </p>
            </div>
        </div>

        <!-- Content -->
        <div class="mt-8">
            <div class="bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="grid grid-cols-1 gap-6">
                        <!-- Basic Information -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Basic Information</h3>
                            <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Business</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $bankSchedule->business->name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Client Name</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $bankSchedule->client_name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Amount</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-semibold">{{ number_format($bankSchedule->amount, 2) }} UGX</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Withdrawal Charge</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-semibold">{{ number_format($bankSchedule->withdrawal_charge ?? 0, 2) }} UGX</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Total</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-semibold text-blue-600">{{ number_format(($bankSchedule->amount ?? 0) + ($bankSchedule->withdrawal_charge ?? 0), 2) }} UGX</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                                    <dd class="mt-1">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            @if($bankSchedule->status === 'processed') bg-green-100 text-green-800
                                            @elseif($bankSchedule->status === 'pending') bg-yellow-100 text-yellow-800
                                            @else bg-red-100 text-red-800
                                            @endif">
                                            {{ ucfirst($bankSchedule->status) }}
                                        </span>
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Bank Information -->
                        <div class="border-t border-gray-200 pt-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Bank Information</h3>
                            <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Bank Name</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $bankSchedule->bank_name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Bank Account</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $bankSchedule->bank_account }}</dd>
                                </div>
                                @if($bankSchedule->reference_id)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Reference ID</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $bankSchedule->reference_id }}</dd>
                                </div>
                                @endif
                            </dl>
                        </div>

                        <!-- Withdrawal Request -->
                        @if($bankSchedule->withdrawalRequest)
                        <div class="border-t border-gray-200 pt-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Related Withdrawal Request</h3>
                            <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Request ID</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <a href="{{ route('withdrawal-requests.show', $bankSchedule->withdrawalRequest) }}" 
                                           class="text-blue-600 hover:text-blue-800 font-mono">
                                            {{ $bankSchedule->withdrawalRequest->uuid }}
                                        </a>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Amount</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ number_format($bankSchedule->withdrawalRequest->amount, 2) }} UGX</dd>
                                </div>
                            </dl>
                        </div>
                        @endif

                        <!-- Metadata -->
                        <div class="border-t border-gray-200 pt-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Metadata</h3>
                            <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Created At</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $bankSchedule->created_at->inOperationalTimezone()->format('M d, Y H:i:s') }}</dd>
                                </div>
                                @if($bankSchedule->creator)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Created By</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $bankSchedule->creator->name }}</dd>
                                </div>
                                @endif
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Updated At</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $bankSchedule->updated_at->inOperationalTimezone()->format('M d, Y H:i:s') }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</x-app-layout>


