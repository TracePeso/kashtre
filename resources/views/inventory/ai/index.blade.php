<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl">AI briefing</h2>
        <p class="mt-1 text-sm text-gray-500">
            Read the last twelve weeks, then ask for a draft. Nothing here places an order, moves stock, or writes off expiry.
        </p>

        @include('inventory.partials.subnav')

        <div class="mt-6">
            @livewire('inventory.ai-advice-briefing')
        </div>
    </div>
</div>
</x-app-layout>
