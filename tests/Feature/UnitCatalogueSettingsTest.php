<?php

namespace Tests\Feature;

use App\Domain\Units\Models\CoreUnit;
use App\Models\Business;
use App\Models\ItemUnit;
use App\Models\User;
use App\Support\SharedUnits;
use Database\Seeders\Units\CoreUnitSeedPackSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnitCatalogueSettingsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['units.enabled' => true]);

        if (! CoreUnit::query()->where('tenant_key', 'SYSTEM')->where('code', 'TABLET')->exists()) {
            $this->seed(CoreUnitSeedPackSeeder::class);
        }
    }

    public function test_kashtre_admin_can_view_unit_settings(): void
    {
        $this->actingAs($this->makeUser($this->kashtreBusiness(), 'units.settings.admin@example.com'));

        $this->get(route('settings.units.index'))
            ->assertOk()
            ->assertSee('Add unit')
            ->assertSee('Shared catalog')
            ->assertSee('ampoule');
    }

    public function test_inventory_units_console_redirects_to_item_units(): void
    {
        $this->actingAs($this->makeUser($this->kashtreBusiness(), 'units.settings.redirect@example.com'));

        $this->get(route('inventory.units.index'))
            ->assertRedirect(route('item-units.index'));
    }

    public function test_platform_units_redirects_to_settings(): void
    {
        $this->actingAs($this->makeUser($this->kashtreBusiness(), 'units.settings.platform@example.com'));

        $this->get('/platform/units')
            ->assertRedirect('/settings/units');
    }

    public function test_kashtre_admin_can_add_and_retire_a_catalog_unit(): void
    {
        $this->actingAs($this->makeUser($this->kashtreBusiness(), 'units.settings.add@example.com'));

        $code = 'SACHET'.strtoupper(substr(uniqid(), -4));

        $this->post(route('settings.units.store'), [
            'code' => $code,
            'name' => 'sachet',
            'symbol' => 'sachet',
            'unit_class' => 'PACKAGING_CONTEXTUAL',
        ])->assertRedirect(route('settings.units.index'));

        $this->assertDatabaseHas('core_units', [
            'tenant_key' => 'SYSTEM',
            'code' => $code,
            'canonical_name' => 'sachet',
            'status' => 'ACTIVE',
            'is_system' => 1,
        ]);

        $unit = CoreUnit::query()->where('tenant_key', 'SYSTEM')->where('code', $code)->first();
        $this->assertNotNull($unit);

        $this->post(route('settings.units.retire', $unit))
            ->assertRedirect(route('settings.units.index'));

        $this->assertDatabaseHas('core_units', [
            'id' => $unit->id,
            'status' => 'DEPRECATED',
        ]);
    }

    public function test_non_kashtre_user_cannot_manage_catalog(): void
    {
        $this->actingAs($this->makeUser($this->hospitalBusiness(), 'units.settings.hospital@example.com'));

        $this->get(route('settings.units.index'))->assertForbidden();
        $this->post(route('settings.units.store'), [
            'code' => 'NOPE',
            'name' => 'nope',
            'symbol' => 'nope',
            'unit_class' => 'PACKAGING_CONTEXTUAL',
        ])->assertForbidden();
    }

    public function test_item_pickers_include_shared_catalog_units(): void
    {
        $business = $this->makeHospital();

        $units = SharedUnits::itemUnitsForBusiness((int) $business->id);

        $this->assertTrue($units->contains(fn (ItemUnit $unit) => strtolower($unit->name) === 'tablet'));
        $this->assertTrue($units->contains(fn (ItemUnit $unit) => strtolower($unit->name) === 'box'));
    }

    public function test_business_can_add_a_local_packaging_unit(): void
    {
        $business = $this->makeHospital();
        $name = 'Hospital pack '.uniqid();

        $itemUnit = SharedUnits::addLocalPackagingUnit((int) $business->id, $name, 'hpack');

        $this->assertSame($name, $itemUnit->name);
        $this->assertSame('Local', SharedUnits::sourceLabel($itemUnit));
        $this->assertDatabaseHas('core_units', [
            'tenant_key' => (string) $business->id,
            'canonical_name' => $name,
            'status' => 'ACTIVE',
            'is_system' => 0,
        ]);
    }

    private function kashtreBusiness(): Business
    {
        $business = Business::query()->find(1);

        if (! $business) {
            $this->markTestSkipped('Kashtre business (id 1) is required.');
        }

        return $business;
    }

    private function hospitalBusiness(): Business
    {
        $business = Business::query()->where('id', '!=', 1)->first();

        if (! $business) {
            $this->markTestSkipped('A non-Kashtre business is required.');
        }

        return $business;
    }

    private function makeHospital(): Business
    {
        return Business::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Unit Catalog Test Biz',
            'email' => 'units-cat-'.uniqid().'@example.com',
            'phone' => '0700000000',
            'address' => 'Kampala',
            'account_number' => 'ACC'.strtoupper(uniqid()),
            'entity_code' => 'E'.strtoupper(substr(uniqid(), -5)),
            'currency_code' => 'UGX',
        ]);
    }

    private function makeUser(Business $business, string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'business_id' => $business->id,
            'status' => 'active',
            'permissions' => [],
            'two_factor_secret' => 'test-secret',
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
