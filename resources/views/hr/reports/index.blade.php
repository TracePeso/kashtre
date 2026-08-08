<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <h1 class="text-xl font-bold text-gray-900 mb-6">HR — Reports</h1>

            @if(isset($stats['error']))
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
                    {{ $stats['error'] }}
                </div>
            @endif

            {{-- Workforce Summary --}}
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Workforce Summary</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                @foreach(['total_employees' => 'Total Employees', 'active_employees' => 'Active', 'on_leave' => 'On Leave', 'present_today' => 'Present Today'] as $key => $label)
                <div class="bg-white rounded-lg shadow px-5 py-4">
                    <p class="text-xs text-gray-500 mb-1">{{ $label }}</p>
                    <p class="text-3xl font-bold text-[#011478]">{{ $stats[$key] ?? '—' }}</p>
                </div>
                @endforeach
            </div>

            {{-- Today's Attendance Breakdown --}}
            @if(!empty($attendance))
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Today's Attendance — {{ $attendance['date'] ?? '' }}</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                @foreach(['total_present' => 'Present', 'absent' => 'Absent', 'on_leave' => 'On Leave', 'late' => 'Late'] as $key => $label)
                <div class="bg-white rounded-lg shadow px-5 py-4">
                    <p class="text-xs text-gray-500 mb-1">{{ $label }}</p>
                    <p class="text-3xl font-bold text-[#011478]">{{ $attendance[$key] ?? '0' }}</p>
                </div>
                @endforeach
            </div>
            @endif

            <div class="bg-white rounded-lg shadow px-6 py-5 text-sm text-gray-500 text-center">
                Detailed report exports are available from the HR module directly.
                <a href="{{ route('hr.embed', ['path' => '/hr/reports']) }}" class="ml-2 text-blue-600 hover:underline">Open Full Reports →</a>
            </div>

        </div>
    </div>
</x-app-layout>
