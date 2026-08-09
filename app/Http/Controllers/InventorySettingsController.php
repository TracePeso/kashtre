<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
use App\Models\InventoryModuleApprover;
use App\Models\InventoryModuleConfig;
use App\Models\User;
use App\Services\Inventory\InventoryEvaluationCommitteeService;
use App\Support\InventoryBusinessContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventorySettingsController extends Controller
{
    use RequiresInventoryModule;

    public function __construct(
        private readonly InventoryEvaluationCommitteeService $committeeService,
    ) {
        $this->middleware($this->inventoryMiddleware(...));
    }

    public function edit(Request $request)
    {
        $config = $this->businessConfig();
        $config->load(['approvers.user', 'evaluationCommitteeMembers.user']);

        $activeTab = $request->query('tab', 'notifications');
        if (! in_array($activeTab, ['notifications', 'approvers', 'evaluation-committee', 'space-routing', 'capabilities'], true)) {
            $activeTab = 'notifications';
        }

        $canManage = ! InventoryBusinessContext::isAdminBrowsing()
            && in_array('Edit Business Settings', Auth::user()->permissions ?? []);

        $businessUsers = User::query()
            ->where('business_id', InventoryBusinessContext::effectiveBusinessId())
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $defaultChair = $config->evaluationCommitteeMembers->firstWhere('role', 'chair');

        return view('inventory.settings.edit', [
            'config' => $config,
            'canManage' => $canManage,
            'businessUsers' => $businessUsers,
            'activeTab' => $activeTab,
            'defaultCommitteeMemberIds' => $config->evaluationCommitteeMembers->pluck('user_id')->map(fn ($id) => (int) $id)->all(),
            'defaultCommitteeChairId' => $defaultChair?->user_id,
        ]);
    }

    public function update(Request $request)
    {
        InventoryBusinessContext::assertWritable();

        if (! in_array('Edit Business Settings', Auth::user()->permissions ?? [])) {
            abort(403, 'You do not have permission to update inventory settings.');
        }

        $validated = $request->validate([
            'finance_notification_emails' => 'nullable|string|max:2000',
            'lpo_email_copy_to_approvers' => 'nullable|boolean',
            'notify_approvers_on_order_submitted' => 'nullable|boolean',
            'notify_finance_on_order_submitted' => 'nullable|boolean',
            'notify_next_approver_on_approval' => 'nullable|boolean',
            'notify_on_order_fully_approved' => 'nullable|boolean',
            'notify_suppliers_on_rfq_approved' => 'nullable|boolean',
            'notify_on_lpo_issued' => 'nullable|boolean',
        ]);

        $config = $this->businessConfig();

        $config->update([
            'updated_by' => Auth::id(),
            'finance_notification_emails' => $validated['finance_notification_emails'] ?? null,
            'lpo_email_copy_to_approvers' => $request->boolean('lpo_email_copy_to_approvers'),
            'notify_approvers_on_order_submitted' => $request->boolean('notify_approvers_on_order_submitted'),
            'notify_finance_on_order_submitted' => $request->boolean('notify_finance_on_order_submitted'),
            'notify_next_approver_on_approval' => $request->boolean('notify_next_approver_on_approval'),
            'notify_on_order_fully_approved' => $request->boolean('notify_on_order_fully_approved'),
            'notify_suppliers_on_rfq_approved' => $request->boolean('notify_suppliers_on_rfq_approved'),
            'notify_on_lpo_issued' => $request->boolean('notify_on_lpo_issued'),
        ]);

        return redirect()
            ->route('inventory.settings.edit', ['tab' => 'notifications'])
            ->with('success', 'Notification settings saved.');
    }

    public function updateCapabilities(Request $request)
    {
        InventoryBusinessContext::assertWritable();

        if (! in_array('Edit Business Settings', Auth::user()->permissions ?? [])) {
            abort(403, 'You do not have permission to update inventory settings.');
        }

        $config = $this->businessConfig();

        $config->update([
            'updated_by' => Auth::id(),
            'enable_floor_stock_management' => $request->boolean('enable_floor_stock_management'),
            'enable_crash_cart_management' => $request->boolean('enable_crash_cart_management'),
            'enable_batch_lot_tracking' => $request->boolean('enable_batch_lot_tracking'),
            'enable_serial_number_tracking' => $request->boolean('enable_serial_number_tracking'),
            'visit_reactivation_lookback_days' => max(1, (int) $request->input('visit_reactivation_lookback_days', 30)),
            'label_dictionary' => [
                'client' => trim((string) $request->input('label_client', '')),
                'client_space' => trim((string) $request->input('label_client_space', '')),
                'item' => trim((string) $request->input('label_item', '')),
                'store' => trim((string) $request->input('label_store', '')),
                'usage_record' => trim((string) $request->input('label_usage_record', '')),
            ],
        ]);

        return redirect()
            ->route('inventory.settings.edit', ['tab' => 'capabilities'])
            ->with('success', 'Capability settings saved.');
    }

    public function updateApprovers(Request $request)
    {
        InventoryBusinessContext::assertWritable();

        if (! in_array('Edit Business Settings', Auth::user()->permissions ?? [])) {
            abort(403, 'You do not have permission to update inventory approvers.');
        }

        $config = $this->businessConfig();

        $validated = $request->validate([
            'approver_1' => 'required|exists:users,id',
            'approver_2' => 'nullable|exists:users,id|different:approver_1',
        ]);

        $businessId = InventoryBusinessContext::effectiveBusinessId();
        $ids = array_values(array_filter([
            (int) $validated['approver_1'],
            ! empty($validated['approver_2']) ? (int) $validated['approver_2'] : null,
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

        DB::transaction(function () use ($config, $validated) {
            $config->approvers()->delete();

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
            ]);
        });

        return redirect()
            ->route('inventory.settings.edit', ['tab' => 'approvers'])
            ->with('success', 'Approvers updated successfully.');
    }

    public function updateEvaluationCommittee(Request $request)
    {
        InventoryBusinessContext::assertWritable();

        if (! in_array('Edit Business Settings', Auth::user()->permissions ?? [])) {
            abort(403, 'You do not have permission to update the evaluation committee.');
        }

        $validated = $request->validate([
            'committee_members' => 'nullable|array',
            'committee_members.*' => 'integer|exists:users,id',
            'committee_chair_user_id' => 'nullable|integer|exists:users,id',
            'evaluation_committee_required' => 'nullable|boolean',
        ]);

        $config = $this->businessConfig();

        try {
            $this->committeeService->syncDefaultMembers(
                $config,
                $this->committeeService->memberInputsFromRequest(
                    $validated['committee_members'] ?? [],
                    isset($validated['committee_chair_user_id']) ? (int) $validated['committee_chair_user_id'] : null,
                ),
            );
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        $config->update([
            'updated_by' => Auth::id(),
            'evaluation_committee_required' => $request->boolean('evaluation_committee_required'),
        ]);

        return redirect()
            ->route('inventory.settings.edit', ['tab' => 'evaluation-committee'])
            ->with('success', 'Default evaluation committee saved.');
    }

    private function businessConfig(): InventoryModuleConfig
    {
        $config = InventoryBusinessContext::moduleConfig();

        if (! $config) {
            abort(404, 'Inventory module is not configured for this organisation.');
        }

        return $config;
    }
}
