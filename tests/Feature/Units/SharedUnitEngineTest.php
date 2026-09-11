<?php

namespace Tests\Feature\Units;

use App\Domain\Units\Exceptions\ConversionException;
use App\Domain\Units\Models\CoreUnit;
use App\Domain\Units\Services\ConversionEngine;
use App\Domain\Units\Services\InventoryUnitGateway;
use App\Domain\Units\Services\UnitCatalogService;
use App\Domain\Units\ValueObjects\ConversionContext;
use App\Domain\Units\ValueObjects\Quantity;
use App\Models\Business;
use App\Models\Item;
use App\Models\ItemUnit;
use Database\Seeders\Units\CoreUnitSeedPackSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Uses DatabaseTransactions (not RefreshDatabase) so the shared local MySQL
 * demo schema is never wiped.
 */
class SharedUnitEngineTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! CoreUnit::query()->where('tenant_key', 'SYSTEM')->where('code', 'G')->exists()) {
            $this->seed(CoreUnitSeedPackSeeder::class);
        }

        config(['units.enabled' => true]);
    }

    private function makeBusiness(): Business
    {
        return Business::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Unit Engine Test Biz',
            'email' => 'units-'.uniqid().'@example.com',
            'phone' => '0700000000',
            'address' => 'Kampala',
            'account_number' => 'ACC'.strtoupper(uniqid()),
            'entity_code' => 'E'.strtoupper(substr(uniqid(), -5)),
            'currency_code' => 'UGX',
        ]);
    }

    public function test_scale_conversion_g_to_mg(): void
    {
        $catalog = app(UnitCatalogService::class);
        $g = $catalog->findByCode('SYSTEM', 'G');
        $mg = $catalog->findByCode('SYSTEM', 'MG');

        $result = app(ConversionEngine::class)->convert(
            new Quantity('2', $g->public_id),
            $mg->public_id,
            new ConversionContext(tenantKey: 'SYSTEM')
        );

        $this->assertSame('2000.0000', $result->targetValue);
        $this->assertNotSame('IDENTITY', $result->rulePublicId);
    }

    public function test_rejects_mass_to_volume_without_context(): void
    {
        $catalog = app(UnitCatalogService::class);
        $mg = $catalog->findByCode('SYSTEM', 'MG');
        $ml = $catalog->findByCode('SYSTEM', 'ML');

        try {
            app(ConversionEngine::class)->convert(
                new Quantity('5', $mg->public_id),
                $ml->public_id,
                new ConversionContext(tenantKey: 'SYSTEM')
            );
            $this->fail('Expected ConversionException');
        } catch (ConversionException $e) {
            $this->assertSame('DIMENSION_MISMATCH', $e->errorCode);
        }
    }

    public function test_inventory_packaging_order_to_sale_via_engine(): void
    {
        $business = $this->makeBusiness();
        $tablet = ItemUnit::query()->create([
            'business_id' => $business->id,
            'name' => 'Tablet',
        ]);
        $box = ItemUnit::query()->create([
            'business_id' => $business->id,
            'name' => 'Box',
        ]);

        $item = Item::query()->create([
            'business_id' => $business->id,
            'name' => 'Unit Engine Test Drug '.uniqid(),
            'type' => 'good',
            'category' => 'drug',
            'uom_id' => $tablet->id,
            'order_unit_id' => $box->id,
            'suom_per_ouom' => 100,
            'code' => 'UE-'.uniqid(),
            'default_price' => 1000,
        ]);

        $gateway = app(InventoryUnitGateway::class);
        $converted = $gateway->orderToSale($item->fresh(), '3');

        $this->assertSame('unit_engine', $converted['source']);
        $this->assertSame('300.0000', $converted['quantity']);
        $this->assertNotEmpty($item->fresh()->packaging_rule_public_id);
    }

    public function test_packaging_requires_item_context_when_calling_engine_directly(): void
    {
        $business = $this->makeBusiness();
        $tablet = ItemUnit::query()->create(['business_id' => $business->id, 'name' => 'Tablet']);
        $box = ItemUnit::query()->create(['business_id' => $business->id, 'name' => 'Box']);
        $item = Item::query()->create([
            'business_id' => $business->id,
            'name' => 'Unit Engine Ctx Drug '.uniqid(),
            'type' => 'good',
            'category' => 'drug',
            'uom_id' => $tablet->id,
            'order_unit_id' => $box->id,
            'suom_per_ouom' => 50,
            'code' => 'UEC-'.uniqid(),
            'default_price' => 500,
        ]);

        $gateway = app(InventoryUnitGateway::class);
        $gateway->ensureItemUnitLinks($item->fresh());
        $gateway->ensurePackagingRule($item->fresh());
        $item->refresh();

        try {
            app(ConversionEngine::class)->convert(
                new Quantity('2', $item->order_unit_public_id),
                $item->sale_unit_public_id,
                new ConversionContext(tenantKey: (string) $business->id, moduleCode: 'INVENTORY')
            );
            $this->fail('Expected ConversionException');
        } catch (ConversionException $e) {
            $this->assertSame('CONTEXT_REQUIRED', $e->errorCode);
        }
    }
}
