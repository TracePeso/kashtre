<?php

namespace Tests\Feature;

use App\Livewire\Items\SimpleItems;
use App\Models\Business;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class SimpleItemsBusinessScopeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hospital_user_query_is_limited_to_their_business(): void
    {
        [$user, $ownItem, $otherItem] = $this->hospitalUserWithForeignItem();

        $this->actingAs($user);

        $component = Livewire::test(SimpleItems::class)->instance();
        $this->assertInstanceOf(SimpleItems::class, $component);

        $query = $component->getFilteredTableQuery();

        $this->assertTrue($query->clone()->whereKey($ownItem->id)->exists());
        $this->assertFalse($query->clone()->whereKey($otherItem->id)->exists());
        $this->assertFalse($query->clone()->where('business_id', '!=', $user->business_id)->exists());
        $this->assertFalse($this->businessColumnVisible($component));
    }

    public function test_kashtre_admin_can_see_the_business_column(): void
    {
        $admin = User::query()->where('business_id', 1)->where('status', 'active')->first();
        if (! $admin) {
            $this->markTestSkipped('A Kashtre admin user is required.');
        }

        $this->actingAs($admin);

        $component = Livewire::test(SimpleItems::class)->instance();
        $this->assertTrue($this->businessColumnVisible($component));
    }

    private function businessColumnVisible(SimpleItems $component): bool
    {
        $column = $component->getTable()->getColumn('business.name');

        return $column !== null && $column->isVisible();
    }

    /**
     * @return array{0: User, 1: Item, 2: Item}
     */
    private function hospitalUserWithForeignItem(): array
    {
        $home = Business::query()->where('entity_code', 'MCC')->first()
            ?? Business::query()->where('id', '!=', 1)->first();
        $other = Business::query()->where('entity_code', 'CCTH')->first()
            ?? Business::query()->where('id', '!=', 1)->where('id', '!=', $home?->id)->first();

        if (! $home || ! $other) {
            $this->markTestSkipped('Two hospital businesses are required.');
        }

        $ownItem = Item::query()
            ->where('business_id', $home->id)
            ->whereIn('type', ['service', 'good'])
            ->latest('id')
            ->first();
        $otherItem = Item::query()
            ->where('business_id', $other->id)
            ->whereIn('type', ['service', 'good'])
            ->latest('id')
            ->first();

        if (! $ownItem || ! $otherItem) {
            $this->markTestSkipped('Simple items in two businesses are required.');
        }

        $user = User::factory()->create([
            'name' => 'Scope Staff',
            'email' => 'scope.staff.'.uniqid().'@example.test',
            'business_id' => $home->id,
            'status' => 'active',
            'permissions' => ['View Items'],
        ]);

        return [$user, $ownItem, $otherItem];
    }
}
