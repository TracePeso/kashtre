<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex items-center justify-between mb-6">
                <h1 class="text-xl font-bold text-gray-900">HR — Employees</h1>
            </div>

            @if(isset($response['error']))
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
                    {{ $response['error'] }}
                </div>
            @endif

            <div class="bg-white shadow rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Name</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Email</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Department</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($response['data'] ?? [] as $emp)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $emp['full_name'] ?? ($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '') }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $emp['email'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $emp['department_name'] ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ ($emp['employment_status'] ?? '') === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $emp['employment_status'] ?? '—' }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-400">No employees found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</x-app-layout>
