<?php

namespace App\Support;

use App\Models\SupplierIndustry;
use App\Models\SupplierSubCategory;
use App\Models\Supplier;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SupplierCategorySelection
{
    /**
     * @return array<int, string>
     */
    public static function industryOptions(): array
    {
        return SupplierIndustry::query()
            ->with('business:id,name')
            ->where('business_id', '!=', 1)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (SupplierIndustry $industry): array => [
                $industry->id => $industry->name . ' (' . ($industry->business?->name ?? 'Entity') . ')',
            ])
            ->all();
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function subCategoryOptionsByIndustry(): array
    {
        return SupplierSubCategory::query()
            ->with('business:id,name')
            ->where('business_id', '!=', 1)
            ->orderBy('name')
            ->get()
            ->groupBy('supplier_industry_id')
            ->map(fn ($group) => $group->mapWithKeys(fn (SupplierSubCategory $subCategory): array => [
                $subCategory->id => $subCategory->name . ' (' . ($subCategory->business?->name ?? 'Entity') . ')',
            ])->all())
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function industryOptionsForBusiness(int $businessId): array
    {
        return SupplierIndustry::query()
            ->where('business_id', $businessId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function subCategoryOptionsByIndustryForBusiness(int $businessId): array
    {
        return SupplierSubCategory::query()
            ->where('business_id', $businessId)
            ->orderBy('name')
            ->get()
            ->groupBy('supplier_industry_id')
            ->map(fn (Collection $group): array => $group->pluck('name', 'id')->all())
            ->all();
    }

    /**
     * @param  Collection<int, Supplier>  $suppliers
     * @return list<array{id: int, name: string, industry_id: ?int, sub_category_id: ?int, industry_name: ?string, sub_category_name: ?string}>
     */
    public static function catalogFromSuppliers(Collection $suppliers): array
    {
        return $suppliers->map(function (Supplier $supplier): array {
            return [
                'id' => (int) $supplier->id,
                'name' => $supplier->name,
                'industry_id' => $supplier->supplier_industry_id ? (int) $supplier->supplier_industry_id : null,
                'sub_category_id' => $supplier->supplier_sub_category_id ? (int) $supplier->supplier_sub_category_id : null,
                'industry_name' => $supplier->industry?->name,
                'sub_category_name' => $supplier->subCategory?->name,
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(bool $registered, array $data): array
    {
        if (! $registered) {
            $data['supplier_industry_id'] = null;
            $data['supplier_sub_category_id'] = null;

            return $data;
        }

        $industryId = isset($data['supplier_industry_id']) ? (int) $data['supplier_industry_id'] : null;
        $subCategoryId = isset($data['supplier_sub_category_id']) ? (int) $data['supplier_sub_category_id'] : null;

        if (! $industryId || ! $subCategoryId) {
            throw ValidationException::withMessages([
                'supplier_industry_id' => 'Industry is required when registering as a supplier.',
                'supplier_sub_category_id' => 'Sub category is required when registering as a supplier.',
            ]);
        }

        $industry = SupplierIndustry::query()->find($industryId);
        $subCategory = SupplierSubCategory::query()->find($subCategoryId);

        if (! $industry || ! $subCategory || (int) $subCategory->supplier_industry_id !== $industryId) {
            throw ValidationException::withMessages([
                'supplier_sub_category_id' => 'The selected sub category does not belong to the chosen industry.',
            ]);
        }

        $data['supplier_industry_id'] = $industryId;
        $data['supplier_sub_category_id'] = $subCategoryId;

        return $data;
    }
}
