<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <h1 class="text-xl font-bold text-gray-900 mb-6">HR — Attendance</h1>

            @if(isset($records['error']))
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
                    {{ $records['error'] }}
                </div>
            @endif

            @if(!empty($summary))
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                @foreach($summary as $label => $value)
                <div class="bg-white rounded-lg shadow px-4 py-3">
                    <p class="text-xs text-gray-500">{{ ucfirst(str_replace('_', ' ', $label)) }}</p>
                    <p class="text-2xl font-bold text-[#011478]">{{ $value }}</p>
                </div>
                @endforeach
            </div>
            @endif

            <div class="bg-white shadow rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Employee</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Date</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Clock In</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Clock Out</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($records['data'] ?? [] as $rec)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $rec['employee'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $rec['attendance_date'] ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ ($rec['status'] ?? '') === 'present' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                                    {{ $rec['status'] ?? '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $rec['clock_in'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $rec['clock_out'] ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-400">No attendance records found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</x-app-layout>
