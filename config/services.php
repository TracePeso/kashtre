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
        // Prefer Settings → HR Module Settings (superadmin UI). Env values seed defaults only.
        'api_key' => env('HR_MODULE_API_KEY'),
        'url' => rtrim((string) env('HR_MODULE_URL', ''), '/'),
        'sync_enabled' => (bool) env('HR_MODULE_SYNC_ENABLED', true),
    ],

    'clinical_module' => [
        // Prefer Settings → Clinical Module Settings (superadmin UI). Env seeds defaults only.
        'url' => rtrim((string) env('CLINICAL_MODULE_URL', ''), '/'),
        'service_key' => env('CLINICAL_MODULE_SERVICE_KEY'),
        'inbound_api_key' => env('CLINICAL_MODULE_INBOUND_API_KEY'),
        'encounter_webhook_enabled' => (bool) env('CLINICAL_MODULE_ENCOUNTER_WEBHOOK_ENABLED', true),
    ],

    'imaging_module' => [
        'api_key' => env('IMAGING_MODULE_API_KEY'),
    ],

    'vendor' => [
        'api_url' => env('VENDOR_API_URL', 'http://localhost:8001'),
    ],

    // Pillars 1.1/7/8: Orthanc (PACS + DICOM Modality Worklist broker).
    // OrthancDicomWorklistBroker / OrthancPacsClient talk to this; unset
    // ORTHANC_URL falls back to LoggingDicomWorklistBroker/StubPacsClient
    // only if AppServiceProvider's bindings are reverted to the stubs.
    'orthanc' => [
        'url' => env('ORTHANC_URL', 'http://127.0.0.1:8042'),
        'username' => env('ORTHANC_USERNAME'),
        'password' => env('ORTHANC_PASSWORD'),
        'uid_root' => env('DICOM_UID_ROOT', '2.25'),
        'webhook_secret' => env('ORTHANC_WEBHOOK_SECRET'),
    ],

    // Clinical Module Chunk 0: ModuleDispatcher transport. 'local' resolves
    // receivers in-process via LocalFactReceiverRegistry (today's reality —
    // Imaging/Inventory live in this same app). 'http' posts the identical
    // fact payload to the target module's base URL instead, for whenever a
    // module is split onto its own server — no caller of ModuleDispatcher
    // has to change, only this driver + the module_endpoints below.
    'dispatch' => [
        'driver' => env('DISPATCH_DRIVER', 'local'),
    ],

    // Clinical Module Chunk 9: ZTNA on-premises detection. Comma-separated
    // CIDR subnets; defaults cover typical private-network hospital LANs
    // plus localhost for local dev — override per deployment.
    'ztna' => [
        'hospital_subnets' => array_filter(explode(',', env('ZTNA_HOSPITAL_SUBNETS', '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,127.0.0.1/32'))),
    ],

    // Clinical Module Chunk 8: Shared AI Services Gateway. A genuinely
    // separate, external service (STT + Azure OpenAI with ZDR behind it)
    // that doesn't exist yet — left unconfigured (empty url) by default,
    // which AiGatewayClientService::isAvailable() treats as "gracefully
    // unavailable," not an error. LIMS/RIS use the identical contract
    // with their own X-Module-Code.
    'ai_gateway' => [
        'url' => env('AI_GATEWAY_URL'),
        'api_key' => env('AI_GATEWAY_API_KEY'),
        'module_code' => 'CLINICAL_ORCHESTRATOR',
    ],

    // Clinical Module split (API Integration Guide v1): the Clinical Module
    // is moving out of this app into its own CLINICAL_ORCHESTRATOR service.
    // 'driver' selects which implementation the ClinicalGatewayServiceProvider
    // binds behind every App\Contracts\Clinical\* gateway:
    //
    //   local — today's in-process engines + clinical_* tables (default)
    //   api   — HTTP calls to {url}/api/v1/... per the integration guide
    //
    // Flip to api per-environment once the service is reachable; no caller
    // of a gateway interface changes. See §2, §3, §4 and §15 of the guide.
    'clinical' => [
        'driver' => env('CLINICAL_DRIVER', 'local'),
        'url' => env('CLINICAL_MODULE_URL'),
        'service_key' => env('CLINICAL_SERVICE_KEY'),
        'timeout' => (int) env('CLINICAL_TIMEOUT', 10),
        'retry_times' => (int) env('CLINICAL_RETRY_TIMES', 2),
        'retry_sleep_ms' => (int) env('CLINICAL_RETRY_SLEEP_MS', 250),

        // §4: falls back to the DEFAULT tenant when a business has no
        // explicit mapping — correct only for single-facility deployments.
        'default_tenant' => env('CLINICAL_DEFAULT_TENANT', 'DEFAULT'),

        // §3.2: identity transport. 'headers' is the interim X-User-* form
        // that works today; 'jwt' switches to a Main-issued RS256 token.
        // Once the Clinical service sets IDENTITY_JWT_REQUIRED=true, headers
        // are refused with 401 IDENTITY_TOKEN_REQUIRED — cut over together.
        'identity_transport' => env('CLINICAL_IDENTITY_TRANSPORT', 'headers'),

        // Shared secret for the events Clinical POSTs back to us (§12) and
        // for the catalogue lookup it calls (§14). Comma-separated so keys
        // can be rotated without downtime.
        'inbound_keys' => array_filter(array_map('trim', explode(',', (string) env('CLINICAL_INBOUND_SERVICE_KEYS', '')))),
    ],

    // Per-module base URL + shared secret, only consulted by
    // HttpModuleDispatcher (DISPATCH_DRIVER=http). Empty/local today.
    'module_endpoints' => [
        'imaging' => [
            'url' => env('IMAGING_MODULE_URL'),
            'api_key' => env('IMAGING_MODULE_API_KEY'),
        ],
        'inventory' => [
            'url' => env('INVENTORY_MODULE_URL'),
            'api_key' => env('INVENTORY_MODULE_API_KEY'),
        ],
        'lims' => [
            'url' => env('LIMS_MODULE_URL'),
            'secret' => env('LIMS_MODULE_SECRET'),
        ],
    ],

];
