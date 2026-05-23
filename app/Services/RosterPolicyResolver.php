<?php

namespace App\Services;

use App\Models\HrPolicyVersion;
use App\Models\Organization;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class RosterPolicyResolver
{
    public function activeVersionFor(Organization $organization, ?CarbonInterface $date = null): ?HrPolicyVersion
    {
        $date ??= now();

        return HrPolicyVersion::query()
            ->with('regionalPolicy')
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->where(function ($query) use ($date): void {
                $query
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date->toDateString());
            })
            ->whereHas('regionalPolicy', fn ($query) => $query->where('is_active', true))
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    public function requireActiveVersion(Organization $organization, ?CarbonInterface $date = null): HrPolicyVersion
    {
        $policy = $this->activeVersionFor($organization, $date);

        if ($policy) {
            return $policy;
        }

        throw ValidationException::withMessages([
            'roster' => 'Configure an active HR regional policy before saving rostered shifts.',
        ]);
    }
}
