<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6" x-data="{ lines: @js(collect(old('lines', [['item_id' => '', 'quantity_suom' => '', 'batch_number' => '']]))->values()) }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <a href="{{ route('inventory.returns.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back</a>
        <h2 class="mt-4 text-2xl font-bold text-gray-900">New goods return</h2>
        @include('inventory.partials.subnav')

        @if ($errors->any())
            <div class="mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded text-sm">
                <ul class="list-disc list-inside">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('inventory.returns.store') }}" class="mt-6 space-y-6">
            @csrf
            <div class="bg-white shadow sm:rounded-lg p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Store</label>
                    <select name="store_id" required class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        @foreach($stores as $id => $label)
                            <option value="{{ $id }}" @selected(old('store_id') == $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Supplier</label>
                    <select name="supplier_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <option value="">— Optional —</option>
                        @foreach($suppliers as $id => $name)
                            <option value="{{ $id }}" @selected(old('supplier_id') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Return date</label>
                    <input type="date" name="return_date" required value="{{ old('return_date', now()->toDateString()) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Reason</label>
                    <select name="reason_code" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <option value="">Select reason</option>
                        @foreach($reasonOptions as $code => $label)
                            <option value="{{ $code }}" @selected(old('reason_code') === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea name="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="flex justify-between mb-4">
                    <h3 class="text-lg font-medium">Return lines</h3>
                    <button type="button" @click="lines.push({item_id:'',quantity_suom:'',batch_number:''})" class="text-sm text-blue-600">+ Add line</button>
                </div>
                <template x-for="(line, i) in lines" :key="i">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 mb-3 p-3 border rounded-lg">
                        <div class="md:col-span-5">
                            <label class="text-xs text-gray-600">Item</label>
                            <select :name="'lines['+i+'][item_id]'" x-model="line.item_id" required class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                <option value="">Select item</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-xs text-gray-600">Qty SUOM</label>
                            <input type="number" step="0.0001" min="0.0001" :name="'lines['+i+'][quantity_suom]'" x-model="line.quantity_suom" required class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div class="md:col-span-3">
                            <label class="text-xs text-gray-600">Batch</label>
                            <input type="text" :name="'lines['+i+'][batch_number]'" x-model="line.batch_number" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div class="md:col-span-2 flex items-end">
                            <button type="button" @click="lines.splice(i,1)" class="text-sm text-red-600">Remove</button>
                        </div>
                    </div>
                </template>
            </div>
            <div class="flex justify-end gap-3">
                <a href="{{ route('inventory.returns.index') }}" class="px-4 py-2 border rounded-md text-sm bg-white">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm">Save draft</button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
