<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Business;
use App\Models\Group;
use App\Models\SubGroup;
use App\Models\Department;
use App\Models\ItemUnit;
use App\Models\ServicePoint;
use App\Models\ContractorProfile;
use App\Models\Item;
use App\Models\ItemImportanceCategory;
use App\Models\Branch;
use App\Models\BranchItemPrice;
use App\Models\PackageItem;
use App\Models\BulkItem;
use App\Models\BranchServicePoint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('items.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Log::info("=== ITEM CREATE CONTROLLER STARTED ===");
        Log::info("User: " . (Auth::user()->name ?? 'Unknown') . " (ID: " . Auth::user()->id . ")");
        Log::info("User business_id: " . Auth::user()->business_id);
        
        // Business logic: Only business_id == 1 can select business, others default to their business
        $canSelectBusiness = Auth::user()->business_id == 1;
        Log::info("Can select business: " . ($canSelectBusiness ? 'true' : 'false'));
        
        if ($canSelectBusiness) {
            $businesses = Business::where('id', '!=', 1)->get();
            // For admin users, default to the first business to show data
            $selectedBusinessId = $businesses->first() ? $businesses->first()->id : null;
            Log::info("Admin user - selected business ID: " . $selectedBusinessId);
        } else {
            $businesses = Business::where('id', Auth::user()->business_id)->get();
            $selectedBusinessId = Auth::user()->business_id;
            Log::info("Regular user - selected business ID: " . $selectedBusinessId);
        }

        // Get data filtered by selected business (or all data for admin if no business selected)
        if ($selectedBusinessId) {
            Log::info("Fetching data for business ID: " . $selectedBusinessId);
            $groups = Group::where('business_id', $selectedBusinessId)->get();
            $subGroups = SubGroup::where('business_id', $selectedBusinessId)->get();
            $departments = Department::where('business_id', $selectedBusinessId)->get();
            $itemUnits = ItemUnit::where('business_id', $selectedBusinessId)->get();
            $servicePoints = ServicePoint::where('business_id', $selectedBusinessId)->get();
            $contractors = ContractorProfile::with(['business', 'user'])->where('business_id', $selectedBusinessId)->get();
            $branches = Branch::where('business_id', $selectedBusinessId)->get();
            
            // Get available items for package and bulk selection (exclude package and bulk types)
            $availableItems = Item::where('business_id', $selectedBusinessId)
                ->whereNotIn('type', ['package', 'bulk'])
                ->get();
            
            Log::info("Available items count: " . $availableItems->count());
            Log::info("Available items: " . $availableItems->pluck('name', 'id')->toJson());
        } else {
            Log::info("No business selected - fetching all data");
            // For admin users with no business selected, show all data
            $groups = Group::where('business_id', '!=', 1)->get();
            $subGroups = SubGroup::where('business_id', '!=', 1)->get();
            $departments = Department::where('business_id', '!=', 1)->get();
            $itemUnits = ItemUnit::where('business_id', '!=', 1)->get();
            $servicePoints = ServicePoint::where('business_id', '!=', 1)->get();
            $contractors = ContractorProfile::with(['business', 'user'])->where('business_id', '!=', 1)->get();
            $branches = Branch::where('business_id', '!=', 1)->get();
            $availableItems = Item::where('business_id', '!=', 1)
                ->whereNotIn('type', ['package', 'bulk'])
                ->get();
            
            Log::info("Available items count (all): " . $availableItems->count());
            Log::info("Available items (all): " . $availableItems->pluck('name', 'id')->toJson());
        }

        $importanceOptions = Item::importanceOptions($selectedBusinessId);

        return view('items.create', compact(
            'businesses',
            'groups',
            'subGroups',
            'departments',
            'itemUnits',
            'servicePoints',
            'contractors',
            'branches',
            'availableItems',
            'canSelectBusiness',
            'selectedBusinessId',
            'importanceOptions',
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'generic_name' => 'nullable|string|max:255',
            'strength' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'code' => 'nullable|string|unique:items,code',
            'type' => 'required|in:service,good,package,bulk',
            'description' => 'nullable|string',
            'group_id' => 'required_unless:type,package,bulk|nullable|exists:groups,id',
            'subgroup_id' => 'required_if:type,service,good|nullable|exists:sub_groups,id',
            'department_id' => 'required_if:type,service,good|nullable|exists:departments,id',
            'uom_id' => 'required_unless:type,package,bulk|nullable|exists:item_units,id',
            'importance_category' => 'nullable|string|max:64',
            'order_unit_id' => 'nullable|exists:item_units,id',
            'suom_per_ouom' => 'nullable|numeric|min:0.0001',
            'default_price' => 'required|numeric|min:0',
            'purchase_price' => 'required|numeric|min:0',
            'vat_rate' => 'nullable|numeric|min:0|max:100',
            'hospital_share' => 'required_if:type,service,good|integer|between:0,100',
            'contractor_account_id' => 'nullable|exists:contractor_profiles,id',
            'business_id' => 'required|exists:businesses,id',
            'other_names' => 'required|string',
            'validity_days' => 'nullable|integer|min:1',
            'max_qty' => 'required_if:type,package|integer|min:1',
            'pricing_type' => 'required|in:default,custom',
            'branch_prices' => 'nullable|array',
            'branch_prices.*.branch_id' => 'nullable|exists:branches,id',
            'branch_prices.*.price' => 'nullable|numeric|min:0',
            'branch_prices.*.purchase_price' => 'nullable|numeric|min:0',
            'branch_service_points' => 'nullable|array',
            'branch_service_points.*' => 'nullable|exists:service_points,id',
            'package_items' => 'nullable|array',
            'package_items.*.included_item_id' => 'nullable|exists:items,id',
            'package_items.*.max_quantity' => 'nullable|integer|min:1',
            'package_items.*.validity_days' => 'nullable|integer|min:1',
            'bulk_items' => 'nullable|array',
            'bulk_items.*.included_item_id' => 'nullable|exists:items,id',
            'bulk_items.*.fixed_quantity' => 'nullable|integer|min:1',
        ]);

        // Set business_id based on user permissions
        if (Auth::user()->business_id != 1) {
            $validated['business_id'] = Auth::user()->business_id;
        }

        $this->validateImportanceCategory($request, (int) $validated['business_id'], $validated['type']);

        // Set hospital_share to 100 for package and bulk types
        if (in_array($validated['type'], ['package', 'bulk'])) {
            $validated['hospital_share'] = 100;
            $validated['contractor_account_id'] = null;
            // Set service/good specific fields to null for packages and bulk items
            $validated['group_id'] = null;
            $validated['subgroup_id'] = null;
            $validated['department_id'] = null;
            $validated['uom_id'] = null;
            $validated['importance_category'] = null;
            $validated['order_unit_id'] = null;
            $validated['suom_per_ouom'] = null;
            // Set max_qty default to 1 for package types if not provided
            if ($validated['type'] === 'package' && (!isset($validated['max_qty']) || empty($validated['max_qty']))) {
                $validated['max_qty'] = 1;
            }
        }

        // Inventory unit fields apply to goods only
        if ($validated['type'] !== 'good') {
            $validated['importance_category'] = null;
            $validated['order_unit_id'] = null;
            $validated['suom_per_ouom'] = null;
        }

        $this->applyImportanceCategoryToItem($validated);

        // Validate contractor selection when hospital share is not 100% for goods and services
        if (in_array($validated['type'], ['service', 'good']) && $validated['hospital_share'] != 100 && empty($validated['contractor_account_id'])) {
            return back()->withErrors(['contractor_account_id' => 'Contractor is required when hospital share is not 100%']);
        }

        // Validate that at least one branch has a custom price when custom pricing is selected
        if ($validated['pricing_type'] === 'custom' && isset($validated['branch_prices'])) {
            $hasCustomPrices = false;
            foreach ($validated['branch_prices'] as $branchPrice) {
                $hasSalePrice = !empty($branchPrice['branch_id']) && isset($branchPrice['price']) && $branchPrice['price'] !== '';
                $hasPurchasePrice = !empty($branchPrice['branch_id']) && isset($branchPrice['purchase_price']) && $branchPrice['purchase_price'] !== '';
                if ($hasSalePrice || $hasPurchasePrice) {
                    $hasCustomPrices = true;
                    break;
                }
            }

            if (!$hasCustomPrices) {
                return back()->withErrors(['branch_prices' => 'At least one branch must have a custom sale or purchase price when custom pricing is selected']);
            }
        }

        // Create the item
        $item = Item::create($validated);

        // Handle branch item prices only if custom pricing is selected
        if ($validated['pricing_type'] === 'custom' && isset($validated['branch_prices'])) {
            foreach ($validated['branch_prices'] as $branchPrice) {
                if (empty($branchPrice['branch_id'])) {
                    continue;
                }

                $salePrice = isset($branchPrice['price']) && $branchPrice['price'] !== ''
                    ? $branchPrice['price']
                    : null;
                $purchasePrice = isset($branchPrice['purchase_price']) && $branchPrice['purchase_price'] !== ''
                    ? $branchPrice['purchase_price']
                    : null;

                if ($salePrice === null && $purchasePrice === null) {
                    continue;
                }

                BranchItemPrice::create([
                    'business_id' => $validated['business_id'],
                    'branch_id' => $branchPrice['branch_id'],
                    'item_id' => $item->id,
                    'price' => $salePrice ?? $validated['default_price'],
                    'purchase_price' => $purchasePrice ?? $validated['purchase_price'],
                ]);
            }
        }

        // Handle branch service points
        if (isset($validated['branch_service_points'])) {
            foreach ($validated['branch_service_points'] as $branchId => $servicePointId) {
                if (!empty($servicePointId)) {
                    BranchServicePoint::create([
                        'business_id' => $validated['business_id'],
                        'branch_id' => $branchId,
                        'service_point_id' => $servicePointId,
                        'item_id' => $item->id,
                    ]);
                }
            }
        }

        // Handle package items
        if ($validated['type'] === 'package' && isset($validated['package_items'])) {
            foreach ($validated['package_items'] as $packageItem) {
                if (!empty($packageItem['included_item_id'])) {
                    PackageItem::create([
                        'package_item_id' => $item->id,
                        'included_item_id' => $packageItem['included_item_id'],
                        'max_quantity' => $packageItem['max_quantity'] ?? 1,
                        'business_id' => $validated['business_id'],
                    ]);
                }
            }
        }

        // Handle bulk items
        if ($validated['type'] === 'bulk' && isset($validated['bulk_items'])) {
            foreach ($validated['bulk_items'] as $bulkItem) {
                if (!empty($bulkItem['included_item_id'])) {
                    BulkItem::create([
                        'bulk_item_id' => $item->id,
                        'included_item_id' => $bulkItem['included_item_id'],
                        'fixed_quantity' => $bulkItem['fixed_quantity'] ?? 1,
                        'business_id' => $validated['business_id'],
                    ]);
                }
            }
        }

        return redirect()->route('items.index')->with('success', 'Item created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Item $item)
    {
        // Load all the relationships needed for the show view
        $item->load([
            'business',
            'group',
            'subgroup',
            'department',
            'itemUnit',
            'contractor.business',
            'branchServicePoints.branch',
            'branchServicePoints.servicePoint',
            'branchPrices.branch',
            'packageItems.includedItem',
            'bulkItems.includedItem',
            'includedInPackages.packageItem',
            'includedInBulks.bulkItem'
        ]);

        return view('items.show', compact('item'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Item $item)
    {
        // Business logic: Only business_id == 1 can select business, others default to their business
        $canSelectBusiness = Auth::user()->business_id == 1;
        
        if ($canSelectBusiness) {
            $businesses = Business::where('id', '!=', 1)->get();
        } else {
            $businesses = Business::where('id', Auth::user()->business_id)->get();
        }

        // Get data filtered by the item's business (not user's business)
        $selectedBusinessId = $item->business_id;
        $groups = Group::where('business_id', $selectedBusinessId)->get();
        $departments = Department::where('business_id', $selectedBusinessId)->get();
        $itemUnits = ItemUnit::where('business_id', $selectedBusinessId)->get();
        $servicePoints = ServicePoint::where('business_id', $selectedBusinessId)->get();
        $contractors = ContractorProfile::with('business')->where('business_id', $selectedBusinessId)->get();
        $branches = Branch::where('business_id', $selectedBusinessId)->get();
        
        // Get existing branch prices for this item
        $branchPrices = $item->branchPrices;
        
        // Get existing branch service points for this item
        $branchServicePoints = $item->branchServicePoints;
        
        // Get existing package and bulk items
        $packageItems = $item->packageItems;
        $bulkItems = $item->bulkItems;
        
        // Get available items for package and bulk selection (exclude package and bulk types, and current item)
        $availableItems = Item::where('business_id', $selectedBusinessId)
            ->whereNotIn('type', ['package', 'bulk'])
            ->where('id', '!=', $item->id)
            ->get();

        $importanceOptions = Item::importanceOptions($selectedBusinessId);

        return view('items.edit', compact(
            'item',
            'businesses',
            'groups',
            'departments',
            'itemUnits',
            'servicePoints',
            'contractors',
            'branches',
            'branchPrices',
            'branchServicePoints',
            'packageItems',
            'bulkItems',
            'availableItems',
            'canSelectBusiness',
            'selectedBusinessId',
            'importanceOptions',
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Item $item)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'generic_name' => 'nullable|string|max:255',
            'strength' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'code' => 'nullable|string|unique:items,code,' . $item->id,
            'type' => 'required|in:service,good,package,bulk',
            'description' => 'nullable|string',
            'group_id' => 'required_unless:type,package,bulk|nullable|exists:groups,id',
            'subgroup_id' => 'required_if:type,service,good|nullable|exists:sub_groups,id',
            'department_id' => 'required_if:type,service,good|nullable|exists:departments,id',
            'uom_id' => 'required_unless:type,package,bulk|nullable|exists:item_units,id',
            'importance_category' => 'nullable|string|max:64',
            'order_unit_id' => 'nullable|exists:item_units,id',
            'suom_per_ouom' => 'nullable|numeric|min:0.0001',
            'default_price' => 'required|numeric|min:0',
            'purchase_price' => 'required|numeric|min:0',
            'vat_rate' => 'nullable|numeric|min:0|max:100',
            'hospital_share' => 'required_if:type,service,good|integer|between:0,100',
            'contractor_account_id' => 'nullable|exists:contractor_profiles,id',
            'business_id' => 'required|exists:businesses,id',
            'other_names' => 'nullable|string',
            'validity_days' => 'nullable|integer|min:1',
            'max_qty' => 'required_if:type,package|integer|min:1',
            'pricing_type' => 'required|in:default,custom',
            'branch_prices' => 'nullable|array',
            'branch_prices.*.branch_id' => 'nullable|exists:branches,id',
            'branch_prices.*.price' => 'nullable|numeric|min:0',
            'branch_prices.*.purchase_price' => 'nullable|numeric|min:0',
            'branch_service_points' => 'nullable|array',
            'branch_service_points.*' => 'nullable|exists:service_points,id',
            'package_items' => 'nullable|array',
            'package_items.*.included_item_id' => 'nullable|exists:items,id',
            'package_items.*.max_quantity' => 'nullable|integer|min:1',
            'package_items.*.validity_days' => 'nullable|integer|min:1',
            'bulk_items' => 'nullable|array',
            'bulk_items.*.included_item_id' => 'nullable|exists:items,id',
            'bulk_items.*.fixed_quantity' => 'nullable|integer|min:1',
        ]);

        // Set business_id based on user permissions
        if (Auth::user()->business_id != 1) {
            $validated['business_id'] = Auth::user()->business_id;
        }

        $this->validateImportanceCategory($request, (int) $validated['business_id'], $validated['type']);

        // Set hospital_share to 100 for package and bulk types
        if (in_array($validated['type'], ['package', 'bulk'])) {
            $validated['hospital_share'] = 100;
            $validated['contractor_account_id'] = null;
            // Set service/good specific fields to null for packages and bulk items
            $validated['group_id'] = null;
            $validated['subgroup_id'] = null;
            $validated['department_id'] = null;
            $validated['uom_id'] = null;
            $validated['importance_category'] = null;
            $validated['order_unit_id'] = null;
            $validated['suom_per_ouom'] = null;
            // Set max_qty default to 1 for package types if not provided
            if ($validated['type'] === 'package' && (!isset($validated['max_qty']) || empty($validated['max_qty']))) {
                $validated['max_qty'] = 1;
            }
        }

        // Inventory unit fields apply to goods only
        if ($validated['type'] !== 'good') {
            $validated['importance_category'] = null;
            $validated['order_unit_id'] = null;
            $validated['suom_per_ouom'] = null;
        }

        $this->applyImportanceCategoryToItem($validated);

        // Validate contractor selection when hospital share is not 100% for goods and services
        if (in_array($validated['type'], ['service', 'good']) && $validated['hospital_share'] != 100 && empty($validated['contractor_account_id'])) {
            return back()->withErrors(['contractor_account_id' => 'Contractor is required when hospital share is not 100%']);
        }

        // Validate that at least one branch has a custom price when custom pricing is selected
        if ($validated['pricing_type'] === 'custom' && isset($validated['branch_prices'])) {
            $hasCustomPrices = false;
            foreach ($validated['branch_prices'] as $branchPrice) {
                $hasSalePrice = !empty($branchPrice['branch_id']) && isset($branchPrice['price']) && $branchPrice['price'] !== '';
                $hasPurchasePrice = !empty($branchPrice['branch_id']) && isset($branchPrice['purchase_price']) && $branchPrice['purchase_price'] !== '';
                if ($hasSalePrice || $hasPurchasePrice) {
                    $hasCustomPrices = true;
                    break;
                }
            }

            if (!$hasCustomPrices) {
                return back()->withErrors(['branch_prices' => 'At least one branch must have a custom sale or purchase price when custom pricing is selected']);
            }
        }

        // Update the item
        $item->update($validated);

        // Handle branch item prices - delete existing and create new ones only if custom pricing is selected
        $item->branchPrices()->delete();

        if ($validated['pricing_type'] === 'custom' && isset($validated['branch_prices'])) {
            foreach ($validated['branch_prices'] as $branchPrice) {
                if (empty($branchPrice['branch_id'])) {
                    continue;
                }

                $salePrice = isset($branchPrice['price']) && $branchPrice['price'] !== ''
                    ? $branchPrice['price']
                    : null;
                $purchasePrice = isset($branchPrice['purchase_price']) && $branchPrice['purchase_price'] !== ''
                    ? $branchPrice['purchase_price']
                    : null;

                if ($salePrice === null && $purchasePrice === null) {
                    continue;
                }

                BranchItemPrice::create([
                    'business_id' => $validated['business_id'],
                    'branch_id' => $branchPrice['branch_id'],
                    'item_id' => $item->id,
                    'price' => $salePrice ?? $validated['default_price'],
                    'purchase_price' => $purchasePrice ?? $validated['purchase_price'],
                ]);
            }
        }

        // Handle branch service points - delete existing and create new ones
        $item->branchServicePoints()->delete();

        if (isset($validated['branch_service_points'])) {
            foreach ($validated['branch_service_points'] as $branchId => $servicePointId) {
                if (!empty($servicePointId)) {
                    BranchServicePoint::create([
                        'business_id' => $validated['business_id'],
                        'branch_id' => $branchId,
                        'service_point_id' => $servicePointId,
                        'item_id' => $item->id,
                    ]);
                }
            }
        }

        // Handle package items - delete existing and create new ones
        $item->packageItems()->delete();
        
        if ($validated['type'] === 'package' && isset($validated['package_items'])) {
            foreach ($validated['package_items'] as $packageItem) {
                if (!empty($packageItem['included_item_id'])) {
                    PackageItem::create([
                        'package_item_id' => $item->id,
                        'included_item_id' => $packageItem['included_item_id'],
                        'max_quantity' => $packageItem['max_quantity'] ?? 1,
                        'business_id' => $validated['business_id'],
                    ]);
                }
            }
        }

        // Handle bulk items - delete existing and create new ones
        $item->bulkItems()->delete();
        
        if ($validated['type'] === 'bulk' && isset($validated['bulk_items'])) {
            foreach ($validated['bulk_items'] as $bulkItem) {
                if (!empty($bulkItem['included_item_id'])) {
                    BulkItem::create([
                        'bulk_item_id' => $item->id,
                        'included_item_id' => $bulkItem['included_item_id'],
                        'fixed_quantity' => $bulkItem['fixed_quantity'] ?? 1,
                        'business_id' => $validated['business_id'],
                    ]);
                }
            }
        }

        return redirect()->route('items.index')->with('success', 'Item updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Get filtered data based on selected business (AJAX endpoint)
     */
    public function getFilteredData(Request $request)
    {
        Log::info("=== GET FILTERED DATA AJAX STARTED ===");
        Log::info("User: " . (Auth::user()->name ?? 'Unknown') . " (ID: " . Auth::user()->id . ")");
        Log::info("User business_id: " . Auth::user()->business_id);
        
        $businessId = $request->input('business_id');
        Log::info("Requested business_id: " . $businessId);
        
        if (!$businessId) {
            Log::warning("No business_id provided in request");
            return response()->json([
                'groups' => [],
                'subGroups' => [],
                'departments' => [],
                'itemUnits' => [],
                'servicePoints' => [],
                'contractors' => [],
                'branches' => [],
                'availableItems' => [],
                'importanceCategories' => [],
            ]);
        }

        $importanceCategories = ItemImportanceCategory::optionsForBusiness((int) $businessId);

        // Validate that the user has permission to access this business
        if (Auth::user()->business_id != 1 && Auth::user()->business_id != $businessId) {
            Log::error("Unauthorized access attempt - User business_id: " . Auth::user()->business_id . ", Requested business_id: " . $businessId);
            return response()->json(['error' => 'Unauthorized access to business data'], 403);
        }

        Log::info("Fetching filtered data for business ID: " . $businessId);

        // Get groups
        $groups = Group::where('business_id', $businessId)->get();
        Log::info("Groups count: " . $groups->count());

        // Get subgroups
        $subGroups = SubGroup::where('business_id', $businessId)->get();
        Log::info("SubGroups count: " . $subGroups->count());

        // Get departments
        $departments = Department::where('business_id', $businessId)->get();
        Log::info("Departments count: " . $departments->count());

        // Get item units
        $itemUnits = ItemUnit::where('business_id', $businessId)->get();
        Log::info("Item units count: " . $itemUnits->count());

        // Get service points grouped by branches
        $servicePoints = ServicePoint::where('business_id', $businessId)
            ->with('branch')
            ->get()
            ->groupBy('branch_id');
        Log::info("Service points count: " . $servicePoints->count());

        // Get contractors
        $contractors = ContractorProfile::with(['business', 'user'])->where('business_id', $businessId)->get();
        Log::info("Contractors count: " . $contractors->count());

        // Get branches
        $branches = Branch::where('business_id', $businessId)->get();
        Log::info("Branches count: " . $branches->count());

        // Get available items for package and bulk selection (exclude package and bulk types)
        $availableItems = Item::where('business_id', $businessId)
            ->whereNotIn('type', ['package', 'bulk'])
            ->get();
        Log::info("Available items count: " . $availableItems->count());
        Log::info("Available items: " . $availableItems->pluck('name', 'id')->toJson());

        return response()->json([
            'groups' => $groups,
            'subGroups' => $subGroups,
            'departments' => $departments,
            'itemUnits' => $itemUnits,
            'servicePoints' => $servicePoints,
            'contractors' => $contractors,
            'branches' => $branches,
            'availableItems' => $availableItems,
            'importanceCategories' => collect($importanceCategories)
                ->map(fn (string $name, string $slug) => ['slug' => $slug, 'name' => $name])
                ->values(),
        ]);
    }

    private function validateImportanceCategory(Request $request, int $businessId, string $type): void
    {
        if ($type !== 'good') {
            return;
        }

        Validator::make(
            $request->only('importance_category'),
            [
                'importance_category' => [
                    'required',
                    Rule::exists('item_importance_categories', 'slug')->where('business_id', $businessId),
                ],
            ],
            [
                'importance_category.required' => 'Choose an importance category for this good.',
                'importance_category.exists' => 'The selected importance category is invalid for this organisation.',
            ]
        )->validate();
    }

    private function applyImportanceCategoryToItem(array &$validated): void
    {
        if (($validated['type'] ?? null) === 'good' && ! empty($validated['importance_category'])) {
            $validated['category'] = ItemImportanceCategory::labelForSlug(
                (int) $validated['business_id'],
                $validated['importance_category']
            );

            return;
        }

        $validated['category'] = null;
    }

    /**
     * Generate a unique item code for the given business (AJAX endpoint)
     */
    public function generateCode(Request $request)
    {
        $businessId = $request->input('business_id');
        
        if (!$businessId) {
            return response()->json(['error' => 'Business ID is required'], 400);
        }

        // Validate that the user has permission to access this business
        if (Auth::user()->business_id != 1 && Auth::user()->business_id != $businessId) {
            return response()->json(['error' => 'Unauthorized access to business data'], 403);
        }

        $code = Item::generateUniqueCode($businessId);
        
        return response()->json(['code' => $code]);
    }
}
