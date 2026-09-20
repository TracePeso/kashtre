<x-app-layout>
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Contractor Service Charge Details</h1>
            <div class="flex space-x-2">
                @if(in_array('Manage Contractor Service Charges', Auth::user()->permissions))
                    <a href="{{ route('contractor-service-charges.edit', $contractorServiceCharge) }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Edit
                    </a>
                @endif
                <a href="{{ route('contractor-service-charges.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Back to List
                </a>
            </div>
        </div>

        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Contractor Information</h2>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Contractor Name</label>
                            <p class="text-sm text-gray-900">{{ $contractorServiceCharge->contractorName }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Business</label>
                            <p class="text-sm text-gray-900">{{ $contractorServiceCharge->business->name }}</p>
                        </div>
                    </div>
                </div>

                <div>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Service Charge Details</h2>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Amount</label>
                            <p class="text-sm text-gray-900">{{ $contractorServiceCharge->formatted_amount }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Type</label>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $contractorServiceCharge->type === 'percentage' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                {{ ucfirst($contractorServiceCharge->type) }}
                            </span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Upper Bound</label>
                            <p class="text-sm text-gray-900">{{ $contractorServiceCharge->upper_bound ? 'UGX ' . number_format($contractorServiceCharge->upper_bound, 2) : 'No Limit' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Lower Bound</label>
                            <p class="text-sm text-gray-900">{{ $contractorServiceCharge->lower_bound ? 'UGX ' . number_format($contractorServiceCharge->lower_bound, 2) : 'No Limit' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $contractorServiceCharge->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $contractorServiceCharge->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            @if($contractorServiceCharge->description)
                <div class="mt-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Description</h2>
                    <p class="text-sm text-gray-900">{{ $contractorServiceCharge->description }}</p>
                </div>
            @endif

            <div class="mt-6 pt-6 border-t border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Additional Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Created By</label>
                        <p class="text-sm text-gray-900">{{ $contractorServiceCharge->createdBy->name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Created At</label>
                        <p class="text-sm text-gray-900">{{ $contractorServiceCharge->created_at->inOperationalTimezone()->format('M d, Y H:i') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Last Updated</label>
                        <p class="text-sm text-gray-900">{{ $contractorServiceCharge->updated_at->inOperationalTimezone()->format('M d, Y H:i') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</x-app-layout>
