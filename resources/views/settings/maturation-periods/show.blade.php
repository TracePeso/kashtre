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
                                <a href="{{ route('maturation-periods.index', ['tab' => 'entities']) }}" class="text-gray-400 hover:text-gray-500">
                                    <svg class="flex-shrink-0 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                                    </svg>
                                    <span class="sr-only">Maturation Periods</span>
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
                    View Payment Method and Maturation Period
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $maturationPeriod->business->name }} - {{ $maturationPeriod->payment_method_name }}
                </p>
            </div>
            @if(in_array('Edit Maturation Periods', auth()->user()->permissions ?? []))
            <div class="mt-4 flex md:mt-0 md:ml-4">
                <a href="{{ route('maturation-periods.edit', $maturationPeriod) }}" 
                   class="ml-3 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Edit
                </a>
            </div>
            @endif
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
                                    <dd class="mt-1 text-sm text-gray-900">{{ $maturationPeriod->business->name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Payment Method</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $maturationPeriod->payment_method_name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Maturation Period</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $maturationPeriod->formatted_maturation_period }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                                    <dd class="mt-1">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $maturationPeriod->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $maturationPeriod->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </dd>
                                </div>
                                @if($maturationPeriod->description)
                                <div class="sm:col-span-2">
                                    <dt class="text-sm font-medium text-gray-500">Description</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $maturationPeriod->description }}</dd>
                                </div>
                                @endif
                            </dl>
                        </div>

                        <!-- Payment Method Account -->
                        @if($maturationPeriod->paymentMethodAccount)
                        <div class="border-t border-gray-200 pt-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Payment Method Account</h3>
                            <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Account Name</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $maturationPeriod->paymentMethodAccount->name }}</dd>
                                </div>
                                @if($maturationPeriod->paymentMethodAccount->provider)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Provider</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $maturationPeriod->paymentMethodAccount->provider }}</dd>
                                </div>
                                @endif
                                @if($maturationPeriod->paymentMethodAccount->account_number)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Account Number</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $maturationPeriod->paymentMethodAccount->account_number }}</dd>
                                </div>
                                @endif
                                @if($maturationPeriod->paymentMethodAccount->account_holder_name)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Account Holder Name</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $maturationPeriod->paymentMethodAccount->account_holder_name }}</dd>
                                </div>
                                @endif
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Balance</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ number_format($maturationPeriod->paymentMethodAccount->balance, 2) }} {{ $maturationPeriod->paymentMethodAccount->currency ?? 'UGX' }}
                                    </dd>
                                </div>
                                <div class="sm:col-span-2">
                                    <dt class="text-sm font-medium text-gray-500">Actions</dt>
                                    <dd class="mt-1">
                                        <a href="{{ route('payment-method-accounts.transactions', $maturationPeriod->paymentMethodAccount) }}" 
                                           class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <svg class="-ml-0.5 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                            </svg>
                                            View Transactions
                                        </a>
                                    </dd>
                                </div>
                                @if($maturationPeriod->paymentMethodAccount->description)
                                <div class="sm:col-span-2">
                                    <dt class="text-sm font-medium text-gray-500">Account Description</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $maturationPeriod->paymentMethodAccount->description }}</dd>
                                </div>
                                @endif
                            </dl>
                        </div>
                        @endif

                        <!-- Metadata -->
                        <div class="border-t border-gray-200 pt-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Metadata</h3>
                            <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Created At</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $maturationPeriod->created_at->format('M d, Y H:i:s') }}</dd>
                                </div>
                                @if($maturationPeriod->createdBy)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Created By</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $maturationPeriod->createdBy->name }}</dd>
                                </div>
                                @endif
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Updated At</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $maturationPeriod->updated_at->format('M d, Y H:i:s') }}</dd>
                                </div>
                                @if($maturationPeriod->updatedBy)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Updated By</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $maturationPeriod->updatedBy->name }}</dd>
                                </div>
                                @endif
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</x-app-layout>

