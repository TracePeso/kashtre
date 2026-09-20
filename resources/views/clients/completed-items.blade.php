<x-app-layout>
    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold">Completed Items</h1>
                    <p class="text-gray-600 mt-1">
                        <span class="font-medium">{{ $client->full_name }}</span>
                        @if($client->phone_number)
                            <span class="text-gray-400">•</span>
                            <span>{{ $client->phone_number }}</span>
                        @endif
                    </p>
                </div>
                <a href="{{ route('clients.completed') }}" class="text-sm px-4 py-2 rounded-md bg-gray-700 text-white hover:bg-gray-800">
                    Back to Completed Items
                </a>
            </div>

            <div class="bg-white shadow rounded-lg overflow-hidden">
                @if($completedItems->count() > 0)
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service Point</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completed At</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($completedItems as $item)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ $item->item->name ?? 'N/A' }}</div>
                                        @if($item->item && $item->item->type)
                                            <div class="text-sm text-gray-500">{{ ucfirst($item->item->type) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $item->invoice->invoice_number ?? 'N/A' }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $item->quantity }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $item->servicePoint->name ?? 'N/A' }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            @if($item->completed_at)
                                                {{ $item->completed_at->inOperationalTimezone()->format('M d, Y H:i') }}
                                            @elseif($item->updated_at)
                                                {{ $item->updated_at->inOperationalTimezone()->format('M d, Y H:i') }}
                                            @else
                                                N/A
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="p-8 text-center">
                        <p class="text-gray-500">No completed items found for this client.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

