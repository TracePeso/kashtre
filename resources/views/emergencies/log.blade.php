<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="md:flex md:items-center md:justify-between mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Emergency Log</h2>
                <p class="mt-1 text-sm text-gray-500">Emergency alerts that have been triggered.</p>
            </div>

            <form method="GET" action="{{ route('emergency.log') }}" class="mt-4 md:mt-0 flex items-center gap-2">
                <input
                    type="date"
                    name="date"
                    value="{{ $date }}"
                    class="border-gray-300 rounded-md shadow-sm text-sm focus:ring-red-500 focus:border-red-500"
                >
                <button
                    type="submit"
                    class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                >
                    Filter
                </button>

                @if($date !== now()->toDateString())
                    <a href="{{ route('emergency.log') }}" class="text-sm text-red-600 hover:text-red-800">Today</a>
                @endif
            </form>
        </div>

        <div class="bg-white shadow sm:rounded-lg overflow-hidden">
            @if($alerts->isEmpty())
                <div class="px-6 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                        />
                    </svg>
                    <h3 class="mt-3 text-sm font-medium text-gray-900">No emergencies recorded</h3>
                    <p class="mt-1 text-sm text-gray-500">No emergency alerts were triggered on {{ \Carbon\Carbon::parse($date)->format('d M Y') }}.</p>
                </div>
            @else
                <div class="px-4 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                    <span class="text-xs font-medium text-gray-500">{{ $alerts->count() }} emergency alert(s) on {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Emergency Button</th>
                                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room</th>
                                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Triggered By</th>
                                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @foreach($alerts as $alert)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-500">
                                        {{ $alert->triggered_at->format('H:i:s') }}
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-700">
                                        {{ $alert->button_name ?: 'Emergency' }}
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-500">
                                        {{ $alert->room_name ?: '—' }}
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-500">
                                        {{ optional($alert->triggeredBy)->name ?? '—' }}
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        @if($alert->is_active)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-700">
                                                Active
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-700">
                                                Resolved
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>
</div>
</x-app-layout>
