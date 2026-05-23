<?php

namespace App\Support;

use App\Models\CreditLimitApprovalApprover;
use App\Models\CreditLimitChangeRequest;
use App\Models\User;

class SidebarMenu
{
    public static function pendingCreditLimitRequestCount(?User $user): int
    {
        if (! $user || ! $user->business_id) {
            return 0;
        }

        $pendingCount = 0;
        $userApproverLevels = CreditLimitApprovalApprover::where('business_id', $user->business_id)
            ->where('approver_id', $user->id)
            ->pluck('approval_level')
            ->toArray();

        if (in_array('authorizer', $userApproverLevels, true)) {
            $pendingCount += CreditLimitChangeRequest::where('business_id', $user->business_id)
                ->where('status', 'initiated')
                ->where('current_step', 2)
                ->whereHas('approvals', function ($query) use ($user) {
                    $query->where('approver_id', $user->id)
                        ->where('approval_level', 'authorizer')
                        ->whereNull('action');
                })
                ->count();
        }

        if (in_array('approver', $userApproverLevels, true)) {
            $pendingCount += CreditLimitChangeRequest::where('business_id', $user->business_id)
                ->where('status', 'authorized')
                ->where('current_step', 3)
                ->whereHas('approvals', function ($query) use ($user) {
                    $query->where('approver_id', $user->id)
                        ->where('approval_level', 'approver')
                        ->whereNull('action');
                })
                ->count();
        }

        return $pendingCount;
    }
}
