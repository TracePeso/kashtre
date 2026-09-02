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

    'clinical_module' => [
        // Prefer Settings → Clinical Module Settings (superadmin UI). Env seeds defaults only.
        'url' => rtrim((string) env('CLINICAL_MODULE_URL', ''), '/'),
        'service_key' => env('CLINICAL_MODULE_SERVICE_KEY'),
        'inbound_api_key' => env('CLINICAL_MODULE_INBOUND_API_KEY'),
        'encounter_webhook_enabled' => (bool) env('CLINICAL_MODULE_ENCOUNTER_WEBHOOK_ENABLED', true),
        // When Clinical outbound is not configured, allow a fixed 5-digit bypass for EndStore release.
        'handoff_bypass_enabled' => (bool) env('INVENTORY_HANDOFF_BYPASS_ENABLED', true),
        'handoff_bypass_code' => env('INVENTORY_HANDOFF_BYPASS_CODE', '00000'),
    ],

    'vendor' => [
        'api_url' => env('VENDOR_API_URL', 'http://localhost:8001'),
    ],

    'hr_module' => [
        'url' => rtrim((string) env('HR_MODULE_URL', ''), '/'),
        'api_key' => env('HR_MODULE_API_KEY', ''),
        'sync_enabled' => (bool) env('HR_MODULE_SYNC_ENABLED', true),
    ],

];
