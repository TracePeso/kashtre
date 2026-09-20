<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Completed Today - {{ $servicePoint->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">
                            Completed Services Today ({{ $completedItems->total() }})
                        </h3>
                        <a href="{{ route('service-queues.index') }}" 
                           class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm">
                            Back to Dashboard
                        </a>
                    </div>

                    @if($completedItems->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client Name</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service/Item</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completed At</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Wait Time</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach($completedItems as $item)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 text-sm text-gray-900">{{ $item->client->name ?? 'N/A' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-900 font-medium">{{ $item->item->name ?? $item->item_name }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ $item->quantity }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ $item->invoice->invoice_number ?? 'N/A' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ $item->completed_at ? $item->completed_at->inOperationalTimezone()->format('H:i') : 'N/A' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-900">
                                                <span class="font-mono font-semibold text-green-600">
                                                    {{ $item->getFormattedWaitingTime() }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-sm">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    ✓ Completed
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-6">
                            {{ $completedItems->links() }}
                        </div>
                    @else
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-8 text-center">
                            <div class="text-gray-500 text-lg">No completed services today</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

