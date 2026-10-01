<?php

namespace App\Support\Hr;

/**
 * The HR module's own navigation manifest (what AppServiceProvider caches
 * as `hr.navigation`, fed from KashtreIntegrationController::navigation()
 * on the HR module side) has no concept of *this* app's users or their
 * permissions -- it's a flat, business-wide list returned to a
 * shared-secret API caller, not a per-user request. Every item rendered
 * unconditionally in the sidebar regardless of what the clicking user was
 * actually allowed to open.
 *
 * Clicking through to one the user lacked permission for used to land on
 * a clean 403 (HrController::ensurePermission()/EMBED_PATH_PERMISSIONS),
 * but several items (Rosters, Payroll, Performance, etc. launched via the
 * generic HR dropdown) bypass those checks entirely -- embed() only
 * enforces permission for the four paths in EMBED_PATH_PERMISSIONS -- and
 * on at least one of those, the user ended up seeing a confusing "SSO
 * token signature mismatch" instead. Rather than chase that unrelated
 * signing issue, the fix is the same one already applied to the HR
 * module's own sidebar: don't render a link the user can't open.
 *
 * Keyed by the manifest's own `key` field. `null` means "self-service,
 * visible to anyone with HR module access" -- these mirror the items the
 * HR module itself leaves open by default (My Leave, Clock In/Out,
 * Enroll Device, My Shifts, Rosters -- `hr.rosters.view_own` is a
 * standing grant on the HR module's own 'staff' role). Any manifest key
 * not listed here is hidden, not shown -- deny by default for an item
 * this map doesn't yet know about, rather than assuming it's safe.
 */
class HrNavigationVisibility
{
    private const REQUIRED_PERMISSION = [
        'dashboard'     => null,
        'leave'         => 'View HR Leave',
        'my-leave'      => null,
        'attendance'    => 'View HR Attendance',
        'my-attendance' => null,
        'enroll-device' => null,
        'rosters'       => null,
        'payroll'       => 'View HR Payroll',
        'performance'   => 'View HR Performance',
        'recognition'   => 'View HR Recognition',
        'my-shifts'     => null,
        'reports'       => 'View HR Reports',
        'settings'      => 'View HR Setup',
    ];

    public static function filter(array $items, array $userPermissions): array
    {
        return array_values(array_filter($items, function (array $item) use ($userPermissions) {
            $key = $item['key'] ?? null;

            if (! array_key_exists($key, self::REQUIRED_PERMISSION)) {
                return false;
            }

            $required = self::REQUIRED_PERMISSION[$key];

            return $required === null || in_array($required, $userPermissions, true);
        }));
    }
}
