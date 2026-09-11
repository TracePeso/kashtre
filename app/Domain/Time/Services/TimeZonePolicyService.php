<?php

namespace App\Domain\Time\Services;

use App\Domain\Time\Contracts\Clock;
use App\Domain\Time\Enums\PolicyPurpose;
use App\Domain\Time\Enums\PolicyScopeType;
use App\Domain\Time\Enums\PolicyStatus;
use App\Domain\Time\Exceptions\TimeEngineException;
use App\Domain\Time\Models\TimeAudit;
use App\Domain\Time\Models\TimeZonePolicy;
use App\Domain\Time\ValueObjects\IanaTimezoneId;
use App\Domain\Time\ValueObjects\UtcInstant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class TimeZonePolicyService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly TimeZoneCatalogueService $catalogue,
        private readonly ?TimePolicyCacheService $cache = null,
    ) {}

    public function draft(
        string $tenantKey,
        PolicyScopeType $scopeType,
        string $subjectPublicId,
        string $ianaId,
        PolicyPurpose $purpose = PolicyPurpose::OPERATIONAL,
        ?UtcInstant $effectiveFrom = null,
        ?UtcInstant $effectiveTo = null,
        ?string $reason = null,
    ): TimeZonePolicy {
        $this->catalogue->ensureKnown($ianaId);
        $from = ($effectiveFrom ?? $this->clock->now())->toCarbon();

        $version = (int) TimeZonePolicy::query()
            ->where('tenant_key', $tenantKey)
            ->where('scope_type', $scopeType->value)
            ->where('subject_public_id', $subjectPublicId)
            ->where('purpose', $purpose->value)
            ->max('version_no') + 1;

        $policy = TimeZonePolicy::query()->create([
            'tenant_key' => $tenantKey,
            'scope_type' => $scopeType->value,
            'subject_public_id' => $subjectPublicId === '' ? 'PLATFORM' : $subjectPublicId,
            'purpose' => $purpose->value,
            'iana_id' => IanaTimezoneId::of($ianaId)->value(),
            'version_no' => max(1, $version),
            'status' => PolicyStatus::DRAFT->value,
            'effective_from' => $from,
            'effective_to' => $effectiveTo?->toCarbon(),
            'reason' => $reason,
            'created_by' => Auth::id(),
        ]);

        $this->audit($tenantKey, 'POLICY_DRAFT', $policy, null, $policy->toArray(), $reason);

        return $policy;
    }

    public function submitForReview(TimeZonePolicy $policy, ?string $reason = null): TimeZonePolicy
    {
        $before = $policy->toArray();
        $policy->update(['status' => PolicyStatus::UNDER_REVIEW->value]);
        $fresh = $policy->fresh();
        $this->audit($policy->tenant_key, 'POLICY_SUBMIT', $fresh, $before, $fresh->toArray(), $reason);

        return $fresh;
    }

    public function approve(TimeZonePolicy $policy, ?string $reason = null): TimeZonePolicy
    {
        $before = $policy->toArray();
        $policy->update([
            'status' => PolicyStatus::APPROVED->value,
            'approved_by' => Auth::id(),
            'approved_at' => $this->clock->now()->toCarbon(),
        ]);
        $fresh = $policy->fresh();
        $this->audit($policy->tenant_key, 'POLICY_APPROVE', $fresh, $before, $fresh->toArray(), $reason);

        return $fresh;
    }

    public function schedule(TimeZonePolicy $policy, ?string $reason = null): TimeZonePolicy
    {
        if (! in_array($policy->status, [PolicyStatus::APPROVED->value, PolicyStatus::DRAFT->value], true)) {
            throw new TimeEngineException('TIME_POLICY_STATE', 'Only approved or draft policies can be scheduled.');
        }
        $before = $policy->toArray();
        $policy->update(['status' => PolicyStatus::SCHEDULED->value]);
        $fresh = $policy->fresh();
        $this->audit($policy->tenant_key, 'POLICY_SCHEDULE', $fresh, $before, $fresh->toArray(), $reason);

        return $fresh;
    }

    public function reject(TimeZonePolicy $policy, ?string $reason = null): TimeZonePolicy
    {
        $before = $policy->toArray();
        $policy->update(['status' => PolicyStatus::REJECTED->value]);
        $fresh = $policy->fresh();
        $this->audit($policy->tenant_key, 'POLICY_REJECT', $fresh, $before, $fresh->toArray(), $reason);

        return $fresh;
    }

    public function activate(TimeZonePolicy $policy, ?string $reason = null): TimeZonePolicy
    {
        return DB::transaction(function () use ($policy, $reason) {
            $this->assertNoOverlap($policy);

            $before = $policy->toArray();

            TimeZonePolicy::query()
                ->where('tenant_key', $policy->tenant_key)
                ->where('scope_type', $policy->scope_type)
                ->where('subject_public_id', $policy->subject_public_id)
                ->where('purpose', $policy->purpose)
                ->where('status', PolicyStatus::ACTIVE->value)
                ->where('id', '!=', $policy->id)
                ->update(['status' => PolicyStatus::SUPERSEDED->value]);

            $policy->update([
                'status' => PolicyStatus::ACTIVE->value,
                'approved_by' => $policy->approved_by ?? Auth::id(),
                'approved_at' => $policy->approved_at ?? $this->clock->now()->toCarbon(),
            ]);

            $fresh = $policy->fresh();
            $this->audit($policy->tenant_key, 'POLICY_ACTIVATE', $fresh, $before, $fresh->toArray(), $reason);

            $correlationId = (string) \Illuminate\Support\Str::ulid();
            event(new \App\Domain\Time\Events\TimeZonePolicyActivated($fresh, $correlationId));

            if ($this->cache) {
                $this->cache->invalidateTenant($policy->tenant_key);
            } else {
                app(TimePolicyCacheService::class)->invalidateTenant($policy->tenant_key);
            }

            return $fresh;
        });
    }

    public function cancel(TimeZonePolicy $policy, ?string $reason = null): TimeZonePolicy
    {
        $before = $policy->toArray();
        $policy->update(['status' => PolicyStatus::CANCELLED->value]);
        $fresh = $policy->fresh();
        $this->audit($policy->tenant_key, 'POLICY_CANCEL', $fresh, $before, $fresh->toArray(), $reason);

        return $fresh;
    }

    private function assertNoOverlap(TimeZonePolicy $candidate): void
    {
        $from = $candidate->effective_from;
        $to = $candidate->effective_to;

        $overlapping = TimeZonePolicy::query()
            ->where('tenant_key', $candidate->tenant_key)
            ->where('scope_type', $candidate->scope_type)
            ->where('subject_public_id', $candidate->subject_public_id)
            ->where('purpose', $candidate->purpose)
            ->whereIn('status', [PolicyStatus::ACTIVE->value, PolicyStatus::APPROVED->value, PolicyStatus::SCHEDULED->value])
            ->where('id', '!=', $candidate->id)
            ->where(function ($q) use ($from, $to) {
                $q->where(function ($inner) use ($from, $to) {
                    $inner->where('effective_from', '<=', $to ?? '9999-12-31 23:59:59');
                    if ($to) {
                        $inner->where(function ($e) use ($from) {
                            $e->whereNull('effective_to')->orWhere('effective_to', '>=', $from);
                        });
                    } else {
                        $inner->where(function ($e) use ($from) {
                            $e->whereNull('effective_to')->orWhere('effective_to', '>=', $from);
                        });
                    }
                });
            })
            ->exists();

        if ($overlapping) {
            throw TimeEngineException::policyOverlap(
                "{$candidate->scope_type}:{$candidate->subject_public_id}:{$candidate->purpose}"
            );
        }
    }

    private function audit(
        string $tenantKey,
        string $action,
        TimeZonePolicy $policy,
        ?array $before,
        ?array $after,
        ?string $reason,
    ): void {
        TimeAudit::query()->create([
            'tenant_key' => $tenantKey,
            'actor_user_id' => Auth::id(),
            'action' => $action,
            'object_type' => 'core_time_zone_policies',
            'object_public_id' => $policy->public_id,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
        ]);
    }
}
