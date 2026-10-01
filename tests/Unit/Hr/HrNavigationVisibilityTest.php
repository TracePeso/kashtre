<?php

namespace Tests\Unit\Hr;

use App\Support\Hr\HrNavigationVisibility;
use PHPUnit\Framework\TestCase;

class HrNavigationVisibilityTest extends TestCase
{
    private function item(string $key): array
    {
        return ['key' => $key, 'label' => $key, 'path' => "/hr/{$key}", 'icon' => 'x'];
    }

    public function test_a_user_with_no_permissions_still_sees_the_self_service_items(): void
    {
        $items = array_map([$this, 'item'], [
            'dashboard', 'leave', 'my-leave', 'attendance', 'my-attendance',
            'enroll-device', 'rosters', 'payroll', 'performance',
            'recognition', 'my-shifts', 'reports', 'settings',
        ]);

        $visible = array_column(HrNavigationVisibility::filter($items, []), 'key');

        $this->assertSame(
            ['dashboard', 'my-leave', 'my-attendance', 'enroll-device', 'rosters', 'my-shifts'],
            $visible
        );
    }

    public function test_each_gated_item_requires_its_own_matching_permission(): void
    {
        $cases = [
            'leave'       => 'View HR Leave',
            'attendance'  => 'View HR Attendance',
            'payroll'     => 'View HR Payroll',
            'performance' => 'View HR Performance',
            'recognition' => 'View HR Recognition',
            'reports'     => 'View HR Reports',
            'settings'    => 'View HR Setup',
        ];

        foreach ($cases as $key => $permission) {
            $items = [$this->item($key)];

            $this->assertSame([], HrNavigationVisibility::filter($items, []), "{$key} should be hidden with no permissions");
            $this->assertSame(
                [$key],
                array_column(HrNavigationVisibility::filter($items, [$permission]), 'key'),
                "{$key} should appear once {$permission} is granted"
            );

            // Holding every other gated permission except this item's own doesn't leak visibility onto it.
            $otherPermissions = array_values(array_diff($cases, [$permission]));
            $this->assertSame(
                [],
                HrNavigationVisibility::filter($items, $otherPermissions),
                "{$key} should stay hidden when only unrelated HR permissions are held"
            );
        }
    }

    public function test_an_unknown_manifest_key_is_hidden_rather_than_assumed_safe(): void
    {
        $items = [$this->item('some-future-page')];

        $this->assertSame([], HrNavigationVisibility::filter($items, ['*', 'View HR Setup', 'View HR Leave']));
    }

    public function test_order_and_other_fields_are_preserved(): void
    {
        $items = [
            $this->item('payroll'),
            $this->item('dashboard'),
        ];

        $visible = HrNavigationVisibility::filter($items, ['View HR Payroll']);

        $this->assertSame(['payroll', 'dashboard'], array_column($visible, 'key'));
        $this->assertSame('/hr/payroll', $visible[0]['path']);
    }
}
