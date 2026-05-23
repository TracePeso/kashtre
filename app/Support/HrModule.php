<?php

namespace App\Support;

use App\Models\User;

class HrModule
{
    public const ACCESS_PERMISSIONS = [
        'View HR Staff',
        'Add HR Staff',
        'Edit HR Staff',
        'View HR Setup',
        'Add HR Setup',
        'Edit HR Setup',
        'View HR Approvals',
        'Edit HR Approvals',
    ];

    public static function userCanAccess(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return count(array_intersect(self::ACCESS_PERMISSIONS, (array) ($user->permissions ?? []))) > 0;
    }
}
