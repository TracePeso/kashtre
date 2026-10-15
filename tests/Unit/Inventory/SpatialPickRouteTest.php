<?php

namespace Tests\Unit\Inventory;

use App\Services\Inventory\InventoryPickRouteService;
use PHPUnit\Framework\TestCase;

class SpatialPickRouteTest extends TestCase
{
    public function test_the_walk_follows_the_shelves_and_leaves_blank_places_last(): void
    {
        $sorted = InventoryPickRouteService::sortWalk([
            ['item_name' => 'Unplaced', 'location_layer_3' => null, 'location_layer_2' => null, 'location_layer_1' => null],
            ['item_name' => 'Bin ten', 'location_layer_3' => 'Wall', 'location_layer_2' => 'Cabinet A', 'location_layer_1' => 'Bin 10'],
            ['item_name' => 'Bin two', 'location_layer_3' => 'Wall', 'location_layer_2' => 'Cabinet A', 'location_layer_1' => 'Bin 2'],
            ['item_name' => 'Next cabinet', 'location_layer_3' => 'Wall', 'location_layer_2' => 'Cabinet B', 'location_layer_1' => 'Bin 1'],
            ['item_name' => 'Earlier wall', 'location_layer_3' => 'Bay', 'location_layer_2' => 'Tray', 'location_layer_1' => 'Slot 1'],
        ]);

        $this->assertSame(
            ['Earlier wall', 'Bin two', 'Bin ten', 'Next cabinet', 'Unplaced'],
            array_column($sorted, 'item_name')
        );
    }
}
