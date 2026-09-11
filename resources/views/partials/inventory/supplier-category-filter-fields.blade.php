<div class="grid grid-cols-1 md:grid-cols-2 gap-3 {{ $class ?? '' }}">
    <div>
        <label class="block text-xs font-medium text-gray-600">Filter by industry</label>
        <select x-model="industryFilter" @change="onIndustryFilterChange()"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
            <option value="">All industries</option>
            @foreach(($supplierIndustries ?? []) as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-600">Filter by sub category</label>
        <select x-model="subCategoryFilter" @change="onSubCategoryFilterChange()"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
            <option value="">All sub categories</option>
            <template x-for="[id, label] in Object.entries(subCategoryFilterOptions())" :key="id">
                <option :value="id" x-text="label"></option>
            </template>
        </select>
    </div>
</div>
