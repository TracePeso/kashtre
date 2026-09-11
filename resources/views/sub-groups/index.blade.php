@php
    \Log::info('sub-groups/index.blade.php view started rendering');
@endphp

<x-app-layout>
    <div class="py-12" x-data="{ showModal: false }" x-cloak>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                
                @php
                    \Log::info('About to render Livewire component: sub-groups.list-sub-groups');
                @endphp

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

                @livewire('sub-groups.list-sub-groups')
                
                @php
                    \Log::info('Livewire component rendered successfully');
                @endphp
            </div>
        </div>



    </div>
</x-app-layout>

@php
    \Log::info('sub-groups/index.blade.php view finished rendering');
@endphp
