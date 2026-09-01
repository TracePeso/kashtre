<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Pending Items - {{ $servicePoint->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold">Pending Items ({{ $pendingItems->total() }})</h3>
                        <a href="{{ route('service-queues.index') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Back to Service Points
                        </a>
                    </div>

                    @if($pendingItems->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service/Item</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Queued At</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waiting Time</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($pendingItems as $item)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">{{ $item->client->name }}</div>
                                                <div class="text-sm text-gray-500">{{ $item->client->phone_number }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">{{ $item->item->name }}</div>
                                                <div class="text-sm text-gray-500">{{ $item->item->type }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $item->quantity }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $item->invoice->invoice_number }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $item->queued_at->format('M d, Y H:i') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <span class="waiting-time font-mono font-semibold text-blue-600" 
                                                      data-queued-at="{{ $item->queued_at->toISOString() }}"
                                                      data-item-id="{{ $item->id }}">
                                                    {{ $item->getFormattedWaitingTime() }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                @if(($item->item->type ?? null) === 'good')
                                                    <span class="text-xs text-gray-500" title="Goods stay Pending until End Store dispense">
                                                        End Store dispense only
                                                    </span>
                                                @else
                                                    <button
                                                        onclick="moveToPartiallyDone({{ $item->id }})"
                                                        class="bg-orange-500 hover:bg-orange-700 text-white font-bold py-2 px-4 rounded text-xs">
                                                        Move to In Progress
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-6">
                            {{ $pendingItems->links() }}
                        </div>
                    @else
                        <div class="text-center py-8">
                            <div class="text-gray-500 text-lg">No pending items found.</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        // Real-time waiting time updates
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

        function moveToPartiallyDone(itemId) {
            // Show confirmation dialog
            Swal.fire({
                title: 'Move to In Progress?',
                text: 'This will process money transfers and move the item to in progress status.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#f97316',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, move it!',
                cancelButtonText: 'Cancel',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch(`/service-delivery-queues/${itemId}/move-to-partially-done`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Content-Type': 'application/json',
                        },
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || 'An error occurred');
                        }
                        return data;
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Success!',
                        text: result.value.message,
                        icon: 'success',
                        confirmButtonColor: '#10b981'
                    }).then(() => {
                        // Reload the page to show updated status
                        window.location.reload();
                    });
                }
            }).catch((error) => {
                Swal.fire({
                    title: 'Error!',
                    text: error.message,
                    icon: 'error',
                    confirmButtonColor: '#ef4444'
                });
            });
        }
    </script>
</x-app-layout>
