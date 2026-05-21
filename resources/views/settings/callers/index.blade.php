<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        @php
            $userPerms  = auth()->user()->permissions ?? [];
            $canView    = in_array('View Callers',   $userPerms) || in_array('Manage Callers', $userPerms);
            $canAdd     = in_array('Add Callers',    $userPerms) || in_array('Manage Callers', $userPerms);
            $canEdit    = in_array('Edit Callers',   $userPerms) || in_array('Manage Callers', $userPerms);
            $canManage  = in_array('Manage Callers', $userPerms);
        @endphp

        <!-- Header -->
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                    Callers
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Named caller stations and their assigned service points.
                </p>
            </div>
            @if($canAdd)
            <div class="mt-4 flex md:mt-0 md:ml-4">
                <a href="{{ route('service-point-callers.create') }}"
                   class="ml-3 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Add Caller
                </a>
            </div>
            @endif
        </div>

        <!-- Active Emergency Banner -->
        @if($globalActiveEmergency)
        <div class="mt-4 bg-red-50 border border-red-400 rounded-lg px-5 py-4 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                <div>
                    <p class="text-sm font-bold text-red-700 uppercase tracking-wide">Emergency Active</p>
                    <p class="text-sm text-red-600 mt-0.5">{{ $activeEmergencyAlert->display_message ?: $activeEmergencyAlert->message }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('emergency.resolve.global') }}">
                @csrf
                <button type="submit"
                    class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded shadow transition">
                    Clear Emergency
                </button>
            </form>
        </div>
        @endif

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="mt-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded relative">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative">
                {{ session('error') }}
            </div>
        @endif

        <!-- Table -->
        <div class="mt-8">
            @if($callers->count() > 0)
                <div class="bg-white shadow overflow-hidden sm:rounded-md">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Caller
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Service Points
                                </th>
                                @if($canView)
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Display Token
                                </th>
                                @endif
                                @if($canEdit || $canView)
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($callers as $caller)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $caller->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ ucfirst($caller->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <p class="text-sm font-medium text-gray-900">{{ $caller->name }}</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($caller->servicePoints->isNotEmpty())
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($caller->servicePoints as $sp)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700">
                                                        {{ $sp->name }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-400 italic">None assigned</span>
                                        @endif
                                    </td>
                                    @if($canView)
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($caller->display_token)
                                            <span class="inline-flex items-center gap-1.5 font-mono text-sm font-semibold text-gray-800 bg-gray-100 px-2.5 py-1 rounded">
                                                {{ $caller->display_token }}
                                                <button type="button"
                                                        onclick="copyToClipboard('{{ $caller->display_token }}', this)"
                                                        title="Copy token"
                                                        class="text-gray-400 hover:text-gray-600">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                              d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                                    </svg>
                                                </button>
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400 italic">Not generated</span>
                                        @endif
                                    </td>
                                    @endif
                                    @if($canEdit || $canView)
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <div class="flex items-center justify-end space-x-3">
                                            <a href="{{ route('service-point-callers.edit', $caller) }}"
                                               class="text-blue-600 hover:text-blue-900 text-sm font-medium">
                                                {{ $canEdit ? 'Edit' : 'View' }}
                                            </a>
                                        </div>
                                    </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M11 5.882A2 2 0 009.117 4H6a2 2 0 00-2 2v6a2 2 0 002 2h3.117M11 5.882l6.553 3.894a1 1 0 010 1.724L11 15.118"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No callers configured yet</h3>
                    <p class="mt-1 text-sm text-gray-500">Add a named caller and assign service points to get started.</p>
                    @if($canAdd)
                    <div class="mt-6">
                        <a href="{{ route('service-point-callers.create') }}"
                           class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            Add Caller
                        </a>
                    </div>
                    @endif
                </div>
            @endif
        </div>

    </div>
</div>

<script>
function copyToClipboard(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        btn.innerHTML = '<svg class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
        setTimeout(() => {
            btn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>';
        }, 2000);
    });
}
</script>
</x-app-layout>
