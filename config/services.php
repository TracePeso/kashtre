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

];
