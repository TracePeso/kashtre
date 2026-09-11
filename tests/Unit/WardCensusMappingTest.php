<?php

namespace Tests\Unit;

use App\Support\Clinical\WardBed;
use App\Support\Clinical\WardCensus;
use PHPUnit\Framework\TestCase;

/**
 * The Clinical Module's census payload and Main's local models name the same
 * things differently (total_beds vs total, patient_id vs current_client_id).
 * These assertions pin that translation — a silent mismatch here shows up as
 * a ward reporting zero occupancy, which reads as "quiet shift" rather than
 * "broken mapping".
 */
class WardCensusMappingTest extends TestCase
{
    /** A verbatim beds_grid entry from the running Clinical Module. */
    public function test_it_maps_the_clinical_census_payload(): void
    {
        $census = WardCensus::fromApi([
            'ward_code' => 'ICU',
            'ward_name' => 'Intensive Care Unit',
            'total_beds' => 4,
            'occupied' => 2,
            'reserved' => 1,
            'available' => 1,
            'overflow_beds' => 1,
            'beds_grid' => [
                [
                    'id' => 1, 'space_id' => 1, 'bed_code' => 'ICU-01',
                    'operational_state' => 'OCCUPIED',
                    'current_patient_id' => 'CL-00001234',
                    'current_visit_id' => 'VIS-2026-000001',
                    'is_overflow' => false, 'room_number' => 'RM-01',
                    'building_wing' => 'East Wing',
                ],
                [
                    'id' => 3, 'space_id' => 1, 'bed_code' => 'ICU-03',
                    'operational_state' => 'AVAILABLE',
                    'current_patient_id' => null, 'is_overflow' => false,
                    'room_number' => 'RM-01',
                ],
            ],
        ]);

        $this->assertSame('ICU', $census->ward_code);
        $this->assertSame('Intensive Care Unit', $census->ward_name);
        $this->assertSame(4, $census->total);
        $this->assertSame(2, $census->occupied);
        $this->assertSame(1, $census->reserved);
        $this->assertSame(1, $census->available);
        $this->assertSame(1, $census->overflow);

        // building_wing rides on the beds, not the census header.
        $this->assertSame('East Wing', $census->building_wing);

        $this->assertCount(2, $census->beds);
        $this->assertSame(1, $census->beds[0]->id);
        $this->assertSame('ICU-01', $census->beds[0]->bed_code);
        $this->assertSame('CL-00001234', $census->beds[0]->current_client_id);
        $this->assertSame('RM-01', $census->beds[0]->room_number);
        $this->assertTrue($census->beds[0]->isOccupied());
        $this->assertNull($census->beds[1]->current_client_id);
        $this->assertTrue($census->beds[1]->isAvailable());
    }

    /** Adding an overflow bed is keyed by space id, which only beds carry. */
    public function test_it_exposes_the_space_id_for_overflow(): void
    {
        $census = WardCensus::fromApi([
            'ward_code' => 'ICU',
            'beds_grid' => [
                ['id' => 1, 'space_id' => 7, 'bed_code' => 'ICU-01', 'operational_state' => 'OCCUPIED'],
            ],
        ]);

        $this->assertSame(7, $census->spaceIdForOverflow());
    }

    public function test_clear_surge_is_offered_only_when_a_vacant_overflow_bed_exists(): void
    {
        $occupiedSurge = WardCensus::fromApi(['ward_code' => 'ICU', 'beds_grid' => [
            ['id' => 1, 'bed_code' => 'ICU-OF-01', 'operational_state' => 'OCCUPIED', 'is_overflow' => true],
        ]]);
        $this->assertFalse($occupiedSurge->hasVacantOverflowBeds());

        $reservedSurge = WardCensus::fromApi(['ward_code' => 'ICU', 'beds_grid' => [
            ['id' => 1, 'bed_code' => 'ICU-OF-01', 'operational_state' => 'RESERVED', 'is_overflow' => true],
        ]]);
        $this->assertFalse($reservedSurge->hasVacantOverflowBeds());

        $vacantSurge = WardCensus::fromApi(['ward_code' => 'ICU', 'beds_grid' => [
            ['id' => 1, 'bed_code' => 'ICU-OF-01', 'operational_state' => 'AVAILABLE', 'is_overflow' => true],
        ]]);
        $this->assertTrue($vacantSurge->hasVacantOverflowBeds());

        // A vacant *baseline* bed is not surge capacity.
        $baseline = WardCensus::fromApi(['ward_code' => 'ICU', 'beds_grid' => [
            ['id' => 1, 'bed_code' => 'ICU-01', 'operational_state' => 'AVAILABLE', 'is_overflow' => false],
        ]]);
        $this->assertFalse($baseline->hasVacantOverflowBeds());
    }

    /** The picker rows have no grid — they still drive the header cards. */
    public function test_it_maps_a_ward_picker_row(): void
    {
        $ward = WardCensus::fromApi([
            'ward_code' => 'ICU', 'ward_name' => 'Intensive Care Unit',
            'space_count' => 1, 'total_beds' => 7, 'occupied' => 2,
            'reserved' => 0, 'available' => 5, 'overflow_beds' => 1,
            'occupancy_rate' => 28.6,
        ]);

        $this->assertSame(7, $ward->total);
        $this->assertSame(28.6, $ward->occupancy_rate);
        $this->assertSame([], $ward->beds);
        $this->assertNull($ward->spaceIdForOverflow());
    }

    /** An unseeded ward — Clinical's current state — must not blow up. */
    public function test_it_maps_an_empty_ward(): void
    {
        $census = WardCensus::fromApi([
            'ward_code' => 'ICU',
            'ward_name' => null,
            'total_beds' => 0,
            'occupied' => 0,
            'reserved' => 0,
            'available' => 0,
            'overflow_beds' => 0,
            'beds_grid' => [],
        ]);

        $this->assertSame('ICU', $census->ward_code);
        $this->assertNull($census->ward_name);
        $this->assertSame(0, $census->total);
        $this->assertSame([], $census->beds);
    }

    public function test_it_accepts_the_alternate_field_names(): void
    {
        $census = WardCensus::fromApi([
            'ward_code' => 'GYNAE',
            'total' => 2,
            'overflow' => 1,
            'beds' => [
                ['code' => 'G-01', 'state' => 'occupied', 'current_client_id' => 'CL-9', 'is_overflow' => true],
            ],
        ]);

        $this->assertSame(2, $census->total);
        $this->assertSame(1, $census->overflow);
        $this->assertSame('G-01', $census->beds[0]->bed_code);
        // State is upper-cased so the Blade comparisons against 'OCCUPIED' hold.
        $this->assertSame('OCCUPIED', $census->beds[0]->operational_state);
        $this->assertTrue($census->beds[0]->is_overflow);
    }

    /** The bed id is what the per-bed action routes address. */
    public function test_it_reads_the_bed_id_from_either_shape(): void
    {
        // beds_grid entries and the reserve/assign/release bed model use `id`.
        $this->assertSame(3, WardBed::fromApi(['id' => 3, 'bed_code' => 'ICU-03'])->id);

        // The overflow-bed response is a flat object using `bed_id` instead.
        $this->assertSame(7, WardBed::fromApi(['bed_id' => 7, 'bed_code' => 'ICU-OF-01'])->id);
    }

    public function test_it_defaults_a_missing_state_to_available(): void
    {
        $this->assertSame('AVAILABLE', WardBed::fromApi(['bed_code' => 'X-1'])->operational_state);
    }
}
