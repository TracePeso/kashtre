<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Settings - Timezones') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                        These are the timezones hospitals can pick when creating a business or assigning a branch.
                    </p>

                    @if(session('success'))
                        <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-4 rounded">
                            <p class="text-sm text-green-700">{{ session('success') }}</p>
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4 rounded">
                            <ul class="text-sm text-red-700 list-disc ml-5">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="border rounded-lg p-4">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Add Timezone</h3>
                            <form method="POST" action="{{ route('settings.timezones.store') }}">
                                @csrf
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">IANA timezone</label>
                                        <select name="iana_id" class="w-full border-gray-300 rounded-md" required>
                                            <option value="">Select a timezone</option>
                                            @foreach($availableIana as $id)
                                                <option value="{{ $id }}" @selected(old('iana_id') === $id)>{{ $id }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Display name <span class="text-xs text-gray-500">(optional)</span></label>
                                        <input type="text" name="display_name" value="{{ old('display_name') }}" class="w-full border-gray-300 rounded-md" placeholder="e.g. East Africa Time — Kampala">
                                    </div>
                                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700">
                                        Add Timezone
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="border rounded-lg p-4">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">What this does</h3>
                            <p class="text-sm text-gray-600">
                                Adding a timezone makes it available on Create Business, Create Branch, and Business Settings → Time.
                                Hospital staff still pick the zone there — this page only controls the allowed list.
                            </p>
                        </div>
                    </div>

                    <div class="mt-8">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Allowed Timezones</h3>
                            <form method="GET" action="{{ route('settings.timezones.index') }}" class="sm:w-72">
                                <input type="search" name="q" value="{{ $catalogueQuery }}" placeholder="Search timezones…"
                                       class="w-full border-gray-300 rounded-md text-sm">
                            </form>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">IANA</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Region</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($zones as $zone)
                                        <tr>
                                            <td class="px-4 py-3 text-sm font-mono text-gray-900">{{ $zone->iana_id }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ $zone->display_name }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ $zone->region_code }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ $zone->status }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-4 py-4 text-sm text-gray-500">
                                                No timezones yet. Run <code class="font-mono">php artisan time:install</code> or add one above.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            {{ $zones->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
