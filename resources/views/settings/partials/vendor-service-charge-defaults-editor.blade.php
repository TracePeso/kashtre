@php
    $initialTiers = old('service_charges')
        ? array_values(old('service_charges'))
        : ($recommendedVendorChargeTiers ?? []);
@endphp

@if ($errors->any() && ($activeTab ?? '') === 'vendor-service-charges')
    <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded text-sm">
        <ul class="list-disc list-inside">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('settings.vendor-service-charge-defaults.update') }}" method="POST" class="mb-8 pb-8 border-b border-gray-200 dark:border-gray-700">
    @csrf

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Defaults</h3>
        <div class="flex gap-2">
            @if($canManageVendorChargeDefaults ?? false)
                <button type="button" id="tp-defaults-add-tier"
                        class="text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
                    + Add tier
                </button>
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium py-1.5 px-4 rounded-md">
                    Save
                </button>
            @endif
        </div>
    </div>

    <div id="tp-defaults-tiers-container" class="space-y-3"></div>
</form>

<template id="tp-defaults-tier-template">
    <div class="tp-defaults-tier-row grid grid-cols-2 md:grid-cols-5 gap-3 items-end border border-gray-200 dark:border-gray-600 rounded-md p-3 bg-gray-50 dark:bg-gray-900/30">
        <div>
            <label class="block text-xs text-gray-500 mb-1">From (UGX)</label>
            <input type="number" step="0.01" min="0" required
                   class="tier-lower w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 rounded text-sm py-1.5"
                   @disabled(!($canManageVendorChargeDefaults ?? false))>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">To</label>
            <input type="number" step="0.01" min="0"
                   class="tier-upper w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 rounded text-sm py-1.5"
                   placeholder="No limit"
                   @disabled(!($canManageVendorChargeDefaults ?? false))>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Charge</label>
            <input type="number" step="0.01" min="0" required
                   class="tier-amount w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 rounded text-sm py-1.5"
                   @disabled(!($canManageVendorChargeDefaults ?? false))>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Type</label>
            <select class="tier-type w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 rounded text-sm py-1.5"
                    @disabled(!($canManageVendorChargeDefaults ?? false))>
                <option value="percentage">%</option>
                <option value="fixed">Fixed</option>
            </select>
        </div>
        <div class="flex justify-end">
            @if($canManageVendorChargeDefaults ?? false)
                <button type="button" class="remove-tier text-xs text-red-600 hover:text-red-800">Remove</button>
            @endif
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('tp-defaults-tiers-container');
    const template = document.getElementById('tp-defaults-tier-template');
    const addBtn = document.getElementById('tp-defaults-add-tier');
    if (!container || !template) return;

    const initialTiers = @json($initialTiers);
    const canEdit = @json($canManageVendorChargeDefaults ?? false);
    let itemIndex = 0;

    function addTierRow(data) {
        const clone = template.content.cloneNode(true);
        const row = clone.querySelector('.tp-defaults-tier-row');
        const idx = itemIndex++;
        const base = `service_charges[${idx}]`;
        row.querySelector('.tier-lower').name = base + '[lower_bound]';
        row.querySelector('.tier-upper').name = base + '[upper_bound]';
        row.querySelector('.tier-amount').name = base + '[amount]';
        row.querySelector('.tier-type').name = base + '[type]';
        if (data) {
            row.querySelector('.tier-lower').value = data.lower_bound ?? '';
            row.querySelector('.tier-upper').value = data.upper_bound ?? '';
            row.querySelector('.tier-amount').value = data.amount ?? '';
            row.querySelector('.tier-type').value = data.type ?? 'percentage';
        }
        if (canEdit) {
            row.querySelector('.remove-tier')?.addEventListener('click', () => { row.remove(); });
        }
        container.appendChild(row);
    }

    if (addBtn && canEdit) addBtn.addEventListener('click', () => addTierRow(null));
    (initialTiers.length ? initialTiers : [null]).forEach(t => addTierRow(t));
});
</script>
