<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <h1 class="text-xl font-bold text-gray-900 mb-6">HR — Leave Requests</h1>

            @if(isset($response['error']))
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
                    {{ $response['error'] }}
                </div>
            @endif

            <div class="bg-white shadow rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Employee</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Type</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">From</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">To</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($response['data'] ?? [] as $leave)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $leave['employee'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $leave['leave_type'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $leave['start_date'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $leave['end_date'] ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                    {{ $leave['status'] ?? '—' }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-400">No leave requests found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</x-app-layout>
