<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public API routes for client registration and open enrollment checks (no auth required)
Route::get('/insurance-company/by-code/{code}', [\App\Http\Controllers\ClientController::class, 'getInsuranceCompanyByCode'])->name('api.insurance-company.by-code');
Route::get('/insurance-settings/{insuranceCompanyId}', [\App\Http\Controllers\ClientController::class, 'getInsuranceCompanySettingsApi'])->name('api.insurance-settings');
Route::get('/policies/verify/{insuranceCompanyId}/{policyNumber}', [\App\Http\Controllers\ClientController::class, 'verifyPolicyNumber'])->name('api.policies.verify');
Route::post('/policies/verify/{insuranceCompanyId}', [\App\Http\Controllers\ClientController::class, 'verifyPolicyNumber'])->name('api.policies.verify.post');



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

    // Insurer portal: mirror Kashtre third-party vendor financial view (ledger, invoices, exclusions)
    Route::get('/businesses/{businessId}/third-party-vendors/{thirdPartyVendorId}/insurer-portal-summary', [\App\Http\Controllers\API\InsurerPortalVendorController::class, 'summary']);
    Route::get('/businesses/{businessId}/third-party-vendors/{thirdPartyVendorId}/insurer-portal-balance-history', [\App\Http\Controllers\API\InsurerPortalVendorController::class, 'balanceHistory']);

    // Client deductible tracking
    Route::get('/clients/{client}/deductible-used', [\App\Http\Controllers\API\ClientController::class, 'getDeductibleUsed']);

    // Client co-pay status tracking
    Route::get('/clients/{client}/copay-status', [\App\Http\Controllers\API\ClientController::class, 'getCopayPaidStatus']);

    // Insurance company settings
    Route::get('/insurance-companies/{insuranceCompanyId}/settings', [\App\Http\Controllers\API\ClientController::class, 'getInsuranceCompanySettings']);

    // Callback from third-party insurer after authorization decision (approve/reject)
    Route::post('/insurance/authorization-decision', [\App\Http\Controllers\API\InvoiceController::class, 'receiveAuthorizationDecision']);
});


