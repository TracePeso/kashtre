<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Header -->
        <div class="md:flex md:items-center md:justify-between mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Called Clients</h2>
                <p class="mt-1 text-sm text-gray-500">Clients who have been called to a service point.</p>
            </div>
            <!-- Date filter -->
            <form method="GET" action="{{ route('callers.log') }}" class="mt-4 md:mt-0 flex items-center gap-2">
                <input type="date"
                       name="date"
                       value="{{ $date }}"
                       class="border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <button type="submit"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Filter
                </button>
                @if($date !== now()->toDateString())
                    <a href="{{ route('callers.log') }}"
                       class="text-sm text-indigo-600 hover:text-indigo-800">Today</a>
                @endif
            </form>
        </div>

        <div class="bg-white shadow sm:rounded-lg overflow-hidden">

            @if($logs->isEmpty())
                <div class="px-6 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M11 5.882A2 2 0 009.117 4H6a2 2 0 00-2 2v6a2 2 0 002 2h3.117M11 5.882l6.553 3.894a1 1 0 010 1.724L11 15.118"/>
                    </svg>
                    <h3 class="mt-3 text-sm font-medium text-gray-900">No calls recorded</h3>
                    <p class="mt-1 text-sm text-gray-500">No clients were called on {{ \Carbon\Carbon::parse($date)->format('d M Y') }}.</p>
                </div>
            @else
                <div class="px-4 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                    <span class="text-xs font-medium text-gray-500">{{ $logs->count() }} call(s) on {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service Point</th>
                                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room</th>
                                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Caller</th>
                                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Called By</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @foreach($logs as $log)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-500">
                                        {{ $log->called_at->format('H:i:s') }}
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ optional($log->client)->visit_id ?? '—' }}
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-700">
                                        {{ optional($log->servicePoint)->name ?? '—' }}
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-500">
                                        {{ optional($log->room)->name ?? '—' }}
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        @if($log->caller)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 text-xs font-medium">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M11 5.882A2 2 0 009.117 4H6a2 2 0 00-2 2v6a2 2 0 002 2h3.117M11 5.882l6.553 3.894a1 1 0 010 1.724L11 15.118"/>
                                                </svg>
                                                {{ $log->caller->name }}
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-500">
                                        {{ optional($log->calledBy)->name ?? '—' }}
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
