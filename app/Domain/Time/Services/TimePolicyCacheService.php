<?php

namespace App\Domain\Time\Services;

use App\Domain\Time\Enums\PolicyPurpose;
use App\Domain\Time\ValueObjects\ResolvedTimezone;
use App\Domain\Time\ValueObjects\TimeContext;
use Illuminate\Support\Facades\Cache;

/**
 * Read-through cache for resolved timezone policies (TIME-RES-002..010).
 */
final class TimePolicyCacheService
{
    public function __construct(private readonly TimeZoneResolutionService $resolution) {}

    public function resolve(TimeContext $context, ?string $tenantKey = null): ResolvedTimezone
    {
        $tenantKey ??= $context->tenantId ?? 'SYSTEM';
        $ttl = (int) config('time.cache.ttl_seconds', 300);
        if ($ttl <= 0) {
            return $this->resolution->resolve($context, $tenantKey);
        }

        $key = $this->key($tenantKey, $context);
        $cached = Cache::get($key);
        if (is_array($cached) && isset($cached['ianaId'], $cached['retrieved_at'])) {
            $maxAge = (int) config('time.cache.max_stale_seconds', 3600);
            $age = time() - (int) $cached['retrieved_at'];
            if ($age <= $ttl || ($age <= $maxAge && (bool) config('time.cache.allow_stale_on_outage', false))) {
                return new ResolvedTimezone(
                    ianaId: \App\Domain\Time\ValueObjects\IanaTimezoneId::of($cached['ianaId']),
                    policyId: $cached['policyId'] ?? null,
                    scopeType: \App\Domain\Time\Enums\PolicyScopeType::from($cached['scopeType']),
                    scopeId: $cached['scopeId'] ?? null,
                    purpose: PolicyPurpose::from($cached['purpose']),
                    resolutionPath: ($cached['resolutionPath'] ?? 'CACHE').($age > $ttl ? ' (STALE)' : ''),
                    resolvedAt: \App\Domain\Time\ValueObjects\UtcInstant::now(),
                    usedFallback: (bool) ($cached['usedFallback'] ?? false),
                );
            }
        }

        $resolved = $this->resolution->resolve($context, $tenantKey);
        Cache::put($key, array_merge($resolved->toArray(), ['retrieved_at' => time()]), $ttl);

        return $resolved;
    }

    public function invalidateTenant(string $tenantKey): void
    {
        // Pattern delete is not portable on all cache drivers; track known purpose keys.
        foreach (PolicyPurpose::cases() as $purpose) {
            Cache::forget("time:policy:{$tenantKey}:{$purpose->value}");
        }
    }

    private function key(string $tenantKey, TimeContext $context): string
    {
        return 'time:policy:'.$tenantKey.':'.$context->purpose->value.':'
            .($context->branchId ?? '').':'.($context->facilityId ?? '').':'
            .($context->userId ?? '').':'.($context->deviceId ?? '');
    }
}
