<x-app-layout>
    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-8 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-amber-100 dark:bg-amber-900/30 mb-4">
                    <svg class="h-6 w-6 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">No patient found</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300 mb-1">
                    There is no patient on record with the id
                </p>
                <p class="text-sm font-mono bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded px-3 py-1.5 inline-block mb-4">
                    {{ $clientId }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-6">
                    This usually means the id was mistyped, or copied from somewhere in a shortened
                    form — a near-miss on the real id matches nothing, it doesn't find the closest patient.
                    Double-check the id (and that it belongs to your business) rather than assuming
                    this patient simply has no chart yet.
                </p>
                <a href="{{ route('clients.index') }}"
                    class="inline-flex items-center text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">
                    Find the right patient
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
