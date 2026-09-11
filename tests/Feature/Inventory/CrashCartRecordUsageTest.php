<?php

namespace Tests\Feature\Inventory;

use App\Models\Client;
use App\Models\CrashCartEvent;
use App\Models\CrashCartItem;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryStockLevel;
use App\Models\InventoryUsageEvent;
use App\Models\Item;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\InventoryCrashCartService;
use App\Services\Inventory\InventoryRecordUsageService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Crash cart ↔ Record Usage integration (manifest, break seal, balances).
 *
 * Manual UI checklist (/inventory/usage):
 * 1. Type = Crash cart → only open carts in Store dropdown
 * 2. Sealed cart absent from dropdown; break seal on /inventory/crash-carts/{id} first
 * 3. Item list = manifest lines with remaining > 0 only
 * 4. Qty capped to manifest remaining; stock ↓; row appears as Crash cart in table
 * 5. Over-par qty rejected; non-manifest item rejected; sealed cart rejected via API
 */
class CrashCartRecordUsageTest extends TestCase
{
    private const BUSINESS_ID = 4;

    private const BRANCH_ID = 6;

    private const END_STORE_ID = 11;

    protected function setUp(): void
    {
        parent::setUp();

        $config = InventoryModuleConfig::query()->firstOrCreate(
            ['business_id' => self::BUSINESS_ID],
            ['is_active' => true]
        );
        $config->update([
            'is_active' => true,
            'enable_floor_stock_management' => true,
            'enable_crash_cart_management' => true,
        ]);
    }

    public function test_sealed_cart_rejects_record_usage(): void
    {
        [$cart, $item, $client, $user] = $this->seedSealedCart();

        $this->expectException(ValidationException::class);

        app(InventoryRecordUsageService::class)->record([
            'business_id' => self::BUSINESS_ID,
            'context' => InventoryUsageEvent::CONTEXT_CRASH_CART,
            'client_id' => $client->id,
            'item_id' => $item->id,
            'store_id' => $cart->id,
            'quantity' => 1,
        ], $user);
    }

    public function test_break_seal_then_record_usage_updates_stock_and_balances(): void
    {
        [$cart, $item, $client, $user] = $this->seedSealedCart();
        $svc = app(InventoryCrashCartService::class);

        $cart = $svc->breakSeal($cart, $user);
        $this->assertTrue($cart->isCrashCartOpen());

        $stockBefore = (float) InventoryStockLevel::query()
            ->where('store_id', $cart->id)
            ->where('item_id', $item->id)
            ->value('quantity_suom');

        $events = app(InventoryRecordUsageService::class)->record([
            'business_id' => self::BUSINESS_ID,
            'context' => InventoryUsageEvent::CONTEXT_CRASH_CART,
            'client_id' => $client->id,
            'item_id' => $item->id,
            'store_id' => $cart->id,
            'quantity' => 2,
            'notes' => 'PHPUnit crash cart usage',
        ], $user);

        $this->assertCount(1, $events);
        $this->assertSame(InventoryUsageEvent::CONTEXT_CRASH_CART, $events->first()->context);
        $this->assertTrue($events->first()->billed_main_module);

        $stockAfter = (float) InventoryStockLevel::query()
            ->where('store_id', $cart->id)
            ->where('item_id', $item->id)
            ->value('quantity_suom');

        $this->assertEqualsWithDelta($stockBefore - 2, $stockAfter, 0.0001);

        $balance = $svc->balances($cart->fresh())->firstWhere('item_id', $item->id);
        $this->assertNotNull($balance);
        $this->assertEqualsWithDelta(2.0, $balance['used'], 0.0001);
        $this->assertEqualsWithDelta(4.0, $balance['remaining'], 0.0001);
    }

    public function test_cannot_exceed_manifest_remaining(): void
    {
        [$cart, $item, $client, $user] = $this->seedOpenCart();

        $this->expectException(ValidationException::class);

        app(InventoryRecordUsageService::class)->record([
            'business_id' => self::BUSINESS_ID,
            'context' => InventoryUsageEvent::CONTEXT_CRASH_CART,
            'client_id' => $client->id,
            'item_id' => $item->id,
            'store_id' => $cart->id,
            'quantity' => 999,
        ], $user);
    }

    public function test_non_manifest_item_rejected(): void
    {
        [$cart, , $client, $user] = $this->seedOpenCart();

        $otherItem = Item::query()
            ->where('business_id', self::BUSINESS_ID)
            ->where('type', 'good')
            ->whereNotIn('id', $cart->crashCartItems()->pluck('item_id'))
            ->first();

        $this->assertNotNull($otherItem);

        $this->expectException(ValidationException::class);

        app(InventoryRecordUsageService::class)->record([
            'business_id' => self::BUSINESS_ID,
            'context' => InventoryUsageEvent::CONTEXT_CRASH_CART,
            'client_id' => $client->id,
            'item_id' => $otherItem->id,
            'store_id' => $cart->id,
            'quantity' => 1,
        ], $user);
    }

    public function test_crash_cart_requires_client(): void
    {
        [$cart, $item, , $user] = $this->seedOpenCart();

        $this->expectException(ValidationException::class);

        app(InventoryRecordUsageService::class)->record([
            'business_id' => self::BUSINESS_ID,
            'context' => InventoryUsageEvent::CONTEXT_CRASH_CART,
            'item_id' => $item->id,
            'store_id' => $cart->id,
            'quantity' => 1,
        ], $user);
    }

    public function test_restock_and_reseal_pulls_from_parent_and_reseals(): void
    {
        [$cart, $item, $client, $user] = $this->seedSealedCart();
        $svc = app(InventoryCrashCartService::class);

        $cart = $svc->breakSeal($cart, $user);

        app(InventoryRecordUsageService::class)->record([
            'business_id' => self::BUSINESS_ID,
            'context' => InventoryUsageEvent::CONTEXT_CRASH_CART,
            'client_id' => $client->id,
            'item_id' => $item->id,
            'store_id' => $cart->id,
            'quantity' => 2,
        ], $user);

        $this->ensureParentStock($item->id, 10);

        $parentBefore = (float) InventoryStockLevel::query()
            ->where('store_id', self::END_STORE_ID)
            ->where('item_id', $item->id)
            ->value('quantity_suom');

        $result = $svc->restockAndReseal($cart->fresh(), $user, 'SEAL-TEST-RESTOCK');

        $this->assertTrue($result['store']->isCrashCartSealed());
        $this->assertSame('SEAL-TEST-RESTOCK', $result['store']->crash_cart_seal_number);
        $this->assertNull($result['store']->crash_cart_deployed_at);
        $this->assertCount(1, $result['restocked']);
        $this->assertEqualsWithDelta(2.0, $result['restocked'][0]['quantity'], 0.0001);

        $cartOnHand = (float) InventoryStockLevel::query()
            ->where('store_id', $cart->id)
            ->where('item_id', $item->id)
            ->value('quantity_suom');
        $this->assertEqualsWithDelta(6.0, $cartOnHand, 0.0001);

        $parentAfter = (float) InventoryStockLevel::query()
            ->where('store_id', self::END_STORE_ID)
            ->where('item_id', $item->id)
            ->value('quantity_suom');
        $this->assertEqualsWithDelta($parentBefore - 2, $parentAfter, 0.0001);

        $history = CrashCartEvent::query()
            ->where('store_id', $cart->id)
            ->where('event_type', CrashCartEvent::TYPE_RESTOCK_RESEAL)
            ->latest('id')
            ->first();
        $this->assertNotNull($history);
        $this->assertSame('SEAL-TEST-RESTOCK', $history->seal_number);
        $this->assertCount(1, $history->lines ?? []);
    }

    public function test_restock_blocked_when_parent_short_on_any_line(): void
    {
        [$cart, $item, $client, $user] = $this->seedOpenCart();
        $svc = app(InventoryCrashCartService::class);

        app(InventoryRecordUsageService::class)->record([
            'business_id' => self::BUSINESS_ID,
            'context' => InventoryUsageEvent::CONTEXT_CRASH_CART,
            'client_id' => $client->id,
            'item_id' => $item->id,
            'store_id' => $cart->id,
            'quantity' => 2,
        ], $user);

        $this->ensureParentStock($item->id, 0);

        $this->expectException(ValidationException::class);

        $svc->restockAndReseal($cart->fresh(), $user);
    }

    public function test_restock_rejected_while_sealed(): void
    {
        [$cart, , , $user] = $this->seedSealedCart();

        $this->expectException(ValidationException::class);

        app(InventoryCrashCartService::class)->restockAndReseal($cart, $user);
    }

    protected function ensureParentStock(int $itemId, float $quantity): void
    {
        $stock = InventoryStockLevel::query()->firstOrCreate(
            [
                'business_id' => self::BUSINESS_ID,
                'store_id' => self::END_STORE_ID,
                'item_id' => $itemId,
            ],
            ['quantity_suom' => 0, 'physical_quantity_suom' => 0]
        );

        if (method_exists($stock, 'applyOnHandBalance')) {
            $stock->applyOnHandBalance($quantity);
        } else {
            $stock->quantity_suom = $quantity;
            $stock->physical_quantity_suom = $quantity;
        }
        $stock->save();
    }

    /**
     * @return array{0: Store, 1: Item, 2: Client, 3: User}
     */
    protected function seedSealedCart(): array
    {
        $user = $this->kololoUser();
        $client = $this->kololoClient();
        $item = $this->sampleGood();

        $cart = Store::create([
            'business_id' => self::BUSINESS_ID,
            'branch_id' => self::BRANCH_ID,
            'parent_id' => self::END_STORE_ID,
            'name' => 'Test Crash Cart '.uniqid(),
            'distribution_type' => Store::DISTRIBUTION_SATELLITE,
            'satellite_role' => Store::SATELLITE_ROLE_CRASH_CART,
            'is_crash_cart' => true,
            'crash_cart_status' => Store::CRASH_CART_READY,
            'crash_cart_seal_number' => 'TEST-'.uniqid(),
            'crash_cart_sealed_at' => now(),
        ]);

        app(InventoryCrashCartService::class)->syncManifest($cart, [
            ['item_id' => $item->id, 'par_quantity' => 6],
        ], $user);

        return [$cart->fresh(), $item, $client, $user];
    }

    /**
     * @return array{0: Store, 1: Item, 2: Client, 3: User}
     */
    protected function seedOpenCart(): array
    {
        [$cart, $item, $client, $user] = $this->seedSealedCart();

        $cart = app(InventoryCrashCartService::class)->breakSeal($cart, $user);

        return [$cart, $item, $client, $user];
    }

    protected function kololoUser(): User
    {
        return User::query()
            ->where('business_id', self::BUSINESS_ID)
            ->where('branch_id', self::BRANCH_ID)
            ->firstOrFail();
    }

    protected function kololoClient(): Client
    {
        return Client::query()
            ->where('business_id', self::BUSINESS_ID)
            ->where('branch_id', self::BRANCH_ID)
            ->firstOrFail();
    }

    protected function sampleGood(): Item
    {
        return Item::query()
            ->where('business_id', self::BUSINESS_ID)
            ->where('type', 'good')
            ->firstOrFail();
    }
}
