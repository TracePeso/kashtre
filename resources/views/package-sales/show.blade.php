<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Package Sales Details') }}
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('package-sales.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    Back to List
                </a>
                @if(in_array('Delete Package Sales', (array) auth()->user()->permissions))
                <form method="POST" action="{{ route('package-sales.destroy', $packageSale) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete this package sales record?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        Delete
                    </button>
                </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Details -->
                <div class="lg:col-span-2">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Package Sales Information</h3>
                            
                            <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Date</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $packageSale->date->format('l, F j, Y') }}</dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                                    <dd class="mt-1">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                            {{ $packageSale->status === 'completed' ? 'bg-green-100 text-green-800' : 
                                               ($packageSale->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                            {{ ucfirst($packageSale->status) }}
                                        </span>
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Client Name</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $packageSale->name }}</dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Invoice Number</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $packageSale->invoice_number }}</dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Package Tracking Number (PKN)</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $packageSale->pkn }}</dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Item Name</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $packageSale->item_name }}</dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Quantity</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $packageSale->qty }}</dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Amount</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white font-semibold">UGX {{ number_format($packageSale->amount, 2) }}</dd>
                                </div>

                                @if($packageSale->notes)
                                <div class="sm:col-span-2">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Notes</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $packageSale->notes }}</dd>
                                </div>
                                @endif
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Related Information -->
                <div class="space-y-6">
                    <!-- Client Information -->
                    @if($packageSale->client)
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Client Information</h3>
                            
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Name</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $packageSale->client->name }}</dd>
                                </div>

                                @if($packageSale->client->phone)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Phone</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $packageSale->client->phone }}</dd>
                                </div>
                                @endif

                                @if($packageSale->client->email)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $packageSale->client->email }}</dd>
                                </div>
                                @endif

                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Client ID</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">#{{ $packageSale->client->id }}</dd>
                                </div>
                            </dl>

                            <div class="mt-4">
                                <a href="{{ route('clients.show', $packageSale->client) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 text-sm font-medium">
                                    View Client Details →
                                </a>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Package Tracking Information -->
                    @if($packageSale->packageTracking)
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Package Tracking</h3>
                            
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Tracking Number</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $packageSale->packageTracking->tracking_number ?? 'PKG-' . $packageSale->packageTracking->id . '-' . $packageSale->packageTracking->created_at->inOperationalTimezone()->format('YmdHis') }}</dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Package Name</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $packageSale->packageTracking->packageItem->name ?? 'N/A' }}</dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                                    <dd class="mt-1">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                            {{ $packageSale->packageTracking->status === 'active' ? 'bg-green-100 text-green-800' : 
                                               ($packageSale->packageTracking->status === 'expired' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800') }}">
                                            {{ ucfirst($packageSale->packageTracking->status) }}
                                        </span>
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Remaining Quantity</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $packageSale->packageTracking->remaining_quantity }}</dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Valid Until</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $packageSale->packageTracking->valid_until ? $packageSale->packageTracking->valid_until->format('M d, Y') : 'N/A' }}</dd>
                                </div>
                            </dl>

                            <div class="mt-4">
                                <a href="{{ route('package-tracking.show', $packageSale->packageTracking) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 text-sm font-medium">
                                    View Package Details →
                                </a>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Business Information -->
                    @if($packageSale->business)
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Business Information</h3>
                            
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Business Name</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $packageSale->business->name }}</dd>
                                </div>

                                @if($packageSale->branch)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Branch</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $packageSale->branch->name }}</dd>
                                </div>
                                @endif
                            </dl>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
