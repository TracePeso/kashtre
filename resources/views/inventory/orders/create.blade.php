<x-app-layout>
@php
    $config = $moduleConfig ?? null;
    $defaultPeriodDays = (int) old('period_of_order_days', $config?->period_of_order_days ?? 30);
    $defaultSafetyDays = (int) old('safety_stock_days', $config?->safety_stock_days ?? 0);
    $defaultBufferDays = (int) old('buffer_stock_days', $config?->buffer_stock_days ?? 0);
    $defaultNotificationDays = (int) old('notification_to_order_days', $config?->notification_to_order_days ?? 0);
    $initialBudgetMode = old('budget_mode');
    $initialOrderApproach = $initialBudgetMode ? 'budget' : 'period';
    $initialBudgetType = in_array($initialBudgetMode, ['days', 'amount'], true) ? $initialBudgetMode : 'amount';
    $oldItemIds = collect(old('item_ids', []))->map(fn ($id) => (int) $id)->values();
@endphp
<div class="min-h-screen bg-gray-50 py-6" x-data="{
    orderApproach: '{{ $initialOrderApproach }}',
    budgetType: '{{ $initialBudgetType }}',
    items: @js($items->map(fn ($item) => [
        'id' => $item->id,
        'name' => $item->name,
        'code' => $item->code,
        'importance' => $item->importance_category,
        'group_id' => $item->group_id,
        'subgroup_id' => $item->subgroup_id,
    ])->values()),
    selectedItemIds: @js($oldItemIds),
    itemSearch: '',
    limitItems: @js($oldItemIds->isNotEmpty()),
    isItemSelected(id) {
        return this.selectedItemIds.includes(Number(id));
    },
    toggleItem(id) {
        const numericId = Number(id);
        if (this.isItemSelected(numericId)) {
            this.selectedItemIds = this.selectedItemIds.filter(itemId => itemId !== numericId);
        } else {
            this.selectedItemIds.push(numericId);
        }
    },
    selectAllVisible() {
        const visibleIds = this.visibleItems().map(item => Number(item.id));
        this.selectedItemIds = [...new Set([...this.selectedItemIds, ...visibleIds])];
    },
    clearSelection() {
        this.selectedItemIds = [];
    },
    visibleItems() {
        const search = this.itemSearch.trim().toLowerCase();
        const importance = document.getElementById('importance_filter')?.value || '';
        const groupId = document.getElementById('group_id')?.value || '';
        const subgroupId = document.getElementById('subgroup_id')?.value || '';

        return this.items.filter(item => {
            if (search) {
                const haystack = (item.name + ' ' + (item.code || '')).toLowerCase();
                if (! haystack.includes(search)) {
                    return false;
                }
            }

            if (! this.limitItems) {
                return true;
            }

            if (importance && item.importance !== importance) {
                return false;
            }

            if (groupId && String(item.group_id) !== groupId) {
                return false;
            }

            if (subgroupId && String(item.subgroup_id) !== subgroupId) {
                return false;
            }

            return true;
        });
    },
}">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">New order</h2>
            <p class="mt-1 text-sm text-gray-500">
                Suggested quantities use your stock position and <strong>auto-calculated consumption rates</strong> (15-day usage).
                Filter by category or group to focus on essential items.
            </p>
        </div>

        @include('inventory.partials.subnav')

        <form method="POST" action="{{ route('inventory.orders.store') }}" class="mt-6 bg-white shadow sm:rounded-lg p-6 space-y-6" novalidate>
            @csrf

            @if ($errors->any())
                <div class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                    <p class="font-medium">Could not generate this order:</p>
                    <ul class="mt-1 list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

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

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="importance_filter" class="block text-sm font-medium text-gray-700">Importance</label>
                    <select name="importance_filter" id="importance_filter"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="">All items</option>
                        @foreach($importanceOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('importance_filter', 'essential') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Defaults to essential items.</p>
                </div>
                <div>
                    <label for="group_id" class="block text-sm font-medium text-gray-700">Group</label>
                    <select name="group_id" id="group_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="">All groups</option>
                        @foreach($groupOptions as $id => $label)
                            <option value="{{ $id }}" @selected(old('group_id') == $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="subgroup_id" class="block text-sm font-medium text-gray-700">Subgroup</label>
                    <select name="subgroup_id" id="subgroup_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="">All subgroups</option>
                        @foreach($subgroupOptions as $id => $label)
                            <option value="{{ $id }}" @selected(old('subgroup_id') == $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="border border-gray-200 rounded-lg p-4 space-y-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-900">Specific items <span class="font-normal text-gray-500">(optional)</span></p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Leave empty to include all matching items from stock and consumption.
                            Select items to generate the order for only those products.
                        </p>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" x-model="limitItems" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span>Choose items</span>
                    </label>
                </div>

                <div x-show="limitItems" x-cloak class="rounded-lg border border-gray-200 bg-gray-50/50 p-3 space-y-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-xs text-gray-600" x-text="selectedItemIds.length + ' selected'"></span>
                        <div class="flex flex-wrap gap-2 text-xs">
                            <button type="button" @click="selectAllVisible()" class="text-blue-700 hover:text-blue-900 font-medium">Select visible</button>
                            <span class="text-gray-300">|</span>
                            <button type="button" @click="clearSelection()" class="text-gray-600 hover:text-gray-800 font-medium">Clear</button>
                        </div>
                    </div>
                    <input type="search" x-model="itemSearch" placeholder="Search by name or code…"
                           class="block w-full rounded-md border-gray-300 py-1.5 px-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <p class="text-xs text-gray-500">The picker respects your importance, group, and subgroup filters above.</p>
                    <div class="max-h-48 overflow-y-auto rounded border border-gray-200 bg-white divide-y divide-gray-100">
                        <template x-if="visibleItems().length === 0">
                            <p class="px-3 py-4 text-xs text-gray-500 text-center">No items match your search or filters.</p>
                        </template>
                        <template x-for="item in visibleItems()" :key="'order-item-' + item.id">
                            <label class="flex items-start gap-2 px-3 py-2 hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                       :checked="isItemSelected(item.id)" @change="toggleItem(item.id)">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium text-gray-900 truncate" x-text="item.name"></span>
                                    <span class="block text-xs text-gray-500" x-text="item.code || '—'"></span>
                                </span>
                            </label>
                        </template>
                    </div>
                    <template x-for="id in selectedItemIds" :key="'item-id-' + id">
                        <input type="hidden" name="item_ids[]" :value="id">
                    </template>
                    @error('item_ids')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    @error('item_ids.*')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="border border-gray-200 rounded-lg p-4 space-y-4">
                <div>
                    <p class="text-sm font-medium text-gray-900">How to order</p>
                    <p class="text-xs text-gray-500 mt-0.5">Choose whether quantities are driven by a stock period or a budget target.</p>
                </div>

                <div class="inline-flex rounded-lg border border-gray-200 p-1 bg-gray-50" role="group" aria-label="Ordering method">
                    <button type="button"
                            @click="orderApproach = 'period'"
                            :class="orderApproach === 'period'
                                ? 'bg-white text-blue-700 shadow-sm ring-1 ring-gray-200'
                                : 'text-gray-600 hover:text-gray-900'"
                            class="px-4 py-2 text-sm font-medium rounded-md transition">
                        By period (days)
                    </button>
                    <button type="button"
                            @click="orderApproach = 'budget'"
                            :class="orderApproach === 'budget'
                                ? 'bg-white text-blue-700 shadow-sm ring-1 ring-gray-200'
                                : 'text-gray-600 hover:text-gray-900'"
                            class="px-4 py-2 text-sm font-medium rounded-md transition">
                        By budget
                    </button>
                </div>

                <template x-if="orderApproach === 'budget'">
                    <div class="space-y-4">
                        <template x-if="budgetType === 'days'">
                            <input type="hidden" name="budget_mode" value="days">
                        </template>
                        <template x-if="budgetType === 'amount'">
                            <input type="hidden" name="budget_mode" value="amount">
                        </template>

                        <div class="inline-flex rounded-lg border border-gray-200 p-1 bg-gray-50" role="group" aria-label="Budget type">
                            <button type="button"
                                    @click="budgetType = 'days'"
                                    :class="budgetType === 'days'
                                        ? 'bg-white text-blue-700 shadow-sm ring-1 ring-gray-200'
                                        : 'text-gray-600 hover:text-gray-900'"
                                    class="px-3 py-1.5 text-sm font-medium rounded-md transition">
                                Stock days
                            </button>
                            <button type="button"
                                    @click="budgetType = 'amount'"
                                    :class="budgetType === 'amount'
                                        ? 'bg-white text-blue-700 shadow-sm ring-1 ring-gray-200'
                                        : 'text-gray-600 hover:text-gray-900'"
                                    class="px-3 py-1.5 text-sm font-medium rounded-md transition">
                                Amount (UGX)
                            </button>
                        </div>

                        <div>
                            <label for="budget_value" class="block text-sm font-medium text-gray-700">
                                <span x-text="budgetType === 'days' ? 'Stock-days budget' : 'Budget cap (UGX)'"></span>
                            </label>
                            <input type="number" :step="budgetType === 'days' ? 1 : 0.01" min="0" name="budget_value" id="budget_value"
                                   value="{{ old('budget_value') }}"
                                   required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <p class="mt-1 text-xs text-gray-500" x-show="budgetType === 'days'">
                                Target stock-days to distribute across items.
                            </p>
                            <p class="mt-1 text-xs text-gray-500" x-show="budgetType === 'amount'" x-cloak>
                                Items are calculated from the order period, then scaled down to this total amount.
                            </p>
                            @error('budget_value')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            @error('budget_mode')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <template x-if="budgetType === 'amount'">
                            <div>
                                <label for="period_of_order_days_budget" class="block text-sm font-medium text-gray-700">Period of order (days)</label>
                                <input type="number" step="1" min="0" name="period_of_order_days" id="period_of_order_days_budget"
                                       value="{{ $defaultPeriodDays }}"
                                       required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                <p class="mt-1 text-xs text-gray-500">Initial quantities use this period before the budget cap is applied.</p>
                            </div>
                        </template>
                    </div>
                </template>

                <template x-if="orderApproach === 'period'">
                    <div class="space-y-3">
                        <div class="rounded-md bg-blue-50 border border-blue-100 px-3 py-2 text-xs text-blue-800">
                            Suggested quantities cover the <strong>period of order</strong> plus safety and buffer stock, based on current consumption.
                        </div>
                        <div>
                            <label for="period_of_order_days" class="block text-sm font-medium text-gray-700">Period of order (days)</label>
                            <input type="number" step="1" min="0" name="period_of_order_days" id="period_of_order_days"
                                   value="{{ $defaultPeriodDays }}"
                                   required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <p class="mt-1 text-xs text-gray-500">Comfort stock period covered by this order.</p>
                        </div>
                    </div>
                </template>

                @error('period_of_order_days')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="border border-gray-200 rounded-lg p-4 space-y-4">
                <div>
                    <p class="text-sm font-medium text-gray-900">Order settings</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Pre-filled from your inventory module defaults. Safety and buffer days affect suggested quantities; notification days affect when to place the order.
                    </p>
                </div>

                <div>
                    <label for="peak_period_percent" class="block text-sm font-medium text-gray-700">Anticipated peak period (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="peak_period_percent" id="peak_period_percent"
                           value="{{ old('peak_period_percent', 0) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    <p class="mt-1 text-xs text-gray-500">Share of the order period expected to be peak demand.</p>
                    @error('peak_period_percent')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="safety_stock_days" class="block text-sm font-medium text-gray-700">Safety stock (days)</label>
                        <input type="number" step="1" min="0" name="safety_stock_days" id="safety_stock_days"
                               value="{{ $defaultSafetyDays }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <p class="mt-1 text-xs text-gray-500">Minimum days of stock to retain before reordering.</p>
                        @error('safety_stock_days')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="buffer_stock_days" class="block text-sm font-medium text-gray-700">Buffer stock (days)</label>
                        <input type="number" step="1" min="0" name="buffer_stock_days" id="buffer_stock_days"
                               value="{{ $defaultBufferDays }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <p class="mt-1 text-xs text-gray-500">Extra days of cover beyond safety stock.</p>
                        @error('buffer_stock_days')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="notification_to_order_days" class="block text-sm font-medium text-gray-700">Notification to order (days)</label>
                        <input type="number" step="1" min="0" name="notification_to_order_days" id="notification_to_order_days"
                               value="{{ $defaultNotificationDays }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <p class="mt-1 text-xs text-gray-500">Lead time from stockout warning to placing the order.</p>
                        @error('notification_to_order_days')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
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
