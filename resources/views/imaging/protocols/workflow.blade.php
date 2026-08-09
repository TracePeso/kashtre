<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">

                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-lg font-medium text-gray-900">Configure Workflow</h2>
                        <p class="text-sm text-gray-500">{{ $protocol->name }} ({{ $protocol->code }})</p>
                    </div>
                    <a href="{{ route('imaging-protocols.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to Protocols</a>
                </div>

                @livewire('imaging.manage-protocol-workflow', ['protocol' => $protocol])

            </div>
        </div>
    </div>
</x-app-layout>
