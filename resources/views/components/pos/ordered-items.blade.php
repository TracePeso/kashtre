{{-- 
    Unified Ordered Items (Requests/Orders) Component
    This component serves as a single source of truth for displaying ordered items
    across both POS item selection and Service Point client details pages.
    It handles pending, in-progress, and completed items with consistent styling.
--}}
@php
    // Ensure business is loaded for the client
    if (isset($client) && !isset($client->business)) {
        $client->load('business');
    }
    $business = $client->business ?? null;
    $inventoryModuleEnabled = $inventoryModuleEnabled
        ?? (isset($client) && $client->business_id
            ? \App\Models\InventoryModuleConfig::query()
                ->where('business_id', $client->business_id)
                ->where('is_active', true)
                ->exists()
            : false);
@endphp

<!-- Section 5: Ordered Items (Requests/Orders) -->
<div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
    <div class="p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">Ordered Items (Requests/Orders)</h3>
            <div class="text-right">
                <div class="text-sm text-gray-600">Total Amount</div>
                <div class="text-lg font-bold text-blue-600">
                    {{ number_format($correctTotalAmount ?? 0, 0) }} UGX
                </div>
            </div>
        </div>
        <form id="itemStatusForm">
            @csrf
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Item Name</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Proforma Invoice</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Price</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Qty</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Total Amount</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Status Update</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @if(isset($pendingItems) && $pendingItems->count() > 0)
                        @foreach($pendingItems as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-900 font-medium">
                                    {{ $item->item->name ?? $item->item_name }}
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $item->invoice->invoice_number ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-gray-600 font-semibold">{{ number_format($item->price, 0) }} UGX</td>
                                <td class="px-4 py-3 text-gray-600 text-center">{{ $item->quantity }}</td>
                                <td class="px-4 py-3 font-semibold text-green-600">
                                    {{ number_format($item->price * $item->quantity, 0) }} UGX
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        // Check if this item's service point is "admission"
                                        $itemServicePoint = $item->servicePoint ?? null;
                                        $isAdmissionServicePoint = $itemServicePoint && strtolower(trim($itemServicePoint->name)) === 'admission';
                                    @endphp
                                    
                                    @if($isAdmissionServicePoint)
                                        {{-- Show only Admit button for admission service point --}}
                                        @if($item->status === 'completed')
                                            <span class="text-sm text-green-600 font-medium">Completed</span>
                                        @elseif($client->is_long_stay || preg_match('/\/M$/', $client->visit_id))
                                            {{-- Client is already admitted, item should be completed --}}
                                            <span class="text-sm text-green-600 font-medium">Completed</span>
                                        @else
                                            @php
                                                // Determine redirect URL based on current route
                                                $redirectUrl = request()->routeIs('pos.item-selection') 
                                                    ? route('pos.item-selection', $client)
                                                    : (isset($servicePoint) && $servicePoint 
                                                        ? route('service-points.client-details', [$servicePoint, $client])
                                                        : route('pos.item-selection', $client));
                                            @endphp
                                            <button 
                                                type="button"
                                                onclick="admitItem({{ $item->id }})"
                                                class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded transition duration-200 text-sm">
                                                {{ $business->admit_button_label ?? 'Admit' }}
                                            </button>
                                        @endif
                                    @else
                                        {{-- Show normal status actions for other service points --}}
                                        <div class="flex flex-col space-y-2">
                                            @php $isGood = ($item->item->type ?? null) === 'good'; @endphp
                                            @if($isGood && !empty($inventoryModuleEnabled))
                                                <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1.5">
                                                    Goods: complete via <strong>EndStore</strong> (Pending until dispensed/released).
                                                </p>
                                                @if($item->status === 'pending')
                                                    <label class="flex items-center">
                                                        <input type="radio" name="item_statuses[{{ $item->id }}]" value="not_done" class="mr-2">
                                                        <span class="text-sm">Not Offered</span>
                                                    </label>
                                                @elseif($item->status === 'completed')
                                                    <label class="flex items-center">
                                                        <input type="radio" name="item_statuses[{{ $item->id }}]" value="completed" class="mr-2" checked>
                                                        <span class="text-sm">Completed (EndStore)</span>
                                                    </label>
                                                @endif
                                            @elseif($item->status === 'pending')
                                                <label class="flex items-center">
                                                    <input type="radio" name="item_statuses[{{ $item->id }}]" value="not_done" class="mr-2">
                                                    <span class="text-sm">Not Offered</span>
                                                </label>
                                                <label class="flex items-center">
                                                    <input type="radio" name="item_statuses[{{ $item->id }}]" value="partially_done" class="mr-2">
                                                    <span class="text-sm">In Progress</span>
                                                </label>
                                                <label class="flex items-center">
                                                    <input type="radio" name="item_statuses[{{ $item->id }}]" value="completed" class="mr-2">
                                                    <span class="text-sm">Completed (Done)</span>
                                                </label>
                                            @elseif($item->status === 'partially_done')
                                                <label class="flex items-center">
                                                    <input type="radio" name="item_statuses[{{ $item->id }}]" value="partially_done" class="mr-2" checked>
                                                    <span class="text-sm">In Progress</span>
                                                </label>
                                                <label class="flex items-center">
                                                    <input type="radio" name="item_statuses[{{ $item->id }}]" value="completed" class="mr-2">
                                                    <span class="text-sm">Completed (Done)</span>
                                                </label>
                                            @elseif($item->status === 'completed')
                                                <label class="flex items-center">
                                                    <input type="radio" name="item_statuses[{{ $item->id }}]" value="completed" class="mr-2" checked>
                                                    <span class="text-sm">Completed (Done)</span>
                                                </label>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endif
                    
                    @if(isset($partiallyDoneItems) && $partiallyDoneItems->count() > 0)
                        @foreach($partiallyDoneItems as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-900 font-medium">
                                    {{ $item->item->name ?? $item->item_name }}
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $item->invoice->invoice_number ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-gray-600 font-semibold">{{ number_format($item->price, 0) }} UGX</td>
                                <td class="px-4 py-3 text-gray-600 text-center">{{ $item->quantity }}</td>
                                <td class="px-4 py-3 font-semibold text-green-600">
                                    {{ number_format($item->price * $item->quantity, 0) }} UGX
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        // Check if this item's service point is "admission"
                                        $itemServicePoint = $item->servicePoint ?? null;
                                        $isAdmissionServicePoint = $itemServicePoint && strtolower(trim($itemServicePoint->name)) === 'admission';
                                    @endphp
                                    
                                    @if($isAdmissionServicePoint)
                                        {{-- Show only Admit button for admission service point --}}
                                        @if($item->status === 'completed')
                                            <span class="text-sm text-green-600 font-medium">Completed</span>
                                        @elseif($client->is_long_stay || preg_match('/\/M$/', $client->visit_id))
                                            {{-- Client is already admitted, item should be completed --}}
                                            <span class="text-sm text-green-600 font-medium">Completed</span>
                                        @else
                                            @php
                                                // Determine redirect URL based on current route
                                                $redirectUrl = request()->routeIs('pos.item-selection') 
                                                    ? route('pos.item-selection', $client)
                                                    : (isset($servicePoint) && $servicePoint 
                                                        ? route('service-points.client-details', [$servicePoint, $client])
                                                        : route('pos.item-selection', $client));
                                            @endphp
                                            <button 
                                                type="button"
                                                onclick="admitItem({{ $item->id }})"
                                                class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded transition duration-200 text-sm">
                                                {{ $business->admit_button_label ?? 'Admit' }}
                                            </button>
                                        @endif
                                    @else
                                        {{-- Show normal status actions for other service points --}}
                                        <div class="flex flex-col space-y-2">
                                            <label class="flex items-center">
                                                <input type="radio" name="item_statuses[{{ $item->id }}]" value="partially_done" class="mr-2" checked>
                                                <span class="text-sm">In Progress</span>
                                            </label>
                                            <label class="flex items-center">
                                                <input type="radio" name="item_statuses[{{ $item->id }}]" value="completed" class="mr-2">
                                                <span class="text-sm">Completed (Done)</span>
                                            </label>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endif
                    
                    @if(isset($completedItems) && $completedItems->count() > 0)
                        @foreach($completedItems as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-900 font-medium">
                                    {{ $item->item->name ?? $item->item_name }}
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $item->invoice->invoice_number ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-gray-600 font-semibold">{{ number_format($item->price, 0) }} UGX</td>
                                <td class="px-4 py-3 text-gray-600 text-center">{{ $item->quantity }}</td>
                                <td class="px-4 py-3 font-semibold text-green-600">
                                    {{ number_format($item->price * $item->quantity, 0) }} UGX
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col space-y-2">
                                        <label class="flex items-center">
                                            <input type="radio" name="item_statuses[{{ $item->id }}]" value="completed" class="mr-2" checked>
                                            <span class="text-sm">Completed (Done)</span>
                                        </label>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                    
                    @if((!isset($pendingItems) || $pendingItems->count() == 0) && 
                        (!isset($partiallyDoneItems) || $partiallyDoneItems->count() == 0) && 
                        (!isset($completedItems) || $completedItems->count() == 0))
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                No ordered items found for this client.
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    async function admitItem(queueItemId) {
        // Confirm with SweetAlert2
        const result = await Swal.fire({
            title: 'Admit Patient?',
            text: 'Are you sure you want to admit this patient? This will update the visit ID format and mark this item as completed.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, admit patient',
            cancelButtonText: 'Cancel'
        });
        
        if (!result.isConfirmed) {
            return;
        }
        
        // Show loading
        Swal.fire({
            title: 'Processing...',
            text: 'Updating visit ID and completing item',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // First, update the visit ID format based on business settings
        try {
            const clientId = {{ $client->id }};
            const businessId = {{ $client->business_id ?? 'null' }};
            
            // Get business settings for admission
            @php
                $business = $client->business ?? \App\Models\Business::find($client->business_id);
            @endphp
            const admitEnableCredit = {{ ($business->admit_enable_credit ?? false) ? 'true' : 'false' }};
            const admitEnableLongStay = {{ ($business->admit_enable_long_stay ?? false) ? 'true' : 'false' }};
            const defaultMaxCredit = {{ $business->max_first_party_credit_limit ?? 0 }};
            
            // Only attempt admission if at least one option is enabled
            if (admitEnableCredit || admitEnableLongStay) {
                // Update visit ID format via admission endpoint
                const admitFormData = new FormData();
                admitFormData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                admitFormData.append('redirect_to', window.location.href);
                
                if (admitEnableCredit) {
                    admitFormData.append('enable_credit', '1');
                    if (defaultMaxCredit > 0) {
                        admitFormData.append('max_credit', defaultMaxCredit);
                    }
                }
                
                if (admitEnableLongStay) {
                    admitFormData.append('enable_long_stay', '1');
                }
                
                const admitResponse = await fetch(`/clients/${clientId}/admit`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: admitFormData
                });
                
                const admitData = await admitResponse.json();
                
                if (!admitResponse.ok || !admitData.success) {
                    throw new Error(admitData.message || 'Failed to update visit ID');
                }
            } else {
                // Skip admission if neither option is enabled
                console.log('Skipping automatic admission: neither credit nor long-stay is enabled in business settings');
            }
            
            // Now mark the item as completed and submit the form data directly
            const form = document.getElementById('itemStatusForm');
            if (!form) {
                throw new Error('Form not found');
            }
            
            // Get form data
            const formData = new FormData(form);
            
            // Set the item status to "completed"
            formData.set(`item_statuses[${queueItemId}]`, 'completed');
            
            // Determine the service point ID from the current route or use 0 for POS
            @php
                $servicePointId = isset($servicePoint) && $servicePoint ? $servicePoint->id : 0;
            @endphp
            const servicePointId = {{ $servicePointId }};
            
            // Submit form data directly to the money movement endpoint
            const updateUrl = servicePointId > 0 
                ? `/service-points/${servicePointId}/client/${clientId}/update-statuses-and-process`
                : `/service-points/0/client/${clientId}/update-statuses-and-process`;
            
            const updateResponse = await fetch(updateUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: formData
            });
            
            const updateData = await updateResponse.json();
            
            if (!updateResponse.ok || !updateData.success) {
                throw new Error(updateData.message || 'Failed to process item and money movement');
            }
            
            // Success - show success message and reload
            Swal.fire({
                title: 'Success!',
                text: 'Patient admitted and item completed successfully',
                icon: 'success',
                confirmButtonColor: '#10b981'
            }).then(() => {
                window.location.reload();
            });
        } catch (error) {
            console.error('Error admitting patient:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to admit patient. Please try again.'
            });
        }
    }
</script>
@endpush
