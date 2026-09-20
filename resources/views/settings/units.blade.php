<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Settings - Units') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                        These are the shared units hospitals pick as sale and order units on items.
                        Do not rename a unit after it is in use — retire it and add a new one instead.
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
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Add unit</h3>
                            <form method="POST" action="{{ route('settings.units.store') }}">
                                @csrf
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Code</label>
                                        <input type="text" name="code" value="{{ old('code') }}" required
                                               class="w-full border-gray-300 rounded-md" placeholder="e.g. SACHET">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                        <input type="text" name="name" value="{{ old('name') }}" required
                                               class="w-full border-gray-300 rounded-md" placeholder="e.g. sachet">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Symbol</label>
                                        <input type="text" name="symbol" value="{{ old('symbol') }}" required
                                               class="w-full border-gray-300 rounded-md" placeholder="e.g. sachet">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Kind</label>
                                        <select name="unit_class" class="w-full border-gray-300 rounded-md" required>
                                            <option value="PACKAGING_CONTEXTUAL" @selected(old('unit_class', 'PACKAGING_CONTEXTUAL') === 'PACKAGING_CONTEXTUAL')>Packaging (box, strip, sachet)</option>
                                            <option value="COUNT_CONTEXTUAL" @selected(old('unit_class') === 'COUNT_CONTEXTUAL')>Count (tablet, each, capsule)</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700">
                                        Add unit
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="border rounded-lg p-4">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">What this does</h3>
                            <p class="text-sm text-gray-600">
                                Adding a unit makes it available on item create/edit and on Manage Item Units.
                                Hospitals can also add a local packaging name for that facility only.
                                Changing sale or order unit on an item does not rewrite past receipts.
                            </p>
                        </div>
                    </div>

                    <div class="mt-8">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Shared catalog</h3>
                            <form method="GET" action="{{ route('settings.units.index') }}" class="sm:w-72">
                                <input type="search" name="q" value="{{ $catalogueQuery }}" placeholder="Search units…"
                                       class="w-full border-gray-300 rounded-md text-sm">
                            </form>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Symbol</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kind</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase"></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($units as $unit)
                                        <tr>
                                            <td class="px-4 py-3 text-sm font-mono text-gray-900">{{ $unit->code }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ $unit->canonical_name }}</td>
                                            <td class="px-4 py-3 text-sm font-mono text-gray-600">{{ $unit->symbol }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ $unit->quantityKind?->name ?? $unit->unit_class }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ $unit->status }}</td>
                                            <td class="px-4 py-3 text-sm text-right">
                                                @if($unit->status === 'ACTIVE')
                                                    <form method="POST" action="{{ route('settings.units.retire', $unit) }}"
                                                          onsubmit="return confirm('Retire {{ $unit->canonical_name }}? Existing stock keeps it; new items will not offer it.')">
                                                        @csrf
                                                        <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium">
                                                            Retire
                                                        </button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-4 py-4 text-sm text-gray-500">
                                                No units yet. Add one above, or run <code class="font-mono">php artisan units:install</code>.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            {{ $units->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
