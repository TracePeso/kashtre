<?php

namespace App\Services\Inventory;

use App\Models\InventoryEvaluationCommitteeMember;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryOrder;
use App\Models\InventoryOrderCommitteeMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryEvaluationCommitteeService
{
    /**
     * @param  list<array{user_id: int, role?: string}>  $memberInputs
     */
    public function syncDefaultMembers(InventoryModuleConfig $config, array $memberInputs): void
    {
        $this->assertValidMemberInputs($memberInputs, (int) $config->business_id);

        DB::transaction(function () use ($config, $memberInputs) {
            $config->evaluationCommitteeMembers()->delete();

            foreach ($memberInputs as $index => $input) {
                InventoryEvaluationCommitteeMember::create([
                    'inventory_module_config_id' => $config->id,
                    'user_id' => (int) $input['user_id'],
                    'role' => $this->normalizeRole($input['role'] ?? null),
                    'sort_order' => $index + 1,
                ]);
            }
        });
    }

    public function applyDefaultsToOrder(InventoryOrder $order, User $actor): void
    {
        if (! $order->isExternal()) {
            return;
        }

        $config = InventoryModuleConfig::query()
            ->forBusiness((int) $order->business_id)
            ->active()
            ->with('evaluationCommitteeMembers')
            ->first();

        if (! $config || $config->evaluationCommitteeMembers->isEmpty()) {
            return;
        }

        $inputs = $config->evaluationCommitteeMembers
            ->sortBy('sort_order')
            ->map(fn (InventoryEvaluationCommitteeMember $member) => [
                'user_id' => (int) $member->user_id,
                'role' => $member->role,
            ])
            ->values()
            ->all();

        $this->syncOrderMembers($order, $inputs, $actor);
    }

    /**
     * @param  list<array{user_id: int, role?: string}>  $memberInputs
     */
    public function syncOrderMembers(InventoryOrder $order, array $memberInputs, User $actor): void
    {
        if (! $order->isExternal()) {
            throw ValidationException::withMessages([
                'committee' => 'Evaluation committees apply to external purchase orders only.',
            ]);
        }

        $this->assertValidMemberInputs($memberInputs, (int) $order->business_id);

        DB::transaction(function () use ($order, $memberInputs, $actor) {
            $order->committeeMembers()->delete();

            foreach ($memberInputs as $index => $input) {
                InventoryOrderCommitteeMember::create([
                    'inventory_order_id' => $order->id,
                    'user_id' => (int) $input['user_id'],
                    'role' => $this->normalizeRole($input['role'] ?? null),
                    'sort_order' => $index + 1,
                    'appointed_by_user_id' => $actor->id,
                ]);
            }
        });
    }

    public function ensureOrderCommittee(InventoryOrder $order, User $actor): void
    {
        if (! $order->isExternal()) {
            return;
        }

        $config = InventoryModuleConfig::query()
            ->forBusiness((int) $order->business_id)
            ->active()
            ->first();

        $order->loadMissing('committeeMembers');

        if ($order->committeeMembers->isEmpty()) {
            $this->applyDefaultsToOrder($order, $actor);
            $order->load('committeeMembers');
        }

        if ($order->committeeMembers->isNotEmpty() || ! ($config?->evaluationCommitteeRequired() ?? false)) {
            return;
        }

        throw ValidationException::withMessages([
            'committee' => 'Appoint at least one evaluation committee member before submitting this purchase request. Your organisation requires an evaluation committee on external orders — configure defaults under Inventory → Settings → Evaluation committee, or appoint members on this order.',
        ]);
    }

    public function isRequiredForBusiness(int $businessId): bool
    {
        $config = InventoryModuleConfig::query()
            ->forBusiness($businessId)
            ->active()
            ->first();

        return $config?->evaluationCommitteeRequired() ?? false;
    }

    /**
     * @return list<array{user_id: int, role: string}>
     */
    public function memberInputsFromRequest(array $memberIds, ?int $chairUserId): array
    {
        $memberIds = collect($memberIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($chairUserId && ! $memberIds->contains($chairUserId)) {
            $chairUserId = null;
        }

        return $memberIds->map(function (int $userId) use ($chairUserId) {
            return [
                'user_id' => $userId,
                'role' => $chairUserId === $userId
                    ? InventoryOrderCommitteeMember::ROLE_CHAIR
                    : InventoryOrderCommitteeMember::ROLE_MEMBER,
            ];
        })->all();
    }

    /**
     * @param  list<array{user_id: int, role?: string}>  $memberInputs
     */
    private function assertValidMemberInputs(array $memberInputs, int $businessId): void
    {
        $chairs = 0;

        foreach ($memberInputs as $index => $input) {
            $userId = (int) ($input['user_id'] ?? 0);

            if ($userId < 1) {
                throw ValidationException::withMessages([
                    "committee_members.{$index}.user_id" => 'Select a valid committee member.',
                ]);
            }

            $role = $this->normalizeRole($input['role'] ?? null);

            if ($role === InventoryOrderCommitteeMember::ROLE_CHAIR) {
                $chairs++;
            }
        }

        if ($chairs > 1) {
            throw ValidationException::withMessages([
                'committee_members' => 'Only one committee chair can be appointed.',
            ]);
        }

        if ($memberInputs === []) {
            return;
        }

        $userIds = collect($memberInputs)->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $validCount = User::query()
            ->where('business_id', $businessId)
            ->where('status', 'active')
            ->whereIn('id', $userIds)
            ->count();

        if ($validCount !== count(array_unique($userIds))) {
            throw ValidationException::withMessages([
                'committee_members' => 'Committee members must be active staff of your organisation.',
            ]);
        }
    }

    private function normalizeRole(?string $role): string
    {
        return $role === InventoryOrderCommitteeMember::ROLE_CHAIR
            ? InventoryOrderCommitteeMember::ROLE_CHAIR
            : InventoryOrderCommitteeMember::ROLE_MEMBER;
    }
}
