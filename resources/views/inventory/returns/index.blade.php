<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="md:flex md:items-center md:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Goods Returns</h2>
                <p class="mt-1 text-sm text-gray-500">Return damaged, defective, or incorrect goods to suppliers (Excel BM–BT).</p>
            </div>
            <a href="{{ route('inventory.returns.create') }}" class="mt-4 md:mt-0 inline-flex px-4 py-2 bg-blue-600 text-white text-sm rounded-md">New return</a>
        </div>
        @include('inventory.partials.subnav')
        <div class="mt-6 bg-white shadow sm:rounded-lg p-6">@livewire('inventory.list-goods-returns')</div>
    </div>
</div>
</x-app-layout>
