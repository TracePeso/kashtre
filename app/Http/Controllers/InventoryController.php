<?php

namespace App\Http\Controllers;

use App\Models\InventoryModuleApprover;
use App\Models\InventoryModuleConfig;
use App\Models\Item;
use App\Models\User;
use App\Support\InventoryBusinessContext;
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

            if ((int) $user->business_id === 1 && ! InventoryBusinessContext::hasContext()) {
                abort(403, 'Select an organisation from Inventory Module Configuration and choose Browse inventory.');
            }

            if (! InventoryBusinessContext::moduleEnabled()) {
                abort(403, 'The inventory module is not enabled for this organisation.');
            }

            if (InventoryBusinessContext::isAdminBrowsing() && ! in_array($request->method(), ['GET', 'HEAD'], true)) {
                abort(403, 'Read-only while browsing another organisation\'s inventory.');
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
        if ((int) $item->business_id !== InventoryBusinessContext::effectiveBusinessId()) {
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
            ->where('business_id', InventoryBusinessContext::effectiveBusinessId())
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $canManageApprovers = ! InventoryBusinessContext::isAdminBrowsing()
            && in_array('Edit Business Settings', Auth::user()->permissions ?? []);

        return view('inventory.approvers', compact('config', 'businessUsers', 'canManageApprovers'));
    }

    public function updateApprovers(Request $request)
    {
        InventoryBusinessContext::assertWritable();

        if (! in_array('Edit Business Settings', Auth::user()->permissions ?? [])) {
            abort(403, 'You do not have permission to update goods receive note approvers.');
        }

        $config = $this->businessConfig();

        $validated = $request->validate([
            'approver_1' => 'required|exists:users,id',
            'approver_2' => 'nullable|exists:users,id|different:approver_1|different:technical_supervisor',
            'technical_supervisor' => 'nullable|exists:users,id|different:approver_1|different:approver_2',
            'finance_notification_emails' => 'nullable|string|max:2000',
            'lpo_email_copy_to_approvers' => 'nullable|boolean',
        ]);

        $businessId = InventoryBusinessContext::effectiveBusinessId();
        $ids = array_values(array_filter([
            (int) $validated['approver_1'],
            ! empty($validated['approver_2']) ? (int) $validated['approver_2'] : null,
            ! empty($validated['technical_supervisor']) ? (int) $validated['technical_supervisor'] : null,
        ]));

        $validCount = User::query()
            ->where('business_id', $businessId)
            ->whereIn('id', $ids)
            ->count();

        if ($validCount !== count($ids)) {
            throw ValidationException::withMessages([
                'approver_1' => 'Selected approvers must be active staff of your organisation.',
            ]);
        }

        DB::transaction(function () use ($config, $validated, $request) {
            $config->approvers()->delete();

            if (! empty($validated['technical_supervisor'])) {
                InventoryModuleApprover::create([
                    'inventory_module_config_id' => $config->id,
                    'user_id' => $validated['technical_supervisor'],
                    'role' => InventoryModuleApprover::ROLE_TECHNICAL_SUPERVISOR,
                    'approval_order' => 0,
                ]);
            }

            InventoryModuleApprover::create([
                'inventory_module_config_id' => $config->id,
                'user_id' => $validated['approver_1'],
                'role' => InventoryModuleApprover::ROLE_APPROVER,
                'approval_order' => 1,
            ]);

            if (! empty($validated['approver_2'])) {
                InventoryModuleApprover::create([
                    'inventory_module_config_id' => $config->id,
                    'user_id' => $validated['approver_2'],
                    'role' => InventoryModuleApprover::ROLE_APPROVER,
                    'approval_order' => 2,
                ]);
            }

            $config->update([
                'updated_by' => Auth::id(),
                'finance_notification_emails' => $validated['finance_notification_emails'] ?? null,
                'lpo_email_copy_to_approvers' => $request->boolean('lpo_email_copy_to_approvers'),
            ]);
        });

        return redirect()->route('inventory.approvers')
            ->with('success', 'Goods receive note approvers updated successfully.');
    }

    private function businessConfig(): InventoryModuleConfig
    {
        return InventoryModuleConfig::query()
            ->where('business_id', InventoryBusinessContext::effectiveBusinessId())
            ->where('is_active', true)
            ->firstOrFail();
    }
}
