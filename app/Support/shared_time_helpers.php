<?php

use App\Support\SharedTime;

if (! function_exists('shared_now')) {
    function shared_now(): \Carbon\CarbonImmutable
    {
        return SharedTime::now();
    }
}

if (! function_exists('shared_business_today')) {
    function shared_business_today(?string $tenantId = null, ?string $branchId = null): string
    {
        return SharedTime::businessToday($tenantId, $branchId);
    }
}

if (! function_exists('business_today')) {
    function business_today(?string $tenantId = null, ?string $branchId = null): string
    {
        return SharedTime::businessToday($tenantId, $branchId);
    }
}

if (! function_exists('operational_tz')) {
    function operational_tz(?string $tenantId = null, ?string $branchId = null): string
    {
        return SharedTime::displayTimezone($tenantId, $branchId);
    }
}

if (! function_exists('local_time')) {
    function local_time(mixed $instant, ?string $tenantId = null, ?string $branchId = null, string $format = 'M j, Y H:i:s'): string
    {
        return SharedTime::formatLocal($instant, $tenantId, $branchId, $format);
    }
}

if (! function_exists('shared_time_snapshot')) {
    function shared_time_snapshot(?string $tenantId = null): array
    {
        return SharedTime::captureSnapshot($tenantId);
    }
}
