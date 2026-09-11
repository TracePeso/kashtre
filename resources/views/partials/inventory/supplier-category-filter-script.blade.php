<script>
function supplierCategoryFilterMixin(catalog, industries, subCategoriesByIndustry) {
    return {
        industryFilter: '',
        subCategoryFilter: '',
        supplierCatalog: catalog || [],
        supplierIndustries: industries || {},
        supplierSubCategoriesByIndustry: subCategoriesByIndustry || {},
        subCategoryFilterOptions() {
            if (! this.industryFilter) {
                return {};
            }

            return this.supplierSubCategoriesByIndustry[this.industryFilter] || {};
        },
        filteredSupplierCatalog() {
            return this.supplierCatalog.filter((supplier) => this.supplierMatchesCategoryFilter(supplier.industry_id, supplier.sub_category_id));
        },
        supplierMatchesCategoryFilter(industryId, subCategoryId) {
            if (this.industryFilter && String(industryId ?? '') !== String(this.industryFilter)) {
                return false;
            }

            if (this.subCategoryFilter && String(subCategoryId ?? '') !== String(this.subCategoryFilter)) {
                return false;
            }

            return true;
        },
        onIndustryFilterChange() {
            this.subCategoryFilter = '';

            if (typeof this.onSupplierCategoryFilterChange === 'function') {
                this.onSupplierCategoryFilterChange();
            }
        },
        onSubCategoryFilterChange() {
            if (typeof this.onSupplierCategoryFilterChange === 'function') {
                this.onSupplierCategoryFilterChange();
            }
        },
    };
}
</script>
