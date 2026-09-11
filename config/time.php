<?php

return [
    /*
    | Shared Time, Calendar and Period Engine (Main Module).
    | Built for Kashtre — not a pick-and-drop of external package code.
    */
    'enabled' => (bool) env('SHARED_TIME_ENABLED', false),

    'default_timezone' => env('SHARED_TIME_DEFAULT_TIMEZONE', 'UTC'),

    'platform_fallback_timezone' => env('SHARED_TIME_PLATFORM_FALLBACK', 'UTC'),

    'clock' => [
        'warning_drift_seconds' => (int) env('SHARED_TIME_CLOCK_WARNING', 2),
        'degraded_drift_seconds' => (int) env('SHARED_TIME_CLOCK_DEGRADED', 5),
        'unhealthy_drift_seconds' => (int) env('SHARED_TIME_CLOCK_UNHEALTHY', 30),
    ],

    'day_rollover' => [
        'offset_minutes' => (int) env('SHARED_TIME_DAY_ROLLOVER_OFFSET', 0),
    ],

    'leap_second' => [
        // REJECT | NORMALIZE | QUARANTINE
        'policy' => env('SHARED_TIME_LEAP_SECOND_POLICY', 'REJECT'),
        'infrastructure' => env('SHARED_TIME_CLOCK_SMEARING', 'smear'), // smear|step
    ],

    'cache' => [
        'ttl_seconds' => (int) env('SHARED_TIME_CACHE_TTL', 300),
        'max_stale_seconds' => (int) env('SHARED_TIME_CACHE_MAX_STALE', 3600),
        'allow_stale_on_outage' => (bool) env('SHARED_TIME_CACHE_ALLOW_STALE', false),
    ],

    'api_version' => '1',
];
