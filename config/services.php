<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'airtel' => [
        'client_id' => env('AIRTEL_CLIENT_ID'),
        'client_secret' => env('AIRTEL_CLIENT_SECRET'),
        'grant_type' => env('AIRTEL_GRANT_TYPE'),
        'x_key' => env('AIRTEL_X_KEY'),
        'x_signature' => env('AIRTEL_X_SIGNATURE'),
    ],

    'third_party' => [
        'api_url' => env('THIRD_PARTY_API_URL', 'https://vendor.kashtre.com'),
        'timeout' => env('THIRD_PARTY_API_TIMEOUT', 30),
    ],

    'calling_service' => [
        'url'         => env('CALLING_SERVICE_URL', 'http://127.0.0.1:8001'),
        'sync_secret' => env('CALLING_SERVICE_SYNC_SECRET', ''),
    ],

    'hr_module' => [
        'api_key' => env('HR_MODULE_API_KEY'),
    ],

    'kashtre' => [
        'synced_user_password' => env('KASHTRE_SYNCED_USER_PASSWORD', 'password'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY', ''),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'base_url' => env('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'verify_ssl' => filter_var(env('GEMINI_VERIFY_SSL', true), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
        'connect_timeout' => (int) env('GEMINI_CONNECT_TIMEOUT', 10),
        'timeout' => (int) env('GEMINI_TIMEOUT', 120),
        'roster_stale_after_seconds' => (int) env('GEMINI_ROSTER_STALE_AFTER_SECONDS', 7500),
        'roster_candidate_count' => (int) env('GEMINI_ROSTER_CANDIDATE_COUNT', 2),
        'roster_max_output_tokens' => (int) env('GEMINI_ROSTER_MAX_OUTPUT_TOKENS', 8192),
        'roster_max_workdays_per_request' => (int) env('GEMINI_ROSTER_MAX_WORKDAYS_PER_REQUEST', 7),
    ],

];
