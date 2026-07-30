<?php

namespace App\Services\Inventory;

use App\Models\InventoryModuleApprover;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryOrder;
use App\Models\InventoryOrderApproval;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryOrderApprovalService
{
    public function __construct(
        private readonly InventoryProcurementNotificationService $notifications,
        private readonly InventoryEvaluationCommitteeService $committeeService,
    ) {}

    public function submit(InventoryOrder $order, User $user): InventoryOrder
    {
        if (! $order->isDraft()) {
            throw ValidationException::withMessages([
                'status' => $order->isInternal()
                    ? 'Only draft internal orders can be submitted for approval.'
                    : 'Only draft purchase requests can be submitted for approval.',
            ]);
        }

        if ($order->lines()->count() < 1) {
            throw ValidationException::withMessages([
                'lines' => $order->isInternal()
                    ? 'Add at least one line item before submitting.'
                    : 'Add at least one line before submitting.',
            ]);
        }

        if ($order->isInternal()) {
            if (! $order->source_store_id) {
                throw ValidationException::withMessages([
                    'source_store_id' => 'Select the supplying store for this internal order.',
                ]);
            }

            $sourceStore = $order->sourceStore;
            $receivingStore = $order->store;

            if (! $sourceStore || ! $receivingStore || ! $sourceStore->canTransferStockTo($receivingStore)) {
                throw ValidationException::withMessages([
                    'source_store_id' => 'The supplying and receiving stores are not a valid internal order pair.',
                ]);
            }
        }

        $approvers = $this->configuredApprovers((int) $order->business_id);

        if ($approvers->isEmpty()) {
            throw ValidationException::withMessages([
                'approvers' => 'No inventory approvers are configured. Set them under Inventory → Settings → Approvers.',
            ]);
        }

        $this->committeeService->ensureOrderCommittee($order, $user);

        $firstApprovalOrder = (int) $approvers->min('approval_order');

        $order = DB::transaction(function () use ($order, $user, $approvers, $firstApprovalOrder) {
            if ($order->isExternal()) {
                app(InventoryOrderService::class)->refreshRfqDocument($order);
            }

            $order->update([
                'status' => InventoryOrder::STATUS_PENDING_APPROVAL,
                'current_approval_order' => $firstApprovalOrder,
                'submitted_by_user_id' => $user->id,
                'submitted_at' => now(),
                'rejection_reason' => null,
                'approved_at' => null,
            ]);

            $order->approvals()->delete();

            foreach ($approvers as $approver) {
                InventoryOrderApproval::create([
                    'inventory_order_id' => $order->id,
                    'approver_user_id' => $approver->user_id,
                    'approval_order' => $approver->approval_order,
                    'status' => InventoryOrderApproval::STATUS_PENDING,
                ]);
            }

            return $order->fresh(['lines.item', 'approvals.approver', 'store', 'supplier', 'createdBy', 'business', 'sourceStore']);
        });

        InventoryProcurementAudit::log(
            'submitted_for_approval',
            $order,
            ($order->isInternal() ? 'Internal order' : 'RFQ').' '.$order->order_number.' submitted for approval',
            why: 'Submitted by '.$user->name,
            newValues: [
                'status' => $order->status,
                'approver_count' => $approvers->count(),
            ],
        );

        $this->notifications->notifySubmitted($order);

        return $order;
    }

    public function approve(InventoryOrder $order, User $user, ?string $comment = null): InventoryOrder
    {
        if (! $order->isPendingApproval()) {
            throw ValidationException::withMessages([
                'status' => 'This order is not awaiting approval.',
            ]);
        }

        $pending = $this->currentPendingApproval($order);

        if (! $pending || (int) $pending->approver_user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'approver' => 'You are not the approver for the current step.',
            ]);
        }

        $result = DB::transaction(function () use ($order, $user, $pending, $comment) {
            $pending->update([
                'status' => InventoryOrderApproval::STATUS_APPROVED,
                'comment' => $comment,
                'acted_at' => now(),
            ]);

            $nextPending = $order->approvals()
                ->where('status', InventoryOrderApproval::STATUS_PENDING)
                ->orderBy('approval_order')
                ->first();

            if ($nextPending) {
                $order->update(['current_approval_order' => $nextPending->approval_order]);

                return [
                    'order' => $order->fresh(['lines.item', 'approvals.approver', 'store', 'supplier', 'createdBy', 'business', 'sourceStore']),
                    'fully_approved' => false,
                ];
            }

            $order->update([
                'status' => InventoryOrder::STATUS_APPROVED,
                'approved_at' => now(),
                'current_approval_order' => null,
            ]);

            return [
                'order' => $order->fresh(['lines.item', 'approvals.approver', 'store', 'supplier', 'createdBy', 'business', 'sourceStore']),
                'fully_approved' => true,
            ];
        });

        /** @var InventoryOrder $fresh */
        $fresh = $result['order'];
        $fullyApproved = (bool) $result['fully_approved'];

        InventoryProcurementAudit::log(
            $fullyApproved ? 'fully_approved' : 'step_approved',
            $fresh,
            ($fresh->isInternal() ? 'Internal order' : 'RFQ').' '.$fresh->order_number.
                ($fullyApproved ? ' fully approved' : ' step '.$pending->approval_order.' approved'),
            why: $comment,
            newValues: [
                'status' => $fresh->status,
                'approval_order' => $pending->approval_order,
            ],
        );

        if ($fullyApproved) {
            $this->notifications->notifyFullyApproved($fresh, $user);

            if ($fresh->isInternal() && $fresh->canCreateStockTransfer()) {
                try {
                    app(InventoryStockTransferService::class)->createFromInternalOrder($fresh, $user);
                } catch (ValidationException) {
                    // Order is approved; user can still create the transfer manually.
                }
            }
        } else {
            $this->notifications->notifyNextApprover($fresh);
        }

        return $fresh->fresh(['stockTransfers', 'lines.item', 'approvals.approver', 'store', 'sourceStore', 'createdBy', 'business']);
    }

    public function reject(InventoryOrder $order, User $user, string $reason): InventoryOrder
    {
        if (! $order->isPendingApproval()) {
            throw ValidationException::withMessages([
                'status' => 'This order is not awaiting approval.',
            ]);
        }

        $pending = $this->currentPendingApproval($order);

        if (! $pending || (int) $pending->approver_user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'approver' => 'You are not the approver for the current step.',
            ]);
        }

        $order = DB::transaction(function () use ($order, $pending, $reason) {
            $pending->update([
                'status' => InventoryOrderApproval::STATUS_REJECTED,
                'comment' => $reason,
                'acted_at' => now(),
            ]);

            $order->update([
                'status' => InventoryOrder::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'current_approval_order' => null,
            ]);

            return $order->fresh(['lines.item', 'approvals.approver', 'store', 'supplier', 'createdBy', 'business']);
        });

        InventoryProcurementAudit::log(
            'rejected',
            $order,
            ($order->isInternal() ? 'Internal order' : 'RFQ').' '.$order->order_number.' rejected',
            why: $reason,
            newValues: ['status' => $order->status],
        );

        return $order;
    }

    public function userCanApprove(InventoryOrder $order, User $user): bool
    {
        if (! $order->isPendingApproval()) {
            return false;
        }

        $pending = $this->currentPendingApproval($order);

        return $pending && (int) $pending->approver_user_id === (int) $user->id;
    }

    private function currentPendingApproval(InventoryOrder $order): ?InventoryOrderApproval
    {
        return $order->approvals()
            ->where('status', InventoryOrderApproval::STATUS_PENDING)
            ->orderBy('approval_order')
            ->first();
    }

    private function configuredApprovers(int $businessId)
    {
        $config = InventoryModuleConfig::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->first();

        if (! $config) {
            return collect();
        }

        return $config->approvers()
            ->where('role', InventoryModuleApprover::ROLE_APPROVER)
            ->orderBy('approval_order')
            ->get();
    }
}
