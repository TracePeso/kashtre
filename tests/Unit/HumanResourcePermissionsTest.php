<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\HrModule;
use App\Traits\AccessTrait;
use PHPUnit\Framework\TestCase;

class HumanResourcePermissionsTest extends TestCase
{
    private const EXPECTED_HR_PERMISSIONS = [
        'View HR Staff',
        'Add HR Staff',
        'Edit HR Staff',
        'View HR Setup',
        'Add HR Setup',
        'Edit HR Setup',
        'View HR Approvals',
        'Edit HR Approvals',
        'Manage HR Biometrics',
        'Manage AI Roster Constraints',
    ];

    public function test_access_control_exposes_human_resource_section_with_all_hr_permissions(): void
    {
        $accessControl = $this->permissionProvider()->getAccessControl();

        $this->assertArrayHasKey('Human Resource', $accessControl);
        $this->assertSame(
            self::EXPECTED_HR_PERMISSIONS,
            $accessControl['Human Resource']['Human Resource']
        );
    }

    public function test_all_permissions_include_human_resource_permissions(): void
    {
        $allPermissions = $this->permissionProvider()->getAllPermissions();

        foreach (self::EXPECTED_HR_PERMISSIONS as $permission) {
            $this->assertContains($permission, $allPermissions);
        }
    }

    public function test_hr_permission_filters_stay_aligned(): void
    {
        $samplePermissions = [
            'Human Resource',
            'View HR Staff',
            'Manage HR Biometrics',
            'Manage AI Roster Constraints',
            'Unrelated Permission',
        ];

        $this->assertSame(
            [
                'View HR Staff',
                'Manage HR Biometrics',
                'Manage AI Roster Constraints',
            ],
            User::filterHrPermissions($samplePermissions)
        );

        $this->assertSame(self::EXPECTED_HR_PERMISSIONS, User::HR_PERMISSIONS);
        $this->assertSame(self::EXPECTED_HR_PERMISSIONS, HrModule::ACCESS_PERMISSIONS);
    }

    private function permissionProvider(): object
    {
        return new class {
            use AccessTrait;
        };
    }
}
