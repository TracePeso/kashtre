<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                    Payment Methods and Maturation Periods
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Configure payment method accounts and maturation periods for different payment methods by entity
                </p>
            </div>
            @if(in_array('Add Maturation Periods', auth()->user()->permissions ?? []))
            <div class="mt-4 flex md:mt-0 md:ml-4">
                <a href="{{ route('maturation-periods.create') }}" 
                   class="ml-3 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Add Maturation Period
                </a>
            </div>
            @endif
        </div>

        <!-- Alert Messages -->
        @if(session('success'))
            <div class="mt-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif

        <!-- Content -->
        <div class="mt-8">
            @if($maturationPeriods->count() > 0)
                <div class="bg-white shadow overflow-hidden sm:rounded-md">
                    <ul class="divide-y divide-gray-200">
                        @foreach($maturationPeriods as $period)
                            <li class="px-6 py-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <div class="flex items-center">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $period->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                        {{ $period->is_active ? 'Active' : 'Inactive' }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="ml-4 flex-1">
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-900">
                                                            {{ $period->business->name }}
                                                        </p>
                                                        <p class="text-sm text-gray-500">
                                                            {{ $period->payment_method_name }} - {{ $period->formatted_maturation_period }}
                                                        </p>
                                                        @if($period->paymentMethodAccount)
                                                            <p class="text-sm text-blue-600 mt-1">
                                                                Account: {{ $period->paymentMethodAccount->name }} 
                                                                @if($period->paymentMethodAccount->provider)
                                                                    ({{ $period->paymentMethodAccount->provider }})
                                                                @endif
                                                                @if($period->paymentMethodAccount->balance != 0)
                                                                    - Balance: {{ number_format($period->paymentMethodAccount->balance, 2) }} {{ $period->paymentMethodAccount->currency }}
                                                                @endif
                                                            </p>
                                                        @endif
                                                        @if($period->description)
                                                            <p class="text-sm text-gray-400 mt-1">{{ $period->description }}</p>
                                                        @endif
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        Created {{ $period->created_at->inOperationalTimezone()->format('M d, Y') }}
                                                        @if($period->createdBy)
                                                            by {{ $period->createdBy->name }}
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        @if(in_array('Edit Maturation Periods', auth()->user()->permissions ?? []))
                                        <a href="{{ route('maturation-periods.edit', $period) }}" 
                                           class="text-blue-600 hover:text-blue-900 text-sm font-medium">
                                            Edit
                                        </a>
                                        @endif
                                        
                                        @if(in_array('Manage Maturation Periods', auth()->user()->permissions ?? []))
                                        <form action="{{ route('maturation-periods.toggle-status', $period) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" 
                                                    class="text-{{ $period->is_active ? 'red' : 'green' }}-600 hover:text-{{ $period->is_active ? 'red' : 'green' }}-900 text-sm font-medium">
                                                {{ $period->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                        @endif
                                        
                                        @if(in_array('Delete Maturation Periods', auth()->user()->permissions ?? []))
                                        <form action="{{ route('maturation-periods.destroy', $period) }}" method="POST" class="inline" 
                                              onsubmit="return confirm('Are you sure you want to delete this maturation period?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900 text-sm font-medium">
                                                Delete
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No payment methods and maturation periods</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by creating a new payment method and maturation period.</p>
                    @if(in_array('Add Maturation Periods', auth()->user()->permissions ?? []))
                    <div class="mt-6">
                        <a href="{{ route('maturation-periods.create') }}" 
                           class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Add Maturation Period
                        </a>
                    </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
</x-app-layout>









