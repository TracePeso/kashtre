<?php

use Illuminate\Support\Facades\Route;

// Public API routes for client registration and open enrollment checks (no auth required)
Route::get('/insurance-company/by-code/{code}', [\App\Http\Controllers\ClientController::class, 'getInsuranceCompanyByCode'])->name('api.insurance-company.by-code');
Route::get('/insurance-settings/{insuranceCompanyId}', [\App\Http\Controllers\ClientController::class, 'getInsuranceCompanySettingsApi'])->name('api.insurance-settings');
Route::get('/policies/verify/{insuranceCompanyId}/{policyNumber}', [\App\Http\Controllers\ClientController::class, 'verifyPolicyNumber'])->name('api.policies.verify');
Route::post('/policies/verify/{insuranceCompanyId}', [\App\Http\Controllers\ClientController::class, 'verifyPolicyNumber'])->name('api.policies.verify.post');



// Clinical Module Integration API (X-Service-Key or X-API-Key)
Route::middleware('clinical.api')->group(function () {
    Route::get('/catalogue/items', [\App\Http\Controllers\API\ClinicalIntegrationController::class, 'catalogueItems']);
    Route::get('/clients/{id}', [\App\Http\Controllers\API\ClinicalIntegrationController::class, 'clientShow']);
    Route::get('/queues', [\App\Http\Controllers\API\ClinicalIntegrationController::class, 'queues']);
    Route::post('/events', [\App\Http\Controllers\API\ClinicalIntegrationController::class, 'events']);
    Route::get('/pharmacy/totes/{ref}', [\App\Http\Controllers\API\ClinicalIntegrationController::class, 'toteShow']);
});

// HR Module Integration API (X-API-Key or X-HR-API-Key)
Route::middleware('hr.api')->group(function () {
    Route::get('/businesses', [\App\Http\Controllers\API\HrIntegrationController::class, 'businesses']);
    Route::get('/facilities', [\App\Http\Controllers\API\HrIntegrationController::class, 'facilities']);
    Route::get('/branches', [\App\Http\Controllers\API\HrIntegrationController::class, 'branches']);
    Route::get('/departments', [\App\Http\Controllers\API\HrIntegrationController::class, 'departments']);
    Route::get('/titles', [\App\Http\Controllers\API\HrIntegrationController::class, 'titles']);
    Route::get('/official-titles', [\App\Http\Controllers\API\HrIntegrationController::class, 'officialTitles']);
    Route::get('/qualifications', [\App\Http\Controllers\API\HrIntegrationController::class, 'qualifications']);
    Route::get('/staff-categories', [\App\Http\Controllers\API\HrIntegrationController::class, 'staffCategories']);
    Route::get('/cadres', [\App\Http\Controllers\API\HrIntegrationController::class, 'cadres']);
    Route::get('/designations', [\App\Http\Controllers\API\HrIntegrationController::class, 'designations']);
    Route::get('/client-spaces', [\App\Http\Controllers\API\HrIntegrationController::class, 'clientSpaces']);
    Route::get('/users', [\App\Http\Controllers\API\HrIntegrationController::class, 'users']);
    Route::get('/users/{uuid}', [\App\Http\Controllers\API\HrIntegrationController::class, 'userShow']);
    Route::get('/employee-identities', [\App\Http\Controllers\API\HrIntegrationController::class, 'employeeIdentities']);
    Route::get('/employee-identities/{uuid}', [\App\Http\Controllers\API\HrIntegrationController::class, 'employeeIdentityShow']);
});

// Backwards-compatible HR aliases under /api/hr/*
Route::prefix('hr')->middleware('hr.api')->group(function () {
    Route::get('/staff', [\App\Http\Controllers\API\HrIntegrationController::class, 'staff']);
    Route::get('/staff/{uuid}', [\App\Http\Controllers\API\HrIntegrationController::class, 'staffShow']);
    Route::get('/businesses', [\App\Http\Controllers\API\HrIntegrationController::class, 'businesses']);
    Route::get('/facilities', [\App\Http\Controllers\API\HrIntegrationController::class, 'facilities']);
    Route::get('/kashtre-entities', [\App\Http\Controllers\API\KashtreEntityController::class, 'index']);
    Route::get('/kashtre-entities/{uuid}', [\App\Http\Controllers\API\KashtreEntityController::class, 'show']);
    Route::get('/branches', [\App\Http\Controllers\API\HrIntegrationController::class, 'branches']);
    Route::get('/departments', [\App\Http\Controllers\API\HrIntegrationController::class, 'departments']);
    Route::get('/titles', [\App\Http\Controllers\API\HrIntegrationController::class, 'titles']);
    Route::get('/official-titles', [\App\Http\Controllers\API\HrIntegrationController::class, 'officialTitles']);
    Route::get('/qualifications', [\App\Http\Controllers\API\HrIntegrationController::class, 'qualifications']);
    Route::get('/staff-categories', [\App\Http\Controllers\API\HrIntegrationController::class, 'staffCategories']);
    Route::get('/cadres', [\App\Http\Controllers\API\HrIntegrationController::class, 'cadres']);
    Route::get('/designations', [\App\Http\Controllers\API\HrIntegrationController::class, 'designations']);
    Route::get('/client-spaces', [\App\Http\Controllers\API\HrIntegrationController::class, 'clientSpaces']);
    Route::get('/users', [\App\Http\Controllers\API\HrIntegrationController::class, 'users']);
    Route::get('/users/{uuid}', [\App\Http\Controllers\API\HrIntegrationController::class, 'userShow']);
    Route::get('/employee-identities', [\App\Http\Controllers\API\HrIntegrationController::class, 'employeeIdentities']);
    Route::get('/employee-identities/{uuid}', [\App\Http\Controllers\API\HrIntegrationController::class, 'employeeIdentityShow']);
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
