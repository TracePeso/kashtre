<?php

use App\Http\Controllers\Hr\AiRosterConstraintController;
use App\Http\Controllers\Hr\ApprovalRequestController;
use App\Http\Controllers\Hr\ApprovalWorkflowController;
use App\Http\Controllers\Hr\BiometricController;
use App\Http\Controllers\Hr\ClientSpaceController;
use App\Http\Controllers\Hr\DashboardController;
use App\Http\Controllers\Hr\HrCalendarController;
use App\Http\Controllers\Hr\HrPolicyController;
use App\Http\Controllers\Hr\LeaveApplicationController;
use App\Http\Controllers\Hr\LeaveTypeController;
use App\Http\Controllers\Hr\MyRosterController;
use App\Http\Controllers\Hr\OpenShiftController;
use App\Http\Controllers\Hr\OrganizationLeaveController;
use App\Http\Controllers\Hr\OrganizationalStructureController;
use App\Http\Controllers\Hr\RosterController;
use App\Http\Controllers\Hr\ShiftTypeController;
use App\Http\Controllers\Hr\StaffAssignmentController;
use App\Http\Controllers\Hr\TierStaffAssignmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->prefix('hr')->name('hr.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/sync', [DashboardController::class, 'sync'])
        ->middleware('hr.permission:Add HR Staff,Edit HR Staff')
        ->name('dashboard.sync');
    Route::get('/clocking', [BiometricController::class, 'clocking'])
        ->middleware('hr.permission:View HR Staff,Edit HR Staff,Manage HR Biometrics')
        ->name('clocking.index');

    Route::get('/staff-assignments', [StaffAssignmentController::class, 'index'])
        ->middleware('hr.permission:View HR Staff,Add HR Staff,Edit HR Staff')
        ->name('staff-assignments.index');
    Route::get('/tier-staff-assignments', [TierStaffAssignmentController::class, 'index'])
        ->name('tier-staff-assignments.index');
    Route::get('/client-spaces', [ClientSpaceController::class, 'index'])
        ->middleware('hr.permission:View HR Staff,View HR Setup,Add HR Staff,Edit HR Staff,Add HR Setup,Edit HR Setup')
        ->name('client-spaces.index');
    Route::get('/rosters', [RosterController::class, 'index'])->name('rosters.index');
    Route::get('/ai-roster-constraints', [AiRosterConstraintController::class, 'index'])
        ->middleware('hr.permission:View HR Setup,Add HR Setup,Edit HR Setup,Manage AI Roster Constraints')
        ->name('ai-roster-constraints.index');
    Route::get('/open-shifts', [OpenShiftController::class, 'index'])->name('open-shifts.index');
    Route::get('/my-roster', [MyRosterController::class, 'index'])->name('my-roster.index');

    Route::get('/biometrics', [BiometricController::class, 'index'])
        ->middleware('hr.permission:View HR Staff,Edit HR Staff,Manage HR Biometrics')
        ->name('biometrics.index');
    Route::get('/biometrics/enrollment', [BiometricController::class, 'enrollment'])
        ->middleware('hr.permission:View HR Staff,Edit HR Staff,Manage HR Biometrics')
        ->name('biometrics.enrollment');
    Route::get('/biometrics/attendance', [BiometricController::class, 'attendance'])
        ->middleware('hr.permission:View HR Staff,Edit HR Staff,Manage HR Biometrics')
        ->name('biometrics.attendance');
    Route::get('/biometrics/settings', [BiometricController::class, 'settings'])
        ->middleware('hr.permission:Edit HR Staff,Manage HR Biometrics')
        ->name('biometrics.settings');
    Route::post('/biometrics/enroll', [BiometricController::class, 'store'])
        ->middleware('hr.permission:Edit HR Staff,Manage HR Biometrics')
        ->name('biometrics.store');
    Route::patch('/biometrics/network-policy', [BiometricController::class, 'updateNetworkPolicy'])
        ->middleware('hr.permission:Edit HR Staff,Manage HR Biometrics')
        ->name('biometrics.network-policy');
    Route::patch('/biometrics/geofence-policy', [BiometricController::class, 'updateGeofencePolicy'])
        ->middleware('hr.permission:Edit HR Staff,Manage HR Biometrics')
        ->name('biometrics.geofence-policy');
    Route::patch('/biometrics/clock-settings', [BiometricController::class, 'updateClockSettings'])
        ->middleware('hr.permission:Edit HR Staff,Manage HR Biometrics')
        ->name('biometrics.clock-settings');
    Route::post('/biometrics/legacy-devices', [BiometricController::class, 'storeLegacyDevice'])
        ->middleware('hr.permission:Edit HR Staff,Manage HR Biometrics')
        ->name('biometrics.legacy-devices.store');
    Route::post('/biometrics/mobile-fingerprint/options', [BiometricController::class, 'mobileFingerprintOptions'])
        ->middleware('hr.permission:View HR Staff,Edit HR Staff,Manage HR Biometrics')
        ->name('biometrics.mobile-fingerprint.options');
    Route::post('/biometrics/legacy-device-import', [BiometricController::class, 'importLegacyDeviceLog'])
        ->middleware('hr.permission:Edit HR Staff,Manage HR Biometrics')
        ->name('biometrics.legacy-device-import');
    Route::post('/biometrics/verify', [BiometricController::class, 'verify'])
        ->middleware('hr.permission:View HR Staff,Edit HR Staff,Manage HR Biometrics')
        ->name('biometrics.verify');
    Route::delete('/biometrics/{biometricProfile}', [BiometricController::class, 'destroy'])
        ->middleware('hr.permission:Edit HR Staff,Manage HR Biometrics')
        ->name('biometrics.destroy');

    Route::get('/approval-workflows', [ApprovalWorkflowController::class, 'index'])
        ->middleware('hr.permission:View HR Setup,Add HR Setup,Edit HR Setup')
        ->name('approval-workflows.index');
    Route::get('/organizational-structure', [OrganizationalStructureController::class, 'index'])
        ->middleware('hr.permission:View HR Setup,Add HR Setup,Edit HR Setup')
        ->name('organizational-structure.index');
    Route::get('/approval-requests', [ApprovalRequestController::class, 'index'])->name('approval-requests.index');
    Route::get('/leave-applications', [LeaveApplicationController::class, 'index'])->name('leave-applications.index');
    Route::get('/organization-leaves', [OrganizationLeaveController::class, 'index'])
        ->middleware('hr.permission:View HR Approvals,Edit HR Approvals')
        ->name('organization-leaves.index');
    Route::get('/shift-types', [ShiftTypeController::class, 'index'])
        ->middleware('hr.permission:View HR Setup,Add HR Setup,Edit HR Setup')
        ->name('shift-types.index');
    Route::get('/leave-types', [LeaveTypeController::class, 'index'])
        ->middleware('hr.permission:View HR Setup,Add HR Setup,Edit HR Setup')
        ->name('leave-types.index');
    Route::get('/calendar', [HrCalendarController::class, 'index'])
        ->middleware('hr.permission:View HR Setup,Add HR Setup,Edit HR Setup')
        ->name('calendar.index');
    Route::get('/calendar/events', [HrCalendarController::class, 'events'])->name('calendar.events');
    Route::get('/policies', [HrPolicyController::class, 'index'])
        ->middleware('hr.permission:View HR Setup,Add HR Setup,Edit HR Setup')
        ->name('policies.index');
});
