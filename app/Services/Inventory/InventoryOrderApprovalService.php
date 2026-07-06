<?php

namespace App\Services\Inventory;

use App\Models\GoodsReceivedNote;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryOrder;
use App\Models\InventoryOrderApproval;
use App\Models\InventoryOrderLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryOrderApprovalService
{
    public function submit(InventoryOrder $order, User $user): InventoryOrder
    {
        if (! $order->isDraft()) {
            throw ValidationException::withMessages([
                'status' => $order->isInternal()
                    ? 'Only draft internal orders can be submitted for approval.'
                    : 'Only draft RFQs can be submitted for approval.',
            ]);
        }

        if ($order->lines()->count() < 1) {
            throw ValidationException::withMessages([
                'lines' => $order->isInternal()
                    ? 'Add at least one line item before submitting.'
                    : 'Add at least one RFQ line before submitting.',
            ]);
        }

        if ($order->isExternal() && ! $order->supplier_id) {
            throw ValidationException::withMessages([
                'supplier' => 'Select a supplier for this RFQ before submitting.',
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
                'approvers' => 'No goods receive note approvers are configured. Set them under Inventory → Goods receive note approvers.',
            ]);
        }

        $firstApprovalOrder = (int) $approvers->min('approval_order');

        return DB::transaction(function () use ($order, $user, $approvers, $firstApprovalOrder) {
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

            return $order->fresh(['lines.item', 'approvals.approver', 'store']);
        });
    }

    public function approve(InventoryOrder $order, User $user, ?string $comment = null): InventoryOrder
    {
        if (! $order->isPendingApproval()) {
            throw ValidationException::withMessages([
                'status' => 'This RFQ is not awaiting approval.',
            ]);
        }

        $pending = $this->currentPendingApproval($order);

        if (! $pending || (int) $pending->approver_user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'approver' => 'You are not the approver for the current step.',
            ]);
        }

        return DB::transaction(function () use ($order, $user, $pending, $comment) {
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

                return $order->fresh(['lines.item', 'approvals.approver', 'store']);
            }

            $finalStatus = $order->isInternal()
                ? InventoryOrder::STATUS_FULFILLED
                : InventoryOrder::STATUS_APPROVED;

            $order->update([
                'status' => $finalStatus,
                'approved_at' => now(),
                'current_approval_order' => null,
            ]);

            return $order->fresh(['lines.item', 'approvals.approver', 'store']);
        });
    }

    public function reject(InventoryOrder $order, User $user, string $reason): InventoryOrder
    {
        if (! $order->isPendingApproval()) {
            throw ValidationException::withMessages([
                'status' => 'This RFQ is not awaiting approval.',
            ]);
        }

        $pending = $this->currentPendingApproval($order);

        if (! $pending || (int) $pending->approver_user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'approver' => 'You are not the approver for the current step.',
            ]);
        }

        return DB::transaction(function () use ($order, $user, $pending, $reason) {
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

            return $order->fresh(['lines.item', 'approvals.approver', 'store']);
        });
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

        return $config->approvers()->orderBy('approval_order')->get();
    }
}
