<?php

use App\Http\Controllers\EmergencyController;
use App\Http\Controllers\API\DisplayBoardController;
use Illuminate\Routing\Middleware\ThrottleRequests;

// Queue display board — migrated to standalone Calling Service
// API endpoints for TV are no longer served from the Kashtre monolith

Route::withoutMiddleware([ThrottleRequests::class])
    ->middleware('throttle:240,1')
    ->group(function () {
        // Public token-authenticated endpoint for the display board to get emergency color
        Route::get('/display/emergency-status', [EmergencyController::class, 'displayEmergencyStatus']);
        Route::get('/display/latest-calls', [DisplayBoardController::class, 'latestCalls']);
        Route::get('/display/audio', [DisplayBoardController::class, 'streamAudio']);
        Route::get('/display/emergency-audio', [DisplayBoardController::class, 'streamEmergencyAudio']);
        Route::get('/display/announcement-audio', [DisplayBoardController::class, 'streamAnnouncementAudio']);
        Route::options('/display/latest-calls', fn () => response()->noContent()->withHeaders([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
        ]));
        Route::options('/display/audio', fn () => response()->noContent()->withHeaders([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
        ]));
        Route::options('/display/emergency-audio', fn () => response()->noContent()->withHeaders([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
        ]));
        Route::options('/display/announcement-audio', fn () => response()->noContent()->withHeaders([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
        ]));

        // Public token-authenticated endpoint for the display board to get PA config (sections + Reverb details)
        Route::get('/display/pa-config', [\App\Http\Controllers\PaAnnouncementController::class, 'displayPaConfig']);
        Route::get('/display/pa-stream', [\App\Http\Controllers\PaAnnouncementController::class, 'displayPaStream']);
        Route::post('/display/pa-signal', [\App\Http\Controllers\PaAnnouncementController::class, 'displaySignal']);
        Route::options('/display/pa-config', fn () => response()->noContent()->withHeaders([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
        ]));
        Route::options('/display/pa-stream', fn () => response()->noContent()->withHeaders([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
        ]));
        Route::options('/display/pa-signal', fn () => response()->noContent()->withHeaders([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
        ]));
    });

// Public API routes for client registration and open enrollment checks (no auth required)
Route::get('/insurance-company/by-code/{code}', [\App\Http\Controllers\ClientController::class, 'getInsuranceCompanyByCode'])->name('api.insurance-company.by-code');
Route::get('/insurance-settings/{insuranceCompanyId}', [\App\Http\Controllers\ClientController::class, 'getInsuranceCompanySettingsApi'])->name('api.insurance-settings');
Route::get('/policies/verify/{insuranceCompanyId}/{policyNumber}', [\App\Http\Controllers\ClientController::class, 'verifyPolicyNumber'])->name('api.policies.verify');
Route::post('/policies/verify/{insuranceCompanyId}', [\App\Http\Controllers\ClientController::class, 'verifyPolicyNumber'])->name('api.policies.verify.post');



// Orthanc PACS integration — Lua OnStableStudy callback (pacs integration
// files/stable-study.lua). Gated by the shared secret inside the
// controller, not route middleware — Orthanc and Laravel share localhost
// in this environment.
Route::post('/orthanc/stable-study', [\App\Http\Controllers\OrthancWebhookController::class, 'stableStudy'])
    ->middleware('throttle:120,1');

// Clinical Module Integration API (X-Service-Key or X-API-Key)
Route::middleware('clinical.api')->group(function () {
    Route::get('/catalogue/items', [\App\Http\Controllers\API\ClinicalIntegrationController::class, 'catalogueItems']);
    Route::get('/clients/{id}', [\App\Http\Controllers\API\ClinicalIntegrationController::class, 'clientShow']);
    Route::get('/queues', [\App\Http\Controllers\API\ClinicalIntegrationController::class, 'queues']);
    Route::post('/events', [\App\Http\Controllers\API\ClinicalIntegrationController::class, 'events']);
});

// HR Module Integration API (X-API-Key or X-HR-API-Key)
Route::middleware('hr.api')->group(function () {
    Route::get('/businesses', [\App\Http\Controllers\API\HrIntegrationController::class, 'businesses']);
    Route::get('/branches', [\App\Http\Controllers\API\HrIntegrationController::class, 'branches']);
    Route::get('/departments', [\App\Http\Controllers\API\HrIntegrationController::class, 'departments']);
    Route::get('/qualifications', [\App\Http\Controllers\API\HrIntegrationController::class, 'qualifications']);
    Route::get('/staff-categories', [\App\Http\Controllers\API\HrIntegrationController::class, 'staffCategories']);
    Route::get('/client-spaces', [\App\Http\Controllers\API\HrIntegrationController::class, 'clientSpaces']);
    Route::get('/users', [\App\Http\Controllers\API\HrIntegrationController::class, 'users']);
    Route::get('/users/{uuid}', [\App\Http\Controllers\API\HrIntegrationController::class, 'userShow']);
});

// Backwards-compatible HR aliases under /api/hr/*
Route::prefix('hr')->middleware('hr.api')->group(function () {
    Route::get('/staff', [\App\Http\Controllers\API\HrIntegrationController::class, 'staff']);
    Route::get('/staff/{uuid}', [\App\Http\Controllers\API\HrIntegrationController::class, 'staffShow']);
    Route::get('/businesses', [\App\Http\Controllers\API\HrIntegrationController::class, 'businesses']);
    Route::get('/kashtre-entities', [\App\Http\Controllers\API\KashtreEntityController::class, 'index']);
    Route::get('/kashtre-entities/{uuid}', [\App\Http\Controllers\API\KashtreEntityController::class, 'show']);
    Route::get('/branches', [\App\Http\Controllers\API\HrIntegrationController::class, 'branches']);
    Route::get('/departments', [\App\Http\Controllers\API\HrIntegrationController::class, 'departments']);
    Route::get('/qualifications', [\App\Http\Controllers\API\HrIntegrationController::class, 'qualifications']);
    Route::get('/staff-categories', [\App\Http\Controllers\API\HrIntegrationController::class, 'staffCategories']);
    Route::get('/client-spaces', [\App\Http\Controllers\API\HrIntegrationController::class, 'clientSpaces']);
    
    Route::get('/users', [\App\Http\Controllers\API\HrIntegrationController::class, 'users']);
    Route::get('/users/{uuid}', [\App\Http\Controllers\API\HrIntegrationController::class, 'userShow']);
});

// RIS Amendment v2.6, Chunk 8 — Imaging Workflow Engine Integration API,
// for the eventual Clinical Module (same shared-secret idiom as the HR
// group above). Every endpoint is a thin wrapper over Chunks 1-6's
// services/models — no logic lives here that doesn't already exist for
// the web UI.
Route::prefix('v1/imaging')->middleware('imaging.api')->group(function () {
    Route::get('/workflow-steps', [\App\Http\Controllers\API\Imaging\WorkflowStepController::class, 'index']);
    Route::get('/workflow-steps/{workflowStep}/users', [\App\Http\Controllers\API\Imaging\WorkflowStepController::class, 'users']);
    Route::get('/workflow-steps/{workflowStep}/queue', [\App\Http\Controllers\API\Imaging\WorkflowStepController::class, 'queue']);
    Route::get('/protocol-workflows', [\App\Http\Controllers\API\Imaging\ProtocolWorkflowController::class, 'index']);
    Route::post('/studies/{study}/claim', [\App\Http\Controllers\API\Imaging\StudyController::class, 'claim']);
    Route::post('/studies/{study}/complete-step', [\App\Http\Controllers\API\Imaging\StudyController::class, 'completeStep']);
    Route::get('/consumption-exceptions', [\App\Http\Controllers\API\Imaging\ConsumptionExceptionController::class, 'index']);
    Route::post('/consumption-exceptions/{consumptionException}/resolve', [\App\Http\Controllers\API\Imaging\ConsumptionExceptionController::class, 'resolve']);
});

// RIS Amendment v2.6, Chunk 8 — Imaging Workflow Engine Integration API,
// for the eventual Clinical Module (same shared-secret idiom as the HR
// group above). Every endpoint is a thin wrapper over Chunks 1-6's
// services/models — no logic lives here that doesn't already exist for
// the web UI.
Route::prefix('v1/imaging')->middleware('imaging.api')->group(function () {
    Route::get('/workflow-steps', [\App\Http\Controllers\API\Imaging\WorkflowStepController::class, 'index']);
    Route::get('/workflow-steps/{workflowStep}/users', [\App\Http\Controllers\API\Imaging\WorkflowStepController::class, 'users']);
    Route::get('/workflow-steps/{workflowStep}/queue', [\App\Http\Controllers\API\Imaging\WorkflowStepController::class, 'queue']);
    Route::get('/protocol-workflows', [\App\Http\Controllers\API\Imaging\ProtocolWorkflowController::class, 'index']);
    Route::post('/studies/{study}/claim', [\App\Http\Controllers\API\Imaging\StudyController::class, 'claim']);
    Route::post('/studies/{study}/complete-step', [\App\Http\Controllers\API\Imaging\StudyController::class, 'completeStep']);
    Route::get('/consumption-exceptions', [\App\Http\Controllers\API\Imaging\ConsumptionExceptionController::class, 'index']);
    Route::post('/consumption-exceptions/{consumptionException}/resolve', [\App\Http\Controllers\API\Imaging\ConsumptionExceptionController::class, 'resolve']);
});

Route::prefix('v1')->group(function () {
    include_once __DIR__ . '/custom/airtel_routes.php';
    include_once __DIR__ . '/custom/mtn_routes.php';

    // Auth (Sanctum token) — same email/password as web login
    Route::post('/auth/login', [\App\Http\Controllers\API\AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [\App\Http\Controllers\API\AuthController::class, 'logout']);
        Route::get('/auth/me', [\App\Http\Controllers\API\AuthController::class, 'me']);

        Route::get('/users', [\App\Http\Controllers\API\UserController::class, 'index']);
        Route::get('/users/{uuid}', [\App\Http\Controllers\API\UserController::class, 'show']);

        Route::get('/items', [\App\Http\Controllers\API\ItemController::class, 'list']);
        Route::get('/items/{uuid}', [\App\Http\Controllers\API\ItemController::class, 'show']);

        Route::get('/businesses', [\App\Http\Controllers\API\BusinessController::class, 'index']);
        Route::get('/businesses/{uuid}', [\App\Http\Controllers\API\BusinessController::class, 'show']);
        Route::get('/businesses/{business}/branches', [\App\Http\Controllers\API\BusinessController::class, 'branches']);

        Route::get('/branches', [\App\Http\Controllers\API\BranchController::class, 'index']);
        Route::get('/branches/{uuid}', [\App\Http\Controllers\API\BranchController::class, 'show']);
    });

    // Invoice API routes for third-party vendors
    Route::get('/invoices/insurance-company/{insuranceCompanyId}', [\App\Http\Controllers\API\InvoiceController::class, 'getInvoicesForInsuranceCompany']);
    Route::post('/invoices/{invoiceId}/mark-paid', [\App\Http\Controllers\API\InvoiceController::class, 'markInvoiceAsPaid']);
    Route::get('/invoices/{invoiceId}/details', [\App\Http\Controllers\API\InvoiceController::class, 'getInvoiceDetails']);
    // Items per business (for insurer portal)
    Route::get('/businesses/{businessId}/items', [\App\Http\Controllers\API\ItemController::class, 'index']);

    // Third-party payer service exclusions (for insurer portal)
    Route::get('/businesses/{businessId}/third-party-payers/{insuranceCompanyId}/excluded-items', [\App\Http\Controllers\API\ThirdPartyPayerController::class, 'getExcludedItems']);

    // Third-party vendor service charge tiers (clinic + per-vendor; includes recommended defaults)
    Route::get('/businesses/{businessId}/third-party-vendor-service-charges/recommended-defaults', [\App\Http\Controllers\API\ThirdPartyVendorServiceChargeController::class, 'recommendedDefaults']);
    Route::post('/businesses/{businessId}/third-party-vendor-service-charges/calculate', [\App\Http\Controllers\API\ThirdPartyVendorServiceChargeController::class, 'calculate']);
    Route::get('/businesses/{businessId}/third-party-vendors/{thirdPartyVendorId}/service-charges', [\App\Http\Controllers\API\ThirdPartyVendorServiceChargeController::class, 'forVendor']);
    Route::get('/businesses/{businessId}/third-party-vendor-service-charges', [\App\Http\Controllers\API\ThirdPartyVendorServiceChargeController::class, 'index']);

    // Insurer portal: mirror Kashtre third-party vendor financial view (ledger, invoices, exclusions)
    Route::get('/businesses/{businessId}/third-party-vendors/{thirdPartyVendorId}/insurer-portal-summary', [\App\Http\Controllers\API\InsurerPortalVendorController::class, 'summary']);
    Route::get('/businesses/{businessId}/third-party-vendors/{thirdPartyVendorId}/insurer-portal-balance-history', [\App\Http\Controllers\API\InsurerPortalVendorController::class, 'balanceHistory']);
    Route::post('/businesses/{businessId}/third-party-vendors/{thirdPartyVendorId}/insurer-portal-payment/preview', [\App\Http\Controllers\API\InsurerPortalVendorController::class, 'previewPayment']);
    Route::post('/businesses/{businessId}/third-party-vendors/{thirdPartyVendorId}/insurer-portal-payment', [\App\Http\Controllers\API\InsurerPortalVendorController::class, 'recordPayment']);
    // Client deductible tracking
    Route::get('/clients/{client}/deductible-used', [\App\Http\Controllers\API\ClientController::class, 'getDeductibleUsed']);

    // Client co-pay status tracking
    Route::get('/clients/{client}/copay-status', [\App\Http\Controllers\API\ClientController::class, 'getCopayPaidStatus']);

    // Insurance company settings
    Route::get('/insurance-companies/{insuranceCompanyId}/settings', [\App\Http\Controllers\API\ClientController::class, 'getInsuranceCompanySettings']);

    // Callback from third-party insurer after authorization decision (approve/reject)
    Route::post('/insurance/authorization-decision', [\App\Http\Controllers\API\InvoiceController::class, 'receiveAuthorizationDecision']);
});
