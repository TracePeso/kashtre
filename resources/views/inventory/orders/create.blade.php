<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">New order form</h2>
            <p class="mt-1 text-sm text-gray-500">Suggested quantities follow Excel AF (period) or AH–AL (budget days) using graduated moving averages and 15-day consumption rates.</p>
        </div>

        @include('inventory.partials.subnav')

        <form method="POST" action="{{ route('inventory.orders.store') }}" class="mt-6 bg-white shadow sm:rounded-lg p-6 space-y-6">
            @csrf

            <div>
                <label for="store_id" class="block text-sm font-medium text-gray-700">Store</label>
                <select name="store_id" id="store_id" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    <option value="">Select store</option>
                    @foreach($stores as $id => $label)
                        <option value="{{ $id }}" @selected(old('store_id') == $id)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('store_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="moving_average_days" class="block text-sm font-medium text-gray-700">Consumption rate window (days)</label>
                <select name="moving_average_days" id="moving_average_days" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    @foreach([15, 30, 90, 180, 360] as $days)
                        <option value="{{ $days }}" @selected(old('moving_average_days', 30) == $days)>{{ $days }} days</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">Daily average consumption is calculated over this window from automatically recorded sale consumption.</p>
            </div>

            <div>
                <label for="period_of_order_days" class="block text-sm font-medium text-gray-700">Period of order (days)</label>
                <input type="number" step="0.01" min="0" name="period_of_order_days" id="period_of_order_days"
                       value="{{ old('period_of_order_days', 30) }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                <p class="mt-1 text-xs text-gray-500">Comfort stock period added to safety, buffer, lead time, and notification days.</p>
            </div>

            <div>
                <label for="importance_filter" class="block text-sm font-medium text-gray-700">Category of importance</label>
                <select name="importance_filter" id="importance_filter"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    <option value="">All items</option>
                    @foreach($importanceOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('importance_filter') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">Items without a category are only included when <strong>All items</strong> is selected. Set categories on each good under Items → edit.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="budget_mode" class="block text-sm font-medium text-gray-700">Budget mode (optional)</label>
                    <select name="budget_mode" id="budget_mode"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="">None</option>
                        <option value="days" @selected(old('budget_mode') === 'days')>Stock days</option>
                        <option value="amount" @selected(old('budget_mode') === 'amount')>Amount (UGX)</option>
                    </select>
                </div>
                <div>
                    <label for="budget_value" class="block text-sm font-medium text-gray-700">Budget value</label>
                    <input type="number" step="0.01" min="0" name="budget_value" id="budget_value" value="{{ old('budget_value') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                </div>
            </div>

            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                <textarea name="notes" id="notes" rows="3"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">{{ old('notes') }}</textarea>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('inventory.orders.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</a>
                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">Generate order</button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
