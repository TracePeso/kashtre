<?php

namespace App\Services\Inventory;

use App\Models\InventoryModuleApprover;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryStockCount;
use App\Models\InventoryStockCountApproval;
use App\Models\InventoryStockCountLine;
use App\Models\InventoryStockLevel;
use App\Models\InventoryStockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryStockCountService
{
    public function __construct(
        private readonly InventoryStockAnalyticsService $analytics,
        private readonly InventoryStockCountShrinkageService $shrinkage
    ) {}

    public function generateReference(int $businessId): string
    {
        $prefix = 'SC-'.now()->format('Ymd');
        $count = InventoryStockCount::query()
            ->where('business_id', $businessId)
            ->where('reference', 'like', $prefix.'%')
            ->count();

        return $prefix.'-'.str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
    }

    public function createDraft(int $businessId, int $storeId, User $user, ?string $notes = null): InventoryStockCount
    {
        return DB::transaction(function () use ($businessId, $storeId, $user, $notes) {
            $count = InventoryStockCount::create([
                'business_id' => $businessId,
                'store_id' => $storeId,
                'reference' => $this->generateReference($businessId),
                'status' => InventoryStockCount::STATUS_DRAFT,
                'counted_at' => now(),
                'notes' => $notes,
                'created_by_user_id' => $user->id,
            ]);

            $levels = InventoryStockLevel::query()
                ->where('business_id', $businessId)
                ->where('store_id', $storeId)
                ->where('quantity_suom', '>', 0)
                ->get();

            foreach ($levels as $level) {
                InventoryStockCountLine::create([
                    'inventory_stock_count_id' => $count->id,
                    'item_id' => $level->item_id,
                    'system_quantity_suom' => $level->quantity_suom,
                    'physical_quantity_suom' => $level->physical_quantity_suom ?? $level->quantity_suom,
                    'damaged_quantity_suom' => $level->damaged_quantity_suom ?? 0,
                    'expired_quantity_suom' => $level->expired_quantity_suom ?? 0,
                ]);
            }

            return $count->load(['lines.item', 'store']);
        });
    }

    public function updateLine(
        InventoryStockCountLine $line,
        float $physicalQty,
        float $damagedQty,
        float $expiredQty = 0
    ): InventoryStockCountLine {
        if (! $line->stockCount->isDraft()) {
            throw ValidationException::withMessages([
                'status' => 'Only draft stock counts can be edited.',
            ]);
        }

        $line->update([
            'physical_quantity_suom' => max(0, $physicalQty),
            'damaged_quantity_suom' => max(0, $damagedQty),
            'expired_quantity_suom' => max(0, $expiredQty),
        ]);

        return $line->fresh('item');
    }

    public function submit(InventoryStockCount $count, User $user): InventoryStockCount
    {
        if (! $count->isDraft()) {
            throw ValidationException::withMessages([
                'status' => 'Only draft stock counts can be submitted.',
            ]);
        }

        if ($count->lines()->count() < 1) {
            throw ValidationException::withMessages([
                'lines' => 'Add at least one item line before submitting.',
            ]);
        }

        $approvers = $this->configuredApprovers($count->business_id);

        if ($approvers->isEmpty()) {
            throw ValidationException::withMessages([
                'approvers' => 'No goods receive note approvers are configured. Set them under Inventory → Goods receive note approvers.',
            ]);
        }

        $firstApprovalOrder = (int) $approvers->min('approval_order');

        return DB::transaction(function () use ($count, $user, $approvers, $firstApprovalOrder) {
            $count->update([
                'status' => InventoryStockCount::STATUS_PENDING,
                'current_approval_order' => $firstApprovalOrder,
                'submitted_by_user_id' => $user->id,
                'submitted_at' => now(),
            ]);

            foreach ($approvers as $approver) {
                InventoryStockCountApproval::create([
                    'inventory_stock_count_id' => $count->id,
                    'approver_user_id' => $approver->user_id,
                    'approval_order' => $approver->approval_order,
                    'status' => InventoryStockCountApproval::STATUS_PENDING,
                ]);
            }

            return $count->fresh(['lines.item', 'store', 'approvals.approver', 'submittedBy']);
        });
    }

    public function approve(InventoryStockCount $count, User $user, ?string $comment = null): InventoryStockCount
    {
        if (! $count->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'This stock count is not awaiting approval.',
            ]);
        }

        $pending = $this->currentPendingApproval($count);

        if (! $pending) {
            throw ValidationException::withMessages([
                'status' => 'No pending approval step found.',
            ]);
        }

        if ((int) $pending->approver_user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'approver' => 'You are not the approver for the current step.',
            ]);
        }

        return DB::transaction(function () use ($count, $user, $pending, $comment) {
            $pending->update([
                'status' => InventoryStockCountApproval::STATUS_APPROVED,
                'comment' => $comment,
                'acted_at' => now(),
            ]);

            $nextPending = $count->approvals()
                ->where('status', InventoryStockCountApproval::STATUS_PENDING)
                ->orderBy('approval_order')
                ->first();

            if ($nextPending) {
                $count->update([
                    'current_approval_order' => $nextPending->approval_order,
                ]);

                return $count->fresh(['lines.item', 'store', 'approvals.approver', 'submittedBy']);
            }

            $this->finalizeApproval($count, $user);

            return $count->fresh(['lines.item', 'store', 'approvals.approver', 'submittedBy', 'finalizedBy']);
        });
    }

    public function reject(InventoryStockCount $count, User $user, string $reason): InventoryStockCount
    {
        if (! $count->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'This stock count is not awaiting approval.',
            ]);
        }

        $pending = $this->currentPendingApproval($count);

        if (! $pending || (int) $pending->approver_user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'approver' => 'You are not the approver for the current step.',
            ]);
        }

        return DB::transaction(function () use ($count, $user, $pending, $reason) {
            $pending->update([
                'status' => InventoryStockCountApproval::STATUS_REJECTED,
                'comment' => $reason,
                'acted_at' => now(),
            ]);

            $count->update([
                'status' => InventoryStockCount::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'current_approval_order' => null,
            ]);

            return $count->fresh(['lines.item', 'store', 'approvals.approver', 'submittedBy']);
        });
    }

    public function userCanApprove(InventoryStockCount $count, User $user): bool
    {
        if (! $count->isPending()) {
            return false;
        }

        $pending = $this->currentPendingApproval($count);

        return $pending && (int) $pending->approver_user_id === (int) $user->id;
    }

    /** @deprecated Use submit() then approval flow */
    public function finalize(InventoryStockCount $count, User $user): InventoryStockCount
    {
        return $this->submit($count, $user);
    }

    private function finalizeApproval(InventoryStockCount $count, User $user): void
    {
        $count->update([
            'status' => InventoryStockCount::STATUS_APPROVED,
            'approved_at' => now(),
            'current_approval_order' => null,
            'finalized_by_user_id' => $user->id,
            'finalized_at' => now(),
        ]);

        $this->applyStock($count, $user);

        $count->update(['stock_applied_at' => now()]);
    }

    private function applyStock(InventoryStockCount $count, User $user): void
    {
        $count->load('lines');

        foreach ($count->lines as $line) {
            $unitCost = $this->shrinkage->unitCostForLine($count, $line);
            $this->shrinkage->snapshotLineShrinkage($line, $unitCost);

            $stock = InventoryStockLevel::firstOrCreate(
                [
                    'business_id' => $count->business_id,
                    'store_id' => $count->store_id,
                    'item_id' => $line->item_id,
                ],
                ['quantity_suom' => 0, 'physical_quantity_suom' => 0]
            );

            $balanceBefore = (float) $stock->quantity_suom;
            $physical = (float) $line->physical_quantity_suom;
            $variance = round($physical - $balanceBefore, 4);

            $stock->snapshotPhysicalCount($physical, $count->counted_at ?? now());
            $stock->damaged_quantity_suom = (float) $line->damaged_quantity_suom;
            $stock->expired_quantity_suom = (float) $line->expired_quantity_suom;
            $stock->save();

            if ($variance != 0.0) {
                InventoryStockMovement::create([
                    'business_id' => $count->business_id,
                    'item_id' => $line->item_id,
                    'store_id' => $count->store_id,
                    'movement_type' => InventoryStockMovement::TYPE_STOCK_COUNT,
                    'quantity_delta' => $variance,
                    'balance_after' => $physical,
                    'reference_label' => $count->reference,
                    'recorded_by_user_id' => $user->id,
                    'occurred_at' => $count->counted_at ?? now(),
                ]);
            }

            $this->analytics->recalculateForStockLevel($stock->fresh());
        }
    }

    private function currentPendingApproval(InventoryStockCount $count): ?InventoryStockCountApproval
    {
        return $count->approvals()
            ->where('status', InventoryStockCountApproval::STATUS_PENDING)
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
