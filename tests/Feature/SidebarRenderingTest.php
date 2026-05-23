<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Business;
use App\Models\CallingModuleConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class SidebarRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_renders_for_calling_module_users_without_view_exceptions(): void
    {
        $business = $this->createNonSystemBusiness();
        $branch = Branch::create([
            'business_id' => $business->id,
            'name' => 'Main Branch',
            'email' => 'branch@example.com',
            'address' => 'Kampala',
        ]);

        $user = User::factory()->create([
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'permissions' => ['View Callers', 'Broadcast Announcements'],
        ]);

        CallingModuleConfig::create([
            'business_id' => $business->id,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $html = Blade::render('<x-app.sidebar variant="v2" />');

        $this->assertStringContainsString('Calling Service', $html);
        $this->assertStringContainsString('Public Announcements', $html);
    }

    public function test_sidebar_hides_calling_group_without_calling_permissions(): void
    {
        $business = $this->createNonSystemBusiness();
        $branch = Branch::create([
            'business_id' => $business->id,
            'name' => 'Main Branch',
            'email' => 'branch2@example.com',
            'address' => 'Kampala',
        ]);

        $user = User::factory()->create([
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'permissions' => ['View Dashboard'],
        ]);

        CallingModuleConfig::create([
            'business_id' => $business->id,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $html = Blade::render('<x-app.sidebar variant="v2" />');

        $this->assertStringNotContainsString('Calling Service', $html);
        $this->assertStringNotContainsString('Public Announcements', $html);
    }

    private function createNonSystemBusiness(): Business
    {
        Business::create([
            'name' => 'System Business',
            'email' => 'system@example.com',
            'address' => 'Kampala',
            'account_number' => 'SYS-001',
        ]);

        return Business::create([
            'name' => 'Test Business',
            'email' => 'test-business@example.com',
            'address' => 'Kampala',
            'account_number' => 'BIZ-001',
        ]);
    }
}
