<x-app-layout>
<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Entity Service Charge Details</h1>
            <div class="flex space-x-2">
                @if(in_array('Manage Service Charges', Auth::user()->permissions))
                    <a href="{{ route('service-charges.edit', $serviceCharge) }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Edit
                    </a>
                @endif
                <a href="{{ route('service-charges.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Back to List
                </a>
            </div>
        </div>

        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Service Charge Details</h2>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Charge Information</h3>
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Charge Amount</dt>
                                <dd class="text-sm text-gray-900">
                                    {{ $serviceCharge->type === 'percentage' ? $serviceCharge->amount . '%' : 'UGX ' . number_format($serviceCharge->amount, 2) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Charge Type</dt>
                                <dd class="text-sm text-gray-900">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        {{ $serviceCharge->type === 'percentage' ? 'bg-green-100 text-green-800' : 'bg-purple-100 text-purple-800' }}">
                                        {{ ucfirst($serviceCharge->type) }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Lower Bound</dt>
                                <dd class="text-sm text-gray-900">{{ $serviceCharge->lower_bound ? 'UGX ' . number_format($serviceCharge->lower_bound, 2) : 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Upper Bound</dt>
                                <dd class="text-sm text-gray-900">{{ $serviceCharge->upper_bound ? 'UGX ' . number_format($serviceCharge->upper_bound, 2) : 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Status</dt>
                                <dd class="text-sm text-gray-900">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        {{ $serviceCharge->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $serviceCharge->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Entity Information</h3>
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Business</dt>
                                <dd class="text-sm text-gray-900">
                                    @php
                                        try {
                                            $business = \App\Models\Business::find($serviceCharge->business_id);
                                            echo $business ? $business->name : 'Business #' . $serviceCharge->business_id;
                                        } catch (\Exception $e) {
                                            echo 'Business #' . $serviceCharge->business_id;
                                        }
                                    @endphp
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Entity Type</dt>
                                <dd class="text-sm text-gray-900">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ ucfirst($serviceCharge->entity_type) }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Entity</dt>
                                <dd class="text-sm text-gray-900">{{ $serviceCharge->entity_name }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
                
                @if($serviceCharge->description)
                    <div class="mt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Description</h3>
                        <p class="text-sm text-gray-700">{{ $serviceCharge->description }}</p>
                    </div>
                @endif
                
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Audit Information</h3>
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created By</dt>
                            <dd class="text-sm text-gray-900">{{ $serviceCharge->createdBy->name ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created At</dt>
                            <dd class="text-sm text-gray-900">{{ $serviceCharge->created_at->inOperationalTimezone()->format('M d, Y H:i:s') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Last Updated</dt>
                            <dd class="text-sm text-gray-900">{{ $serviceCharge->updated_at->inOperationalTimezone()->format('M d, Y H:i:s') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
</x-app-layout>
