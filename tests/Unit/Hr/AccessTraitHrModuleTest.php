<?php

namespace Tests\Unit\Hr;

use App\Traits\AccessTrait;
use PHPUnit\Framework\TestCase;

/**
 * $hrModule used to be one flat 23-item list under a single "HR Module"
 * key, giving every permission the same generic category in the role-edit
 * screen. Broken out into 12 labelled sub-categories (Staff, Setup,
 * Approvals, Roster Approvals, Attendance Exceptions, Attendance, Leave,
 * Payroll, Performance, Recognition, Reports, Device Pairing) so an admin
 * can tell at a glance -- and bulk-select -- what each checkbox actually
 * controls.
 *
 * The real risk here isn't the permission strings (unchanged, verified
 * below) -- it's that getAllPermissions() merges every property's
 * top-level keys with a plain array_merge(), which silently drops one
 * side of any name collision. An earlier version of this change used bare
 * category names ("Staff", "Reports") and it genuinely clobbered
 * $staffAccess's and $reportAccess's own same-named categories, dropping
 * real permissions from getAllPermissions() with no error. Every category
 * name here is "HR "-prefixed specifically to rule that out.
 */
class AccessTraitHrModuleTest extends TestCase
{
    use AccessTrait;

    private const EXPECTED_PERMISSIONS = [
        'View HR Staff', 'Add HR Staff', 'Edit HR Staff',
        'View HR Setup', 'Add HR Setup', 'Edit HR Setup',
        'View HR Approvals', 'Edit HR Approvals',
        'View HR Roster Approvals', 'Edit HR Roster Approvals',
        'View HR Attendance Exceptions', 'Edit HR Attendance Exceptions',
        'View HR Attendance', 'Edit HR Attendance',
        'View HR Leave', 'Edit HR Leave',
        'View HR Payroll',
        'View HR Performance',
        'View HR Recognition', 'Edit HR Recognition',
        'View HR Reports',
        'View HR Device Pairing', 'Edit HR Device Pairing',
    ];

    public function test_every_hr_permission_string_is_unchanged(): void
    {
        $known = [];
        foreach (self::getAccessControl()['HR Module'] as $perms) {
            $known = array_merge($known, $perms);
        }

        sort($known);
        $expected = self::EXPECTED_PERMISSIONS;
        sort($expected);

        $this->assertSame($expected, $known);
    }

    public function test_hr_module_is_organised_into_named_sub_categories(): void
    {
        $categories = array_keys(self::getAccessControl()['HR Module']);

        $this->assertSame([
            'HR Staff', 'HR Setup', 'HR Approvals', 'HR Roster Approvals',
            'HR Attendance Exceptions', 'HR Attendance', 'HR Leave',
            'HR Payroll', 'HR Performance', 'HR Recognition', 'HR Reports',
            'HR Device Pairing',
        ], $categories);
    }

    /**
     * Regression for the collision bug described in the class docblock:
     * every real HR permission string must still appear in the fully
     * flattened getAllPermissions() list, not just in $hrModule's own
     * structure -- array_merge() can drop one without the other noticing.
     */
    public function test_every_hr_permission_survives_flattening_into_getAllPermissions(): void
    {
        $all = self::getAllPermissions();

        foreach (self::EXPECTED_PERMISSIONS as $permission) {
            $this->assertContains($permission, $all, "{$permission} is missing from getAllPermissions()");
        }
    }

    /**
     * Guards against reintroducing a category name that collides with
     * another property's own top-level key anywhere in the trait -- the
     * exact class of bug this restructuring hit once already.
     */
    public function test_no_hr_module_category_collides_with_another_propertys_top_level_key(): void
    {
        $reflection = new \ReflectionClass(self::class);
        $props = [
            'admin', 'entities', 'items', 'staff', 'reports', 'logs', 'contractor', 'sales',
            'cashier', 'clients', 'visits', 'queues', 'withdrawaal', 'modules', 'stock',
            'masters', 'callers', 'inventoryModule', 'adminAccess', 'businessAccess',
            'timeEngineAccess', 'clientAccess', 'staffAccess', 'reportAccess', 'bulkUpload',
            'finance', 'packageTracking', 'packageSales', 'imagingModule', 'clinicalModule',
        ];

        $otherKeys = [];
        foreach ($props as $prop) {
            $otherKeys = array_merge($otherKeys, array_keys($reflection->getStaticPropertyValue($prop)));
        }

        $hrCategories = array_keys(self::getAccessControl()['HR Module']);
        $collisions = array_intersect($hrCategories, $otherKeys);

        $this->assertSame([], $collisions, 'An HR Module category name collides with another permission group, which array_merge() in getAllPermissions() would silently resolve by dropping one side.');
    }
}
