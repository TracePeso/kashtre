<?php

namespace App\Support\Clinical;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Who is performing a clinical action, and under which facility.
 *
 * Every clinical act needs an attributable clinician — for ReBAC, for the
 * audit trail, and for prescriber accountability (API Integration Guide §3.2).
 * Passing this explicitly instead of reaching for Auth::user() inside each
 * gateway keeps the gateways testable and makes it impossible to accidentally
 * write an unattributed record.
 *
 * `branchId` has no counterpart in the Clinical API — Clinical scopes by
 * tenant only. It is carried here because the local engines still write it,
 * and it is simply dropped by the API implementations.
 */
class ClinicalActor
{
    /**
     * @param  array<int, string>  $permissions
     */
    public function __construct(
        public readonly int $userId,
        public readonly int $businessId,
        public readonly ?int $branchId = null,
        public readonly string $name = '',
        public readonly array $permissions = [],
    ) {
    }

    public static function fromUser(?User $user = null): self
    {
        $user ??= Auth::user();

        if (! $user) {
            throw new RuntimeException('A clinical action requires an authenticated user.');
        }

        return new self(
            userId: (int) $user->id,
            businessId: (int) $user->business_id,
            branchId: $user->branch_id ? (int) $user->branch_id : null,
            name: (string) $user->name,
            permissions: (array) ($user->permissions ?? []),
        );
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}
