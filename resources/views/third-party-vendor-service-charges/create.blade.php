<x-app-layout>
<div class="container mx-auto px-4 py-8 max-w-6xl">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Configure third-party vendor charges</h1>
            <p class="text-sm text-gray-600 mt-1">
                Each tier applies by invoice amount band. Saving replaces the tier schedule for this clinic for either <strong>all</strong> third-party vendors or <strong>one</strong> vendor you choose.
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

    <form action="{{ route('third-party-vendor-service-charges.store') }}" method="POST" class="bg-white shadow-md rounded-lg p-6">
        @csrf

        @if((int) Auth::user()->business_id === 1)
            <div class="mb-6">
                <label for="entity_id" class="block text-sm font-medium text-gray-700 mb-2">Business (clinic) *</label>
                <select name="entity_id" id="entity_id" required
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <option value="">Select business</option>
                    @foreach($businesses as $b)
                        <option value="{{ $b->id }}" @selected(old('entity_id') == $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="mb-6 rounded-lg border border-gray-200 bg-gray-50 p-4">
            <p class="text-sm font-medium text-gray-900 mb-3">Apply this schedule to</p>
            <div class="space-y-3">
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="radio" name="charge_scope" value="all" class="mt-1"
                           {{ old('charge_scope', 'all') === 'all' ? 'checked' : '' }}>
                    <span>
                        <span class="text-sm font-medium text-gray-800">All third-party vendors</span>
                        <span class="block text-xs text-gray-500">Same tiered charges for every insurer/vendor linked to this clinic (default).</span>
                    </span>
                </label>
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="radio" name="charge_scope" value="vendor" class="mt-1"
                           {{ old('charge_scope') === 'vendor' ? 'checked' : '' }}>
                    <span>
                        <span class="text-sm font-medium text-gray-800">One third-party vendor only</span>
                        <span class="block text-xs text-gray-500">Overrides the “all vendors” schedule for that vendor only; other vendors still use the clinic-wide schedule if you configured it.</span>
                    </span>
                </label>
            </div>

            <div id="tp-vendor-picker-wrap" class="mt-4 {{ old('charge_scope') === 'vendor' ? '' : 'hidden' }}">
                <label for="insurance_company_id" class="block text-sm font-medium text-gray-700 mb-2">Third-party vendor *</label>
                @if((int) Auth::user()->business_id === 1)
                    <select name="insurance_company_id" id="insurance_company_id"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">Select clinic first, then choose a vendor</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Vendors load after you select a business above.</p>
                @else
                    <select name="insurance_company_id" id="insurance_company_id"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">Select vendor</option>
                        @foreach($insuranceCompaniesForClinic as $ic)
                            <option value="{{ $ic->id }}" @selected(old('insurance_company_id') == $ic->id)>{{ $ic->name }} @if($ic->code)({{ $ic->code }})@endif</option>
                        @endforeach
                    </select>
                    @if($insuranceCompaniesForClinic->isEmpty())
                        <p class="text-sm text-amber-700 mt-2">No third-party vendors found for your clinic. Create them under Settings first.</p>
                    @endif
                @endif
            </div>
        </div>

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Tiered charges</h2>
                <p class="text-xs text-gray-500">Upper bound: leave empty on the last tier for no maximum.</p>
            </div>
            <div class="flex gap-2">
                @if(count($defaultTiers ?? []) > 0)
                    <button type="button" id="load-default-tiers"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold py-2 px-4 rounded-lg">
                        Load recommended defaults
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
                Save tiers
            </button>
        </div>
    </form>
</div>

<template id="tp-tier-template">
    <div class="tp-tier-row border border-gray-200 rounded-lg p-4 mb-4 bg-gray-50">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-sm font-medium text-gray-900">Tier <span class="tier-num"></span></h3>
            <button type="button" class="remove-tier text-red-600 hover:text-red-800 text-sm font-semibold">Remove</button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Lower bound (UGX) *</label>
                <input type="number" name="" step="0.01" min="0" required
                       class="tier-lower w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="0">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Upper bound (UGX)</label>
                <input type="number" name="" step="0.01" min="0"
                       class="tier-upper w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="Optional">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Charge *</label>
                <input type="number" name="" step="0.01" min="0" required
                       class="tier-amount w-full border-gray-300 rounded-md shadow-sm text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Type *</label>
                <select name="" class="tier-type w-full border-gray-300 rounded-md shadow-sm text-sm" required>
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

    function clearAndLoadDefaults() {
        container.innerHTML = '';
        itemIndex = 0;
        defaultTiers.forEach(function(t) { addTierRow(t); });
    }

    addBtn.addEventListener('click', function() { addTierRow(null); });
    if (defaultsBtn) {
        defaultsBtn.addEventListener('click', clearAndLoadDefaults);
    }

    if (defaultTiers.length) {
        clearAndLoadDefaults();
    } else {
        addTierRow(null);
    }

    const vendorWrap = document.getElementById('tp-vendor-picker-wrap');
    const vendorSelect = document.getElementById('insurance_company_id');
    const scopeRadios = document.querySelectorAll('input[name="charge_scope"]');
    const entitySelect = document.getElementById('entity_id');

    function syncScopeUi() {
        const vendorScope = document.querySelector('input[name="charge_scope"]:checked')?.value === 'vendor';
        if (vendorWrap) {
            vendorWrap.classList.toggle('hidden', !vendorScope);
        }
        if (vendorSelect) {
            vendorSelect.disabled = !vendorScope;
            vendorSelect.required = vendorScope;
            if (!vendorScope) {
                vendorSelect.value = '';
            }
        }
    }

    scopeRadios.forEach(function(r) {
        r.addEventListener('change', syncScopeUi);
    });

    syncScopeUi();

    if (entitySelect && vendorSelect) {
        entitySelect.addEventListener('change', function() {
            const bid = this.value;
            if (!bid) {
                vendorSelect.innerHTML = '<option value="">Select clinic first</option>';
                return;
            }
            vendorSelect.innerHTML = '<option value="">Loading…</option>';
            fetch('/third-party-vendor-service-charges/businesses/' + bid + '/insurance-companies', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    vendorSelect.innerHTML = '<option value="">Select vendor</option>';
                    data.forEach(function(ic) {
                        var opt = document.createElement('option');
                        opt.value = ic.id;
                        opt.textContent = ic.code ? (ic.name + ' (' + ic.code + ')') : ic.name;
                        vendorSelect.appendChild(opt);
                    });
                    var oldIc = @json(old('insurance_company_id'));
                    if (oldIc) {
                        vendorSelect.value = String(oldIc);
                    }
                })
                .catch(function() {
                    vendorSelect.innerHTML = '<option value="">Could not load vendors</option>';
                });
        });

        if (entitySelect.value) {
            entitySelect.dispatchEvent(new Event('change'));
        }
    }
});
</script>
</x-app-layout>
