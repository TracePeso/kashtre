<?php

namespace App\Domain\Time\Services;

use App\Domain\Time\Contracts\Clock;
use App\Domain\Time\Enums\PolicyPurpose;
use App\Domain\Time\Enums\PolicyScopeType;
use App\Domain\Time\Enums\PolicyStatus;
use App\Domain\Time\Models\TimeZonePolicy;
use App\Domain\Time\ValueObjects\IanaTimezoneId;
use App\Domain\Time\ValueObjects\ResolvedTimezone;
use App\Domain\Time\ValueObjects\TimeContext;
use App\Domain\Time\ValueObjects\TimezoneSnapshot;
use App\Domain\Time\ValueObjects\UtcInstant;
use Carbon\CarbonImmutable;

final class TimeZoneResolutionService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly TimeZoneCatalogueService $catalogue,
    ) {}

    public function resolve(TimeContext $context, ?string $tenantKey = null): ResolvedTimezone
    {
        $asOf = $context->asOf ?? $this->clock->now();
        $tenantKey ??= $context->tenantId ?? 'SYSTEM';
        $path = [];

        foreach ($context->scopeCandidates() as [$scopeType, $scopeId]) {
            $subject = $scopeId ?? 'PLATFORM';
            $path[] = $scopeType->value.':'.$subject;

            $policy = $this->findActivePolicy(
                $tenantKey,
                $scopeType,
                $subject,
                $context->purpose,
                $asOf,
            );

            // PLATFORM policies may also live under SYSTEM tenant.
            if (! $policy && $scopeType === PolicyScopeType::PLATFORM && $tenantKey !== 'SYSTEM') {
                $policy = $this->findActivePolicy('SYSTEM', $scopeType, 'PLATFORM', $context->purpose, $asOf);
            }

            if ($policy) {
                $iana = $this->catalogue->resolveCanonical($policy->iana_id);

                return new ResolvedTimezone(
                    ianaId: $iana,
                    policyId: $policy->public_id,
                    scopeType: $scopeType,
                    scopeId: $scopeId,
                    purpose: $context->purpose,
                    resolutionPath: implode(' > ', $path),
                    resolvedAt: $asOf,
                    usedFallback: false,
                );
            }
        }

        $fallback = (string) config('time.platform_fallback_timezone', 'UTC');
        $iana = $this->catalogue->resolveCanonical($fallback);
        $path[] = 'FALLBACK:'.$fallback;

        return new ResolvedTimezone(
            ianaId: $iana,
            policyId: null,
            scopeType: PolicyScopeType::PLATFORM,
            scopeId: null,
            purpose: $context->purpose,
            resolutionPath: implode(' > ', $path),
            resolvedAt: $asOf,
            usedFallback: true,
        );
    }

    public function snapshot(IanaTimezoneId|string $ianaId, ?UtcInstant $asOf = null): TimezoneSnapshot
    {
        $id = $ianaId instanceof IanaTimezoneId ? $ianaId : $this->catalogue->resolveCanonical($ianaId);
        $instant = $asOf ?? $this->clock->now();
        $local = $instant->toCarbon()->setTimezone($id->toDateTimeZone());

        return new TimezoneSnapshot(
            ianaId: $id,
            asOf: $instant,
            utcOffsetSeconds: $local->offset,
            isDst: (bool) $local->dst,
            abbreviation: $local->format('T'),
            tzdbVersion: $this->catalogue->tzdbRelease(),
        );
    }

    private function findActivePolicy(
        string $tenantKey,
        PolicyScopeType $scopeType,
        string $subject,
        PolicyPurpose $purpose,
        UtcInstant $asOf,
    ): ?TimeZonePolicy {
        $at = $asOf->toCarbon();

        return TimeZonePolicy::query()
            ->where('tenant_key', $tenantKey)
            ->where('scope_type', $scopeType->value)
            ->where('subject_public_id', $subject)
            ->where('purpose', $purpose->value)
            ->where('status', PolicyStatus::ACTIVE->value)
            ->where('effective_from', '<=', $at)
            ->where(function ($q) use ($at) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>', $at);
            })
            ->orderByDesc('version_no')
            ->first();
    }

    public function present(UtcInstant $instant, IanaTimezoneId|string $ianaId): array
    {
        $id = $ianaId instanceof IanaTimezoneId ? $ianaId : IanaTimezoneId::of($ianaId);
        $local = $instant->toCarbon()->setTimezone($id->toDateTimeZone());

        return [
            'utc' => $instant->toIso8601(),
            'local' => $local->format('Y-m-d\TH:i:s.u'),
            'ianaId' => $id->value(),
            'offsetSeconds' => $local->offset,
            'abbreviation' => $local->format('T'),
            'isDst' => (bool) $local->dst,
        ];
    }
}
