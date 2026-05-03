<?php

/**
 * Third-party (insurance) vendor global tiered service charges — loaded from RouteServiceProvider
 * so routes always register even if routes/web.php fails to parse past a certain line.
 */

use App\Http\Controllers\ThirdPartyVendorServiceChargeController;
use Illuminate\Support\Facades\Route;

Route::get('/third-party-vendor-service-charges', [ThirdPartyVendorServiceChargeController::class, 'index'])
    ->name('third-party-vendor-service-charges.index');
Route::get('/third-party-vendor-service-charges/create', [ThirdPartyVendorServiceChargeController::class, 'create'])
    ->name('third-party-vendor-service-charges.create');
Route::get('/third-party-vendor-service-charges/businesses/{business}/insurance-companies', [ThirdPartyVendorServiceChargeController::class, 'insuranceCompaniesForBusiness'])
    ->name('third-party-vendor-service-charges.insurance-companies');
Route::post('/third-party-vendor-service-charges', [ThirdPartyVendorServiceChargeController::class, 'store'])
    ->name('third-party-vendor-service-charges.store');
Route::get('/third-party-vendor-service-charges/{business}/edit', [ThirdPartyVendorServiceChargeController::class, 'edit'])
    ->name('third-party-vendor-service-charges.edit');
Route::put('/third-party-vendor-service-charges/{business}', [ThirdPartyVendorServiceChargeController::class, 'update'])
    ->name('third-party-vendor-service-charges.update');
