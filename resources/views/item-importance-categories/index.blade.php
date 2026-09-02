<x-app-layout>
    <div class="py-12" x-cloak>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-6">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">Item categories (importance)</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Define importance categories for goods. Each item must belong to a category when created.
                </p>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                @if (session('success'))
                    <div x-data="{ show: true }" x-show="show"
                        class="relative bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"
                        role="alert">
                        <span class="block sm:inline">{{ session('success') }}</span>
                        <button @click="show = false" class="absolute top-1 right-2 text-xl font-semibold text-green-700">&times;</button>
                    </div>
                @endif

                @livewire('item-importance-categories.list-item-importance-categories')
            </div>
        </div>
    </div>
</x-app-layout>
