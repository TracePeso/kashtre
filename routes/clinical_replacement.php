<?php

use App\Http\Controllers\ClinicalReplacementController;
use Illuminate\Support\Facades\Route;

// Loaded by routes/web.php: Main's web session and CSRF middleware remain in force.
Route::middleware(['auth', 'verified'])->prefix('clinical/replacement')->name('clinical.replacement.')->group(function () {
    Route::get('/', [ClinicalReplacementController::class, 'show'])->name('show');
    Route::post('/workflow', [ClinicalReplacementController::class, 'workflow'])->name('workflow');
    Route::get('/assets/{asset}', [ClinicalReplacementController::class, 'asset'])->name('asset');
});
