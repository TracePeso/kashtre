<?php

namespace App\Http\Controllers;

use App\Models\InventoryModuleApprover;
use App\Models\InventoryModuleConfig;
use App\Models\Item;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = auth()->user();

            if ($user->business_id === 1) {
                abort(403, 'Use Inventory Module Configuration to manage which businesses have inventory enabled.');
            }

            $enabled = InventoryModuleConfig::query()
                ->where('business_id', $user->business_id)
                ->where('is_active', true)
                ->exists();

            if (! $enabled) {
                abort(403, 'The inventory module is not enabled for your organisation.');
            }

            return $next($request);
        });
    }

    public function index()
    {
        return redirect()->route('inventory.receive');
    }

    public function receive()
    {
        return view('inventory.receive');
    }

    public function monitor()
    {
        return view('inventory.monitor');
    }

    public function network()
    {
        return redirect()->route('inventory.monitor', ['view' => 'network']);
    }

    public function stockHistory(Item $item)
    {
        if ((int) $item->business_id !== (int) Auth::user()->business_id) {
            abort(403);
        }

        if ($item->type !== 'good') {
            abort(404);
        }

        $item->load('itemUnit');

        return view('inventory.monitor.history', compact('item'));
    }

    public function approvers()
    {
        $config = $this->businessConfig();
        $config->load(['approvers.user']);

        $businessUsers = User::query()
            ->where('business_id', Auth::user()->business_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $canManageApprovers = in_array('Edit Business Settings', Auth::user()->permissions ?? []);

        return view('inventory.approvers', compact('config', 'businessUsers', 'canManageApprovers'));
    }

    public function updateApprovers(Request $request)
    {
        if (! in_array('Edit Business Settings', Auth::user()->permissions ?? [])) {
            abort(403, 'You do not have permission to update GRN approvers.');
        }

        $config = $this->businessConfig();

        $validated = $request->validate([
            'approver_1' => 'required|exists:users,id',
            'approver_2' => 'nullable|exists:users,id|different:approver_1',
        ]);

        $businessId = (int) Auth::user()->business_id;
        $ids = array_filter([(int) $validated['approver_1'], isset($validated['approver_2']) ? (int) $validated['approver_2'] : null]);

        $validCount = User::query()
            ->where('business_id', $businessId)
            ->whereIn('id', $ids)
            ->count();

        if ($validCount !== count($ids)) {
            throw ValidationException::withMessages([
                'approver_1' => 'Selected approvers must be active staff of your organisation.',
            ]);
        }

        DB::transaction(function () use ($config, $validated) {
            $config->approvers()->delete();

            InventoryModuleApprover::create([
                'inventory_module_config_id' => $config->id,
                'user_id' => $validated['approver_1'],
                'approval_order' => 1,
            ]);

            if (! empty($validated['approver_2'])) {
                InventoryModuleApprover::create([
                    'inventory_module_config_id' => $config->id,
                    'user_id' => $validated['approver_2'],
                    'approval_order' => 2,
                ]);
            }

            $config->update(['updated_by' => Auth::id()]);
        });

        return redirect()->route('inventory.approvers')
            ->with('success', 'GRN approvers updated successfully.');
    }

    private function businessConfig(): InventoryModuleConfig
    {
        return InventoryModuleConfig::query()
            ->where('business_id', Auth::user()->business_id)
            ->where('is_active', true)
            ->firstOrFail();
    }
}
