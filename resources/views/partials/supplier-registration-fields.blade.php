@php
    $industryOptions = $industryOptions ?? \App\Support\SupplierCategorySelection::industryOptions();
    $subCategoryOptionsByIndustry = $subCategoryOptionsByIndustry ?? \App\Support\SupplierCategorySelection::subCategoryOptionsByIndustry();
    $idPrefix = $idPrefix ?? '';
    $registerFieldName = $registerFieldName ?? 'register_as_supplier';
    $industryFieldName = $industryFieldName ?? 'supplier_industry_id';
    $subCategoryFieldName = $subCategoryFieldName ?? 'supplier_sub_category_id';
    $registerChecked = (bool) old($registerFieldName, $registerChecked ?? false);
    $selectedIndustryId = (string) old($industryFieldName, $selectedIndustryId ?? '');
    $selectedSubCategoryId = (string) old($subCategoryFieldName, $selectedSubCategoryId ?? '');
@endphp

<div
    x-data="{
        registerAsSupplier: {{ $registerChecked ? 'true' : 'false' }},
        industryId: @js($selectedIndustryId),
        subCategoryId: @js($selectedSubCategoryId),
        subCategoriesByIndustry: @js($subCategoryOptionsByIndustry),
        subCategoryOptions() {
            if (! this.industryId) {
                return {};
            }

            return this.subCategoriesByIndustry[this.industryId] ?? {};
        },
        onIndustryChange() {
            const options = this.subCategoryOptions();
            if (! options[this.subCategoryId]) {
                this.subCategoryId = '';
            }
        }
    }"
    class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 bg-gray-50 dark:bg-gray-900/40"
>
    <label class="inline-flex items-start gap-3 cursor-pointer">
        <input type="checkbox"
               name="{{ $registerFieldName }}"
               value="1"
               x-model="registerAsSupplier"
               class="mt-1 rounded border-gray-300 text-[#011478] focus:ring-[#011478]">
        <span>
            <span class="block text-sm font-medium text-gray-800 dark:text-gray-200">Register as a supplier</span>
            <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                Mark this Kashtre entity as available to other organisations in the network. They can add it as a supplier for procurement (RFQs, LPOs, goods receive notes).
            </span>
        </span>
    </label>

    <div x-show="registerAsSupplier" x-cloak class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-gray-200 dark:border-gray-600 pt-4">
        <div>
            <label for="{{ $idPrefix }}supplier-industry" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Industry <span class="text-red-500">*</span>
            </label>
            <select
                name="{{ $industryFieldName }}"
                id="{{ $idPrefix }}supplier-industry"
                x-model="industryId"
                @change="onIndustryChange()"
                :required="registerAsSupplier"
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            >
                <option value="">Select industry</option>
                @foreach($industryOptions as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
            @error($industryFieldName)
                <span class="text-red-500 text-sm">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label for="{{ $idPrefix }}supplier-sub-category" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Sub category <span class="text-red-500">*</span>
            </label>
            <select
                name="{{ $subCategoryFieldName }}"
                id="{{ $idPrefix }}supplier-sub-category"
                x-model="subCategoryId"
                :required="registerAsSupplier"
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            >
                <option value="">Select sub category</option>
                <template x-for="[id, label] in Object.entries(subCategoryOptions())" :key="id">
                    <option :value="id" x-text="label" :selected="id === subCategoryId"></option>
                </template>
            </select>
            @error($subCategoryFieldName)
                <span class="text-red-500 text-sm">{{ $message }}</span>
            @enderror
        </div>
    </div>
</div>
