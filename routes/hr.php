<?php

use App\Http\Controllers\Hr\HrDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->prefix('hr')->name('hr.')->group(function () {
    Route::get('/', [HrDashboardController::class, 'index'])->name('dashboard');
});
