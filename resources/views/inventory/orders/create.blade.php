<x-app-layout>
@php
    $config = $moduleConfig ?? null;
    $periodOfOrderDaysValue = old('period_of_order_days');
    $defaultSafetyDays = (float) old('safety_stock_days', $config?->safety_stock_days ?? 0);
    $defaultBufferDays = (float) old('buffer_stock_days', $config?->buffer_stock_days ?? 0);
    $defaultNotificationDays = (float) old('notification_to_order_days', $config?->notification_to_order_days ?? 0);
    $initialOrderApproach = old('ordering_approach', in_array(old('budget_mode'), ['amount', 'days'], true) ? 'budget' : 'period');
    $budgetAmountValue = $initialOrderApproach === 'budget' ? old('budget_value') : '';
    $oldItemIds = collect(old('item_ids', []))->map(fn ($id) => (int) $id)->values();
    $defaultImportance = old('importance_filter', '');
    $initialOrderType = old('order_type', 'external');
    $initialSourceStoreId = old('source_store_id', '');
    $initialReceivingStoreId = old('store_id', '');
    $initialEditOrderSettings = $errors->hasAny(['safety_stock_days', 'buffer_stock_days', 'notification_to_order_days']);
    $safetyDays = (float) old('safety_stock_days', $defaultSafetyDays);
    $bufferDays = (float) old('buffer_stock_days', $defaultBufferDays);
    $notificationDays = (float) old('notification_to_order_days', $defaultNotificationDays);
    $oldCommitteeIds = collect(old('committee_members', $defaultCommitteeMemberIds ?? []))->map(fn ($id) => (int) $id)->values();
    $oldCommitteeChairId = old('committee_chair_user_id', $defaultCommitteeChairId ?? null);
@endphp
<div class="min-h-screen bg-gray-50 py-6" x-data="{
    orderApproach: '{{ $initialOrderApproach }}',
    orderType: '{{ $initialOrderType }}',
    storesList: @js($storesList),
    sourceStoreId: '{{ $initialSourceStoreId }}',
    receivingStoreId: '{{ $initialReceivingStoreId }}',
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
    editOrderSettings: @js($initialEditOrderSettings),
    defaultSafetyDays: {{ $defaultSafetyDays }},
    defaultBufferDays: {{ $defaultBufferDays }},
    defaultNotificationDays: {{ $defaultNotificationDays }},
    safetyDays: {{ $safetyDays }},
    bufferDays: {{ $bufferDays }},
    notificationDays: {{ $notificationDays }},
    resetOrderSettings() {
        this.safetyDays = this.defaultSafetyDays;
        this.bufferDays = this.defaultBufferDays;
        this.notificationDays = this.defaultNotificationDays;
        this.editOrderSettings = false;
    },
    storeById(id) {
        if (! id) return null;
        return this.storesList.find(store => String(store.id) === String(id)) || null;
    },
    canOrderBetween(fromId, toId) {
        const from = this.storeById(fromId);
        const to = this.storeById(toId);
        if (! from || ! to) return false;
        if (String(from.id) === String(to.id)) return false;
        if (from.parent_id !== null && Number(from.parent_id) === Number(to.id)) return true;
        if (to.parent_id !== null && Number(to.parent_id) === Number(from.id)) return true;
        if ((from.parent_id === null || from.parent_id === '') && (to.parent_id === null || to.parent_id === '')) return true;
        return false;
    },
    availableReceivingStores() {
        if (! this.sourceStoreId) return [];
        return this.storesList.filter(store => this.canOrderBetween(this.sourceStoreId, store.id));
    },
    internalOrderIsValid() {
        return this.sourceStoreId && this.receivingStoreId && this.canOrderBetween(this.sourceStoreId, this.receivingStoreId);
    },
    onOrderTypeChange() {
        if (this.orderType === 'external') {
            this.sourceStoreId = '';
        }
        if (this.orderType === 'internal' && this.activeTab === 'review') {
            this.activeTab = 'rules';
        }
    },
    onSourceStoreChange() {
        if (this.receivingStoreId && ! this.canOrderBetween(this.sourceStoreId, this.receivingStoreId)) {
            this.receivingStoreId = '';
        }
    },
    activeTab: 'setup',
    tabs() {
        return this.orderType === 'external'
            ? ['setup', 'items', 'rules', 'review']
            : ['setup', 'items', 'rules'];
    },
    tabIndex() {
        return this.tabs().indexOf(this.activeTab);
    },
    goNext() {
        const tabs = this.tabs();
        const idx = this.tabIndex();
        if (idx < tabs.length - 1) this.activeTab = tabs[idx + 1];
    },
    goPrev() {
        const tabs = this.tabs();
        const idx = this.tabIndex();
        if (idx > 0) this.activeTab = tabs[idx - 1];
    },
}">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">Make an order</h2>
            <p class="mt-1 text-sm text-gray-500">
                Suggested quantities use your stock position and <strong>auto-calculated consumption rates</strong> (15-day usage).
                Filter by category or group to focus on essential items.
            </p>
            <p class="mt-2">
                <a href="{{ route('inventory.orders.how-it-works') }}"
                   class="inline-flex items-center gap-1 text-sm font-medium text-blue-600 hover:text-blue-800">
                    How ordering works
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </p>
        </div>

        @include('inventory.partials.subnav')

        <form method="POST" action="{{ route('inventory.orders.store') }}" class="mt-6 bg-white shadow sm:rounded-lg overflow-hidden" novalidate>
            @csrf
            <input type="hidden" name="ordering_approach" :value="orderApproach">

            <div class="px-6 pt-5 border-b border-gray-200">
                <nav class="-mb-px flex flex-wrap gap-1" aria-label="Order form steps">
                    <button type="button" @click="activeTab = 'setup'"
                            :class="activeTab === 'setup' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="px-4 py-3 text-sm font-medium border-b-2">1. Type &amp; stores</button>
                    <button type="button" @click="activeTab = 'items'"
                            :class="activeTab === 'items' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="px-4 py-3 text-sm font-medium border-b-2">2. Items</button>
                    <button type="button" @click="activeTab = 'rules'"
                            :class="activeTab === 'rules' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="px-4 py-3 text-sm font-medium border-b-2">3. Ordering rules</button>
                    <button type="button" x-show="orderType === 'external'" @click="activeTab = 'review'"
                            :class="activeTab === 'review' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="px-4 py-3 text-sm font-medium border-b-2">4. Committee &amp; review</button>
                </nav>
            </div>

            <div class="p-6 space-y-6">
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

            <div x-show="activeTab === 'setup'" x-cloak class="space-y-6">
            <div class="border border-gray-200 rounded-lg p-4 space-y-4">
                <div>
                    <p class="text-sm font-medium text-gray-900">Order type</p>
                    <p class="text-xs text-gray-500 mt-0.5">External orders are for purchasing. Internal orders request stock from another store in your network.</p>
                </div>
                <div class="flex flex-wrap gap-4">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="radio" name="order_type" value="external" x-model="orderType" @change="onOrderTypeChange()"
                               class="text-blue-600 border-gray-300 focus:ring-blue-500">
                        <span>External <span class="text-gray-500">(purchase)</span></span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="radio" name="order_type" value="internal" x-model="orderType" @change="onOrderTypeChange()"
                               class="text-blue-600 border-gray-300 focus:ring-blue-500">
                        <span>Internal <span class="text-gray-500">(store to store)</span></span>
                    </label>
                </div>
                @error('order_type')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <template x-if="orderType === 'external'">
                <div class="space-y-4">
                    <div>
                        <label for="store_id" class="block text-sm font-medium text-gray-700">Receiving store <span class="text-red-500">*</span></label>
                        <select name="store_id" id="store_id" required x-model="receivingStoreId"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <option value="">Select store</option>
                            @foreach($stores as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Store that will receive the goods.</p>
                        @error('store_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </template>

            <template x-if="orderType === 'internal'">
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="source_store_id" class="block text-sm font-medium text-gray-700">Supplying store (from) <span class="text-red-500">*</span></label>
                            <select name="source_store_id" id="source_store_id" x-model="sourceStoreId" @change="onSourceStoreChange()" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                <option value="">Select supplying store</option>
                                @foreach($stores as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('source_store_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="internal_store_id" class="block text-sm font-medium text-gray-700">Receiving store (to) <span class="text-red-500">*</span></label>
                            <select name="store_id" id="internal_store_id" x-model="receivingStoreId" required
                                    :disabled="!sourceStoreId"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm disabled:bg-gray-50">
                                <option value="">Select receiving store</option>
                                <template x-for="store in availableReceivingStores()" :key="'recv-' + store.id">
                                    <option :value="String(store.id)" x-text="store.label"></option>
                                </template>
                            </select>
                            <p class="mt-1 text-xs text-gray-500" x-show="sourceStoreId && availableReceivingStores().length === 0" x-cloak>
                                No valid receiving stores for the selected supplying store.
                            </p>
                            @error('store_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div x-show="sourceStoreId && receivingStoreId && !internalOrderIsValid()" x-cloak
                         class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        Child stores cannot order directly from other child stores. Request stock through the parent distribution store first.
                    </div>
                    <p class="text-xs text-gray-500">Internal orders follow the same parent/child store rules as stock transfers. No supplier is required.</p>
                </div>
            </template>
            </div>

            <div x-show="activeTab === 'items'" x-cloak class="space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="importance_filter" class="block text-sm font-medium text-gray-700">Category</label>
                    <select name="importance_filter" id="importance_filter"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="">All items</option>
                        @foreach($importanceOptions as $value => $label)
                            <option value="{{ $value }}" @selected($defaultImportance === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Optional filter. Leave as “All items” to include every stocked good.</p>
                    @error('importance_filter')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
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
                    <p class="text-xs text-gray-500">The picker respects your category, group, and subgroup filters above.</p>
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
            </div>

            <div x-show="activeTab === 'rules'" x-cloak class="space-y-6">
            <div class="border border-gray-200 rounded-lg p-4 space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-900">How to order</p>
                    </div>
                    <a href="{{ route('inventory.orders.how-it-works') }}"
                       class="shrink-0 text-sm font-medium text-blue-600 hover:text-blue-800">
                        How it works
                    </a>
                </div>

                <div class="inline-flex flex-wrap gap-1 rounded-lg border border-gray-200 p-1 bg-gray-50" role="group" aria-label="Ordering method">
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
                    <div class="space-y-3">
                        <input type="hidden" name="budget_mode" value="amount">
                        <div>
                            <label for="budget_value_amount" class="block text-sm font-medium text-gray-700">Budget (UGX)</label>
                            <input type="number" step="0.01" min="1" name="budget_value" id="budget_value_amount"
                                   value="{{ $budgetAmountValue }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                   placeholder="e.g. 25415.94">
                            <p class="mt-1 text-xs text-gray-500">Enter the UGX amount (Excel BA7). Quantities use AH → AL; days are not typed here.</p>
                        </div>
                        @error('budget_value')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </template>

                <template x-if="orderApproach === 'period'">
                    <div class="space-y-3">
                        <div>
                            <label for="period_of_order_days" class="block text-sm font-medium text-gray-700">Period of order (days)</label>
                            <input type="number" step="1" min="1" name="period_of_order_days" id="period_of_order_days"
                                   value="{{ $periodOfOrderDaysValue }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                    </div>
                </template>

                @error('period_of_order_days')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="border border-gray-200 rounded-lg p-4 space-y-4">
                <div>
                    <p class="text-sm font-medium text-gray-900">Peak period</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="peak_period_percent" class="block text-sm font-medium text-gray-700">Anticipated peak period (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="peak_period_percent" id="peak_period_percent"
                               value="{{ old('peak_period_percent', 0) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        @error('peak_period_percent')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="peak_consumption_increase_percent" class="block text-sm font-medium text-gray-700">Expected increase in consumption during peak period (%)</label>
                        <input type="number" step="0.01" min="0" max="1000" name="peak_consumption_increase_percent" id="peak_consumption_increase_percent"
                               value="{{ old('peak_consumption_increase_percent', 0) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        @error('peak_consumption_increase_percent')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <div class="border border-gray-200 rounded-lg p-4 space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-900">Order settings</p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            From your inventory module defaults. Safety and buffer days affect suggested quantities; notification days affect when to place the order.
                        </p>
                    </div>
                    <button type="button"
                            x-show="!editOrderSettings"
                            @click="editOrderSettings = true"
                            class="shrink-0 text-sm font-medium text-blue-600 hover:text-blue-800">
                        Edit
                    </button>
                    <button type="button"
                            x-show="editOrderSettings"
                            @click="resetOrderSettings()"
                            class="shrink-0 text-sm font-medium text-gray-600 hover:text-gray-800">
                        Use defaults
                    </button>
                </div>

                <template x-if="!editOrderSettings">
                    <div>
                        <input type="hidden" name="safety_stock_days" :value="safetyDays">
                        <input type="hidden" name="buffer_stock_days" :value="bufferDays">
                        <input type="hidden" name="notification_to_order_days" :value="notificationDays">
                    </div>
                </template>

                <div x-show="!editOrderSettings" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="rounded-md bg-slate-50 border border-slate-200 px-3 py-2.5">
                        <p class="text-xs font-medium text-gray-500">Safety stock (days)</p>
                        <p class="mt-0.5 text-lg font-semibold text-gray-900 tabular-nums" x-text="safetyDays"></p>
                        <p class="mt-1 text-xs text-gray-500">Minimum days of stock to retain before reordering.</p>
                    </div>
                    <div class="rounded-md bg-slate-50 border border-slate-200 px-3 py-2.5">
                        <p class="text-xs font-medium text-gray-500">Buffer stock (days)</p>
                        <p class="mt-0.5 text-lg font-semibold text-gray-900 tabular-nums" x-text="bufferDays"></p>
                        <p class="mt-1 text-xs text-gray-500">Extra days of cover beyond safety stock.</p>
                    </div>
                    <div class="rounded-md bg-slate-50 border border-slate-200 px-3 py-2.5">
                        <p class="text-xs font-medium text-gray-500">Notification to order (days)</p>
                        <p class="mt-0.5 text-lg font-semibold text-gray-900 tabular-nums" x-text="notificationDays"></p>
                        <p class="mt-1 text-xs text-gray-500">Lead time from stockout warning to placing the order.</p>
                    </div>
                </div>

                <div x-show="editOrderSettings" x-cloak class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="safety_stock_days" class="block text-sm font-medium text-gray-700">Safety stock (days)</label>
                        <input type="number" step="1" min="0" name="safety_stock_days" id="safety_stock_days"
                               x-model.number="safetyDays"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <p class="mt-1 text-xs text-gray-500">Minimum days of stock to retain before reordering.</p>
                        @error('safety_stock_days')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="buffer_stock_days" class="block text-sm font-medium text-gray-700">Buffer stock (days)</label>
                        <input type="number" step="1" min="0" name="buffer_stock_days" id="buffer_stock_days"
                               x-model.number="bufferDays"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <p class="mt-1 text-xs text-gray-500">Extra days of cover beyond safety stock.</p>
                        @error('buffer_stock_days')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="notification_to_order_days" class="block text-sm font-medium text-gray-700">Notification to order (days)</label>
                        <input type="number" step="1" min="0" name="notification_to_order_days" id="notification_to_order_days"
                               x-model.number="notificationDays"
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
            </div>

            <div x-show="activeTab === 'review' && orderType === 'external'" x-cloak class="space-y-6">
                <div class="rounded-lg border border-indigo-200 bg-indigo-50/40 p-4">
                    <h3 class="text-sm font-semibold text-gray-900">Evaluation committee</h3>
                    <p class="text-xs text-gray-500 mt-1">
                        @if($evaluationCommitteeRequired ?? false)
                            Pre-appoint members who will evaluate supplier quotations after approval. Required before submission for your organisation.
                        @else
                            Optionally pre-appoint members who will evaluate supplier quotations after approval. Defaults come from Inventory → Settings.
                        @endif
                    </p>
                    <div class="mt-4">
                        @include('inventory.partials.committee-member-fields', [
                            'businessUsers' => $businessUsers ?? collect(),
                            'selectedMemberIds' => $oldCommitteeIds->all(),
                            'chairUserId' => $oldCommitteeChairId,
                            'required' => $evaluationCommitteeRequired ?? false,
                        ])
                    </div>
                </div>
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
                    <p class="font-medium text-gray-900">Ready to generate</p>
                    <p class="mt-1">After generating, review line quantities on the order page, adjust the committee if needed, then submit for approval.</p>
                </div>
            </div>

            <div class="flex flex-wrap justify-between gap-3 pt-4 border-t border-gray-200">
                <a href="{{ route('inventory.orders.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</a>
                <div class="flex flex-wrap gap-2">
                    <button type="button" x-show="tabIndex() > 0" @click="goPrev()"
                            class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Previous
                    </button>
                    <button type="button" x-show="tabIndex() < tabs().length - 1" @click="goNext()"
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-slate-600 hover:bg-slate-700">
                        Next
                    </button>
                    <button type="submit" x-show="orderType === 'internal' ? activeTab === 'rules' : activeTab === 'review'"
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                        Generate order
                    </button>
                </div>
            </div>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
