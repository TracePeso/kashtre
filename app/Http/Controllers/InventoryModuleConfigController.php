<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\InventoryModuleApprover;
use App\Models\InventoryModuleConfig;
use App\Models\User;
use App\Support\InventoryBusinessContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryModuleConfigController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (auth()->user()->business_id !== 1) {
                abort(403, 'Access denied. This feature is only available to Kashtre administrators.');
            }

            if (! in_array('View Inventory Module', auth()->user()->permissions ?? [])) {
                abort(403, 'Access denied. You do not have permission to view the inventory module configuration.');
            }

            return $next($request);
        });
    }

    public function index()
    {
        $configs = InventoryModuleConfig::with(['business', 'createdBy', 'updatedBy', 'approvers.user'])
            ->orderBy('business_id')
            ->get();

        $businesses = Business::where('id', '!=', 1)->orderBy('name')->get();

        return view('settings.inventory-module.index', compact('configs', 'businesses'));
    }

    public function create()
    {
        if (! in_array('Add Inventory Module', auth()->user()->permissions ?? [])) {
            abort(403, 'Access denied. You do not have permission to add inventory module configurations.');
        }

        $existingBusinessIds = InventoryModuleConfig::pluck('business_id')->toArray();
        $businesses = Business::where('id', '!=', 1)
            ->whereNotIn('id', $existingBusinessIds)
            ->orderBy('name')
            ->get();

        $usersByBusiness = User::query()
            ->where('business_id', '!=', 1)
            ->whereIn('business_id', $businesses->pluck('id'))
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'business_id'])
            ->groupBy('business_id');

        return view('settings.inventory-module.create', compact('businesses', 'usersByBusiness'));
    }

    public function store(Request $request)
    {
        if (! in_array('Add Inventory Module', auth()->user()->permissions ?? [])) {
            abort(403, 'Access denied. You do not have permission to add inventory module configurations.');
        }

        $validated = $request->validate(array_merge([
            'business_id' => 'required|exists:businesses,id',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'approver_1' => 'required|exists:users,id',
            'approver_2' => 'nullable|exists:users,id|different:approver_1',
        ], $this->stockSettingsRules()));

        if (InventoryModuleConfig::where('business_id', $validated['business_id'])->exists()) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'An inventory module configuration already exists for this business.');
        }

        $this->assertApproversBelongToBusiness(
            (int) $validated['business_id'],
            [(int) $validated['approver_1'], isset($validated['approver_2']) ? (int) $validated['approver_2'] : null]
        );

        $config = DB::transaction(function () use ($request, $validated) {
            $config = InventoryModuleConfig::create([
                'business_id' => $validated['business_id'],
                'description' => $validated['description'] ?? null,
                'fixed_daily_average_suom' => $validated['fixed_daily_average_suom'],
                'safety_stock_days' => $validated['safety_stock_days'],
                'buffer_stock_days' => $validated['buffer_stock_days'],
                'notification_to_order_days' => $validated['notification_to_order_days'],
                'period_of_order_days' => $validated['period_of_order_days'],
                'is_active' => $request->boolean('is_active', true),
                'created_by' => Auth::id(),
            ]);

            $this->syncApprovers($config, $validated['approver_1'], $validated['approver_2'] ?? null);

            return $config;
        });

        return redirect()->route('inventory-module-configs.show', $config)
            ->with('success', 'Inventory module enabled for the selected business.');
    }

    public function edit(InventoryModuleConfig $inventoryModuleConfig)
    {
        if (! in_array('Edit Inventory Module', auth()->user()->permissions ?? [])) {
            abort(403, 'Access denied. You do not have permission to edit inventory module configurations.');
        }

        $inventoryModuleConfig->load(['business', 'approvers.user']);

        $businessUsers = User::query()
            ->where('business_id', $inventoryModuleConfig->business_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('settings.inventory-module.edit', [
            'config' => $inventoryModuleConfig,
            'businessUsers' => $businessUsers,
        ]);
    }

    public function show(InventoryModuleConfig $inventoryModuleConfig)
    {
        $inventoryModuleConfig->load([
            'business',
            'createdBy',
            'updatedBy',
            'approvers.user',
        ]);

        return view('settings.inventory-module.show', [
            'config' => $inventoryModuleConfig,
        ]);
    }

    public function enterInventory(InventoryModuleConfig $inventoryModuleConfig)
    {
        if (! $inventoryModuleConfig->is_active) {
            return redirect()->route('inventory-module-configs.show', $inventoryModuleConfig)
                ->with('error', 'Activate the inventory module before browsing this organisation.');
        }

        InventoryBusinessContext::setContext((int) $inventoryModuleConfig->business_id);

        return redirect()->route('inventory.receive')
            ->with('success', 'Browsing inventory for '.$inventoryModuleConfig->business->name.'.');
    }

    public function update(Request $request, InventoryModuleConfig $inventoryModuleConfig)
    {
        if (! in_array('Edit Inventory Module', auth()->user()->permissions ?? [])) {
            abort(403, 'Access denied. You do not have permission to edit inventory module configurations.');
        }

        $validated = $request->validate(array_merge([
            'description' => 'nullable|string|max:1000',
            'approver_1' => 'required|exists:users,id',
            'approver_2' => 'nullable|exists:users,id|different:approver_1',
        ], $this->stockSettingsRules()));

        $this->assertApproversBelongToBusiness(
            (int) $inventoryModuleConfig->business_id,
            [(int) $validated['approver_1'], isset($validated['approver_2']) ? (int) $validated['approver_2'] : null]
        );

        DB::transaction(function () use ($inventoryModuleConfig, $validated) {
            $inventoryModuleConfig->update([
                'description' => $validated['description'] ?? null,
                'fixed_daily_average_suom' => $validated['fixed_daily_average_suom'],
                'safety_stock_days' => $validated['safety_stock_days'],
                'buffer_stock_days' => $validated['buffer_stock_days'],
                'notification_to_order_days' => $validated['notification_to_order_days'],
                'period_of_order_days' => $validated['period_of_order_days'],
                'updated_by' => Auth::id(),
            ]);

            $this->syncApprovers($inventoryModuleConfig, $validated['approver_1'], $validated['approver_2'] ?? null);
        });

        return redirect()->route('inventory-module-configs.show', $inventoryModuleConfig)
            ->with('success', 'Inventory module configuration updated successfully.');
    }

    public function destroy(InventoryModuleConfig $inventoryModuleConfig)
    {
        if (! in_array('Delete Inventory Module', auth()->user()->permissions ?? [])) {
            abort(403, 'Access denied. You do not have permission to delete inventory module configurations.');
        }

        $inventoryModuleConfig->delete();

        return redirect()->route('inventory-module-configs.index')
            ->with('success', 'Inventory module configuration removed successfully.');
    }

    public function toggleStatus(InventoryModuleConfig $inventoryModuleConfig)
    {
        if (! in_array('Manage Inventory Module', auth()->user()->permissions ?? [])) {
            abort(403, 'Access denied. You do not have permission to manage inventory module configurations.');
        }

        $inventoryModuleConfig->update([
            'is_active' => ! $inventoryModuleConfig->is_active,
            'updated_by' => Auth::id(),
        ]);

        $status = $inventoryModuleConfig->is_active ? 'activated' : 'deactivated';

        return redirect()->route('inventory-module-configs.show', $inventoryModuleConfig)
            ->with('success', "Inventory module {$status} successfully.");
    }

    private function syncApprovers(InventoryModuleConfig $config, int $approver1Id, ?int $approver2Id): void
    {
        $config->approvers()->delete();

        InventoryModuleApprover::create([
            'inventory_module_config_id' => $config->id,
            'user_id' => $approver1Id,
            'approval_order' => 1,
        ]);

        if ($approver2Id) {
            InventoryModuleApprover::create([
                'inventory_module_config_id' => $config->id,
                'user_id' => $approver2Id,
                'approval_order' => 2,
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function stockSettingsRules(): array
    {
        return [
            'fixed_daily_average_suom' => 'required|numeric|min:0',
            'safety_stock_days' => 'required|numeric|min:0',
            'buffer_stock_days' => 'required|numeric|min:0',
            'notification_to_order_days' => 'required|numeric|min:0',
            'period_of_order_days' => 'required|numeric|min:0',
        ];
    }

    /**
     * @param  array<int|null>  $userIds
     */
    private function assertApproversBelongToBusiness(int $businessId, array $userIds): void
    {
        $ids = array_values(array_filter($userIds));

        if (count($ids) < 1) {
            throw ValidationException::withMessages([
                'approver_1' => 'At least one goods receive note approver is required.',
            ]);
        }

        $validCount = User::query()
            ->where('business_id', $businessId)
            ->whereIn('id', $ids)
            ->count();

        if ($validCount !== count($ids)) {
            throw ValidationException::withMessages([
                'approver_1' => 'Selected approvers must be active staff of the chosen business.',
            ]);
        }
    }
}
