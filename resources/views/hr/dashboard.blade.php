<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <h1 class="text-xl font-bold text-gray-900 mb-6">HR Overview</h1>

            @if(isset($stats['error']))
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
                    HR module unavailable: {{ $stats['error'] }}
                </div>
            @endif

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @php $cards = [
                    'total_employees'    => 'Total Employees',
                    'active_employees'   => 'Active',
                    'on_leave_today'     => 'On Leave Today',
                    'present_today'      => 'Present Today',
                ]; @endphp
                @foreach($cards as $key => $label)
                <div class="bg-white rounded-lg shadow px-5 py-4">
                    <p class="text-xs text-gray-500 mb-1">{{ $label }}</p>
                    <p class="text-3xl font-bold text-[#011478]">{{ $stats[$key] ?? '—' }}</p>
                </div>
                @endforeach
            </div>

        </div>
    </div>
</x-app-layout>
