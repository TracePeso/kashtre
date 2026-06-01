<?php

namespace Tests\Unit;

use App\Models\HrStaffRosteringProfile;
use App\Models\ShiftType;
use PHPUnit\Framework\TestCase;

class HrStaffRosteringProfileRegularWorkingHoursFallbackTest extends TestCase
{
    public function test_profile_without_explicit_shift_preference_defaults_to_regular_working_hours(): void
    {
        $profile = new HrStaffRosteringProfile([
            'rostering_mode' => HrStaffRosteringProfile::MODE_DYNAMIC,
            'preferred_shift_type_ids' => [],
            'is_active' => true,
        ]);

        $regularShift = new ShiftType([
            'code' => 'RWH',
            'name' => 'Regular working Hours',
            'is_system_default' => true,
        ]);
        $regularShift->id = 11;

        $nightShift = new ShiftType([
            'code' => 'NIGHT',
            'name' => 'Night Shift',
            'is_system_default' => false,
        ]);
        $nightShift->id = 22;

        $this->assertFalse($profile->hasExplicitShiftPreference());
        $this->assertTrue($profile->defaultsToRegularWorkingHours($regularShift));
        $this->assertTrue($profile->prefersShift($regularShift));
        $this->assertFalse($profile->defaultsToRegularWorkingHours($nightShift));
        $this->assertFalse($profile->prefersShift($nightShift));
        $this->assertSame([11], $profile->preferredShiftIdsForPrompt(11));
    }

    public function test_explicit_shift_preference_disables_regular_working_hours_default(): void
    {
        $profile = new HrStaffRosteringProfile([
            'rostering_mode' => HrStaffRosteringProfile::MODE_DYNAMIC,
            'preferred_shift_type_ids' => [22],
            'is_active' => true,
        ]);

        $regularShift = new ShiftType([
            'code' => 'RWH',
            'name' => 'Regular working Hours',
            'is_system_default' => true,
        ]);
        $regularShift->id = 11;

        $preferredShift = new ShiftType([
            'code' => 'NIGHT',
            'name' => 'Night Shift',
            'is_system_default' => false,
        ]);
        $preferredShift->id = 22;

        $this->assertTrue($profile->hasExplicitShiftPreference());
        $this->assertFalse($profile->defaultsToRegularWorkingHours($regularShift));
        $this->assertFalse($profile->prefersShift($regularShift));
        $this->assertTrue($profile->prefersShift($preferredShift));
        $this->assertSame([22], $profile->preferredShiftIdsForPrompt(11));
    }

    public function test_regular_working_hours_detection_supports_legacy_default_markers(): void
    {
        $legacyRegularShift = new ShiftType([
            'code' => 'DAY',
            'name' => 'Regular working Hours',
            'is_system_default' => false,
        ]);
        $legacyRegularShift->id = 33;

        $this->assertTrue($legacyRegularShift->isRegularWorkingHoursDefault());
    }
}
