<?php

namespace Tests\Unit\Imaging;

use App\Models\ImagingProtocol;
use App\Models\ImagingStudy;
use Mockery;
use Tests\TestCase;

class ImagingStudyDoseAndPriorityTest extends TestCase
{
    /**
     * isIonizingModality() is driven by the protocol's own
     * involves_ionizing_radiation toggle (an admin-controlled choice), not a
     * hardcoded modality name match — protocol() is mocked here so this stays
     * a pure unit test with no real ImagingProtocol row required.
     */
    public function test_ionizing_flag_follows_the_protocols_own_toggle(): void
    {
        $ionizing = Mockery::mock(ImagingStudy::class)->makePartial();
        $ionizing->shouldReceive('protocol')->andReturn(new ImagingProtocol(['involves_ionizing_radiation' => true]));
        $this->assertTrue($ionizing->isIonizingModality());

        $nonIonizing = Mockery::mock(ImagingStudy::class)->makePartial();
        $nonIonizing->shouldReceive('protocol')->andReturn(new ImagingProtocol(['involves_ionizing_radiation' => false]));
        $this->assertFalse($nonIonizing->isIonizingModality());

        $noProtocol = Mockery::mock(ImagingStudy::class)->makePartial();
        $noProtocol->shouldReceive('protocol')->andReturn(null);
        $this->assertFalse($noProtocol->isIonizingModality());
    }

    public function test_priorities_cover_the_four_level_scale(): void
    {
        $this->assertSame([
            ImagingStudy::PRIORITY_LOW,
            ImagingStudy::PRIORITY_NORMAL,
            ImagingStudy::PRIORITY_HIGH,
            ImagingStudy::PRIORITY_URGENT,
        ], ImagingStudy::PRIORITIES);
    }
}
