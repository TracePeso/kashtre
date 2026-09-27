<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl">Stock advice</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Review this store’s last 12 weeks, then ask for a draft. Nothing here places an order, moves stock, or writes off expiry.
                </p>
            </div>
            <p class="text-xs text-slate-500">Draft only · a person still decides</p>
        </div>

        @include('inventory.partials.subnav')

        <div class="mt-6">
            @livewire('inventory.ai-advice-briefing')
        </div>
    </div>
</div>
</x-app-layout>
