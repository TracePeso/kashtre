<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="client-spaces-page bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">

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

                <style>
                    .client-spaces-page .fi-ta-header-toolbar .fi-btn,
                    .client-spaces-page .fi-ta-empty-state-actions .fi-btn {
                        background-color: #011478 !important;
                        border-color: #011478 !important;
                        color: #ffffff !important;
                        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.12) !important;
                    }

                    .client-spaces-page .fi-ta-header-toolbar .fi-btn:hover,
                    .client-spaces-page .fi-ta-empty-state-actions .fi-btn:hover {
                        background-color: rgba(1, 20, 120, 0.9) !important;
                        border-color: rgba(1, 20, 120, 0.9) !important;
                    }

                    .client-spaces-page .fi-ta-header-toolbar .fi-btn-label,
                    .client-spaces-page .fi-ta-empty-state-actions .fi-btn-label {
                        color: #ffffff !important;
                    }
                </style>
                @livewire('client-spaces.list-client-spaces')

            </div>
        </div>
    </div>
</x-app-layout>
