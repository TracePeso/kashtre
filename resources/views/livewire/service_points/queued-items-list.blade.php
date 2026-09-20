<div class="p-6">
    <div class="mb-4">
        <h3 class="text-lg font-medium text-gray-900 mb-2">Queued Items ({{ $queuedItems->count() }})</h3>
        <p class="text-sm text-gray-500">Items waiting to be processed at this service point</p>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Queued At</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waiting Time</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($queuedItems as $item)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">{{ $item->item_name }}</div>
                        @if($item->notes)
                            <div class="text-sm text-gray-500">{{ Str::limit($item->notes, 50) }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">{{ $item->client->name ?? 'N/A' }}</div>
                        <div class="text-sm text-gray-500">{{ $item->client->phone_number ?? 'N/A' }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">{{ $item->invoice->invoice_number ?? 'N/A' }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ $item->quantity }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ number_format($item->price) }} UGX
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $item->queued_at->inOperationalTimezone()->format('M d, Y H:i') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <span class="waiting-time font-mono font-semibold text-blue-600" 
                              data-queued-at="{{ $item->queued_at->toISOString() }}"
                              data-item-id="{{ $item->id }}">
                            {{ $item->getFormattedWaitingTime() }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                            @if($item->status === 'pending') bg-yellow-100 text-yellow-800
                            @elseif($item->status === 'in_progress') bg-blue-100 text-blue-800
                            @elseif($item->status === 'completed') bg-green-100 text-green-800
                            @else bg-gray-100 text-gray-800
                            @endif">
                            {{ ucfirst(str_replace('_', ' ', $item->status)) }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<script>
    // Real-time waiting time updates for Livewire component
    function updateWaitingTimes() {
        const waitingTimeElements = document.querySelectorAll('.waiting-time');
        
        waitingTimeElements.forEach(element => {
            const queuedAt = new Date(element.dataset.queuedAt);
            const now = new Date();
            const diffInSeconds = Math.floor((now - queuedAt) / 1000);
            
            const minutes = Math.floor(diffInSeconds / 60);
            const seconds = diffInSeconds % 60;
            
            const formattedTime = `${minutes}m:${seconds.toString().padStart(2, '0')}s`;
            element.textContent = formattedTime;
            
            // Add color coding based on waiting time
            element.className = 'waiting-time font-mono font-semibold';
            if (diffInSeconds < 300) { // Less than 5 minutes
                element.classList.add('text-green-600');
            } else if (diffInSeconds < 900) { // Less than 15 minutes
                element.classList.add('text-yellow-600');
            } else { // 15+ minutes
                element.classList.add('text-red-600');
            }
        });
    }

    // Update waiting times every second
    setInterval(updateWaitingTimes, 1000);
    
    // Initial update
    updateWaitingTimes();
</script>

