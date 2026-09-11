<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <h1 class="text-xl font-bold text-gray-900 mb-6">HR — Performance Reviews</h1>

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
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Review Period</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Score</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Reviewer</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($response['data'] ?? [] as $review)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $review['employee'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $review['review_period'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $review['overall_score'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $review['reviewer'] ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                    {{ $review['status'] ?? '—' }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-400">No performance reviews found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</x-app-layout>
