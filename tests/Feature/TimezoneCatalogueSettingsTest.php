<?php

namespace Tests\Feature;

use App\Domain\Time\Models\CoreTimeZone;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TimezoneCatalogueSettingsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_kashtre_admin_can_view_timezone_settings(): void
    {
        $this->actingAs($this->makeUser($this->kashtreBusiness(), 'tz.settings.admin@example.com'));

        $this->get(route('settings.timezones.index'))
            ->assertOk()
            ->assertSee('Settings - Timezones')
            ->assertSee('Add Timezone');
    }

    public function test_platform_time_console_redirects_to_timezone_settings(): void
    {
        $this->actingAs($this->makeUser($this->kashtreBusiness(), 'tz.settings.redirect@example.com'));

        $this->get('/platform/time')
            ->assertRedirect('/settings/timezones');
    }

    public function test_kashtre_admin_can_add_a_timezone_to_the_catalogue(): void
    {
        $this->actingAs($this->makeUser($this->kashtreBusiness(), 'tz.settings.add@example.com'));

        $iana = 'Pacific/Pago_Pago';
        CoreTimeZone::query()->where('iana_id', $iana)->delete();

        $this->post(route('settings.timezones.store'), [
            'iana_id' => $iana,
            'display_name' => 'Pago Pago',
        ])->assertRedirect(route('settings.timezones.index'));

        $this->assertDatabaseHas('core_time_zones', [
            'iana_id' => $iana,
            'display_name' => 'Pago Pago',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_non_kashtre_user_cannot_manage_timezones(): void
    {
        $this->actingAs($this->makeUser($this->hospitalBusiness(), 'tz.settings.hospital@example.com'));

        $this->get(route('settings.timezones.index'))->assertForbidden();
        $this->post(route('settings.timezones.store'), [
            'iana_id' => 'UTC',
        ])->assertForbidden();
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

    private function makeUser(Business $business, string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'business_id' => $business->id,
            'status' => 'active',
            'permissions' => [],
        ]);
    }
}
