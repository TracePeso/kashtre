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



// HR Module Integration API
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
});

Route::prefix('v1')->group(function () {
    include_once __DIR__ . '/custom/airtel_routes.php';
    include_once __DIR__ . '/custom/mtn_routes.php';

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
