<?php

use App\Support\SharedTime;

if (! function_exists('shared_now')) {
    function shared_now(): \Carbon\CarbonImmutable
    {
        return SharedTime::now();
    }
}

if (! function_exists('shared_business_today')) {
    function shared_business_today(?string $tenantId = null): string
    {
        return SharedTime::businessToday($tenantId);
    }
}

if (! function_exists('shared_time_snapshot')) {
    function shared_time_snapshot(?string $tenantId = null): array
    {
        return SharedTime::captureSnapshot($tenantId);
    }
}
