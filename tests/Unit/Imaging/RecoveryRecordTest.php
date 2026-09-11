<?php

namespace Tests\Unit\Imaging;

use App\Models\ImagingStudy;
use App\Models\RecoveryRecord;
use Tests\TestCase;

class RecoveryRecordTest extends TestCase
{
    public function test_clear_for_discharge_rejects_when_criteria_not_met(): void
    {
        $record = new RecoveryRecord(['discharge_criteria_met' => false]);

        $this->expectException(\RuntimeException::class);
        $record->clearForDischarge(1);
    }

    public function test_clear_for_discharge_rejects_when_already_discharged(): void
    {
        $record = new RecoveryRecord([
            'discharge_criteria_met' => true,
            'discharge_cleared_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $record->clearForDischarge(1);
    }

    public function test_is_discharged_reflects_the_cleared_timestamp(): void
    {
        $pending = new RecoveryRecord(['discharge_cleared_at' => null]);
        $cleared = new RecoveryRecord(['discharge_cleared_at' => now()]);

        $this->assertFalse($pending->isDischarged());
        $this->assertTrue($cleared->isDischarged());
    }

    public function test_requires_recovery_is_false_without_a_resolvable_protocol(): void
    {
        $study = new ImagingStudy(['protocol_code' => 'DOES-NOT-EXIST']);

        $this->assertFalse($study->requiresRecovery());
        $this->assertFalse($study->isDischargeCleared());
    }
}
