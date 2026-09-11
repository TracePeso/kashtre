<?php

namespace App\Support;

use App\Domain\Time\Enums\PolicyPurpose;
use App\Domain\Time\Services\SharedTimeGateway;
use App\Domain\Time\ValueObjects\LocalDate;
use App\Domain\Time\ValueObjects\TimeContext;
use App\Domain\Time\ValueObjects\UtcInstant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;

/**
 * Convenience accessors for consuming modules.
 */
final class SharedTime
{
    public static function enabled(): bool
    {
        return (bool) config('time.enabled', false);
    }

    public static function gateway(): SharedTimeGateway
    {
        return app(SharedTimeGateway::class);
    }

    public static function nowUtc(): CarbonImmutable
    {
        if (! self::enabled()) {
            return CarbonImmutable::now('UTC');
        }

        return self::gateway()->now()->toCarbon();
    }

    public static function now(): CarbonImmutable
    {
        return self::nowUtc();
    }

    public static function businessToday(?string $tenantId = null, ?string $branchId = null): string
    {
        $tenantId ??= Auth::user()->business_id ? (string) Auth::user()->business_id : null;
        if (! self::enabled() || ! $tenantId) {
            return CarbonImmutable::today()->toDateString();
        }

        return self::gateway()->businessDates()->businessDateFor(
            null,
            new TimeContext(tenantId: $tenantId, branchId: $branchId),
            $tenantId,
        )->toString();
    }

    public static function businessDate(?string $tenantId = null): LocalDate
    {
        $tenantId ??= Auth::user()->business_id ? (string) Auth::user()->business_id : 'SYSTEM';

        return self::gateway()->businessDate($tenantId);
    }

    public static function captureSnapshot(?string $tenantId = null, ?string $branchId = null): array
    {
        $tenantId ??= Auth::user()->business_id ? (string) Auth::user()->business_id : null;

        return self::gateway()->snapshots()->capture($tenantId, $branchId);
    }

    public static function presentForUser(UtcInstant|CarbonImmutable|string $instant, ?\App\Models\User $user = null): array
    {
        $user ??= Auth::user();
        $iana = $user?->presentation_timezone
            ?: (string) config('time.default_timezone', 'UTC');

        $utc = $instant instanceof UtcInstant
            ? $instant
            : ($instant instanceof CarbonImmutable
                ? UtcInstant::fromDateTime($instant->utc())
                : UtcInstant::fromString((string) $instant));

        return self::gateway()->present($utc, $iana);
    }

    public static function assertPeriodOpen(?string $tenantId = null, ?string $localDate = null): void
    {
        if (! self::enabled()) {
            return;
        }

        $tenantId ??= Auth::user()->business_id ? (string) Auth::user()->business_id : null;
        if (! $tenantId) {
            return;
        }

        $settings = \App\Domain\Time\Models\TimeTenantSetting::forTenant($tenantId);
        if (! $settings->enforce_financial_periods) {
            return;
        }

        $date = $localDate
            ? LocalDate::parse($localDate)
            : self::businessDate($tenantId);

        self::gateway()->periods()->assertOpenFor($tenantId, $date);
    }
}
