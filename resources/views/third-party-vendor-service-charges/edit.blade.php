<x-app-layout>
<div class="container mx-auto px-4 py-8 max-w-6xl">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Edit vendor charges: {{ $business->name }}</h1>
            <p class="text-sm text-gray-600 mt-1">
                @if(($insuranceCompanyId ?? null) !== null && ($insuranceCompany ?? null))
                    Schedule for <strong>{{ $insuranceCompany->name }}</strong> only. Vendor-specific tiers override the clinic-wide “all vendors” schedule for this insurer.
                @else
                    Clinic-wide schedule for <strong>all</strong> third-party vendors. Saving replaces every tier in this schedule only.
                @endif
            </p>
        </div>
        <a href="{{ route('third-party-vendor-service-charges.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded-lg">
            Back to list
        </a>
    </div>

    @if ($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('third-party-vendor-service-charges.update', $business) }}" method="POST" class="bg-white shadow-md rounded-lg p-6">
        @csrf
        @method('PUT')
        @if(($insuranceCompanyId ?? null) !== null)
            <input type="hidden" name="insurance_company_id" value="{{ $insuranceCompanyId }}">
        @endif

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-gray-900">Tiered charges</h2>
            <div class="flex gap-2">
                @if(count($defaultTiers ?? []) > 0)
                    <button type="button" id="load-default-tiers"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold py-2 px-4 rounded-lg">
                        Reset to recommended defaults
                    </button>
                @endif
                <button type="button" id="add-tier-row"
                        class="bg-green-600 hover:bg-green-700 text-white text-sm font-semibold py-2 px-4 rounded-lg">
                    Add tier
                </button>
            </div>
        </div>

        <div id="tp-vendor-tiers-container"></div>

        <div class="flex justify-end gap-3 mt-8 pt-4 border-t border-gray-100">
            <a href="{{ route('third-party-vendor-service-charges.index') }}" class="bg-gray-100 text-gray-800 font-semibold py-2 px-4 rounded-lg hover:bg-gray-200">Cancel</a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg">
                Update tiers
            </button>
        </div>
    </form>
</div>

@php
    $tiersPayload = $tiers->map(fn ($t) => [
        'lower_bound' => (float) $t->lower_bound,
        'upper_bound' => $t->upper_bound !== null ? (float) $t->upper_bound : null,
        'amount' => (float) $t->amount,
        'type' => $t->type,
    ])->values();
@endphp

<template id="tp-tier-template">
    <div class="tp-tier-row border border-gray-200 rounded-lg p-4 mb-4 bg-gray-50">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-sm font-medium text-gray-900">Tier <span class="tier-num"></span></h3>
            <button type="button" class="remove-tier text-red-600 hover:text-red-800 text-sm font-semibold">Remove</button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Lower bound (UGX) *</label>
                <input type="number" step="0.01" min="0" required
                       class="tier-lower w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="0">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Upper bound (UGX)</label>
                <input type="number" step="0.01" min="0"
                       class="tier-upper w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="Optional">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Charge *</label>
                <input type="number" step="0.01" min="0" required
                       class="tier-amount w-full border-gray-300 rounded-md shadow-sm text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Type *</label>
                <select class="tier-type w-full border-gray-300 rounded-md shadow-sm text-sm" required>
                    <option value="percentage">Percentage</option>
                    <option value="fixed">Fixed (UGX)</option>
                </select>
            </div>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('tp-vendor-tiers-container');
    const template = document.getElementById('tp-tier-template');
    const addBtn = document.getElementById('add-tier-row');
    const defaultsBtn = document.getElementById('load-default-tiers');
    const initialTiers = @json($tiersPayload);
    const defaultTiers = @json($defaultTiers ?? []);
    let itemIndex = 0;

    function addTierRow(data) {
        const clone = template.content.cloneNode(true);
        const row = clone.querySelector('.tp-tier-row');
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
        row.querySelector('.remove-tier').addEventListener('click', function() {
            row.remove();
            updateTierNumbers();
        });
        container.appendChild(row);
        updateTierNumbers();
    }

    function updateTierNumbers() {
        container.querySelectorAll('.tp-tier-row').forEach(function(row, i) {
            row.querySelector('.tier-num').textContent = i + 1;
        });
    }

    addBtn.addEventListener('click', function() { addTierRow(null); });

    if (defaultsBtn) {
        defaultsBtn.addEventListener('click', function() {
            container.innerHTML = '';
            itemIndex = 0;
            defaultTiers.forEach(function(t) { addTierRow(t); });
        });
    }

    if (initialTiers.length) {
        initialTiers.forEach(function(t) { addTierRow(t); });
    } else if (defaultTiers.length) {
        defaultTiers.forEach(function(t) { addTierRow(t); });
    } else {
        addTierRow(null);
    }
});
</script>
</x-app-layout>
