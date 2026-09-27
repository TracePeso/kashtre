<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryDailyConsumption;
use App\Models\Store;
use App\Services\Inventory\InventoryConsumptionSampleDataService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InventoryConsumptionSampleDataTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pending_range_never_exceeds_the_livewire_cap(): void
    {
        $store = Store::query()->where('business_id', '!=', 1)->first();
        if (! $store) {
            $this->markTestSkipped('A hospital store is required.');
        }

        $range = app(InventoryConsumptionSampleDataService::class)
            ->pendingBackfillRange((int) $store->business_id, (int) $store->id);

        if ($range['already_current']) {
            $this->assertSame(0, $range['days']);

            return;
        }

        $this->assertFalse($range['already_current']);
        $this->assertLessThanOrEqual(InventoryConsumptionSampleDataService::MAX_BACKFILL_DAYS, $range['days']);
        $this->assertSame(now()->toDateString(), $range['until']);
        $this->assertNotNull($range['from']);
    }

    public function test_empty_store_starts_28_days_back_for_ai_history(): void
    {
        Carbon::setTestNow('2026-09-27 12:00:00');

        $range = app(InventoryConsumptionSampleDataService::class)
            ->pendingBackfillRange(1, 9_999_990);

        $this->assertFalse($range['already_current']);
        $this->assertSame(28, $range['days']);
        $this->assertSame('2026-08-31', $range['from']);
        $this->assertSame('2026-09-27', $range['until']);

        Carbon::setTestNow();
    }
}
