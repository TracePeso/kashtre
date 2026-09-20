<x-app-layout>
    <div class="py-12" x-data="{ showModal: false }" x-cloak>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="mb-4">
                    <h1 class="text-xl font-semibold text-gray-900">Item Units</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Units this business uses on items. Pick from the shared catalog, or add a local packaging name.
                        Change sale and order unit on the item itself.
                    </p>
                </div>

                @if (session('success'))
                    <div x-data="{ show: true }" x-show="show"
                        class="relative bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 transition"
                        role="alert">
                        <span class="block sm:inline">{{ session('success') }}</span>
                        <button @click="show = false"
                            class="absolute top-1 right-2 text-xl font-semibold text-green-700">
                            &times;
                        </button>
                    </div>
                @endif

                @livewire('item-units.list-item-units')
            </div>
        </div>



    </div>
</x-app-layout>
