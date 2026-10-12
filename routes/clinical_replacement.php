<?php

use App\Http\Controllers\ClinicalReplacementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('clinical/replacement')->name('clinical.replacement.')->group(function () {
    Route::get('/', [ClinicalReplacementController::class, 'show'])->name('show');
    Route::post('/client/{client_id}/workflow', [ClinicalReplacementController::class, 'workflow'])
        ->where('client_id', '[A-Z0-9]{4,32}')->name('workflow');
    Route::get('/assets/{asset}', [ClinicalReplacementController::class, 'asset'])->name('asset');
});
