<?php

namespace Tests\Feature;

use App\Models\HrOrganizationalUnit;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrRoutingStructureBuildCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_routing_structure_keeps_two_immediate_children_under_the_root_and_preserves_ops_branches(): void
    {
        $organization = Organization::create([
            'name' => 'Generated Routing Org',
            'external_business_uuid' => 'generated-routing-org',
            'weekend_days' => [0, 6],
        ]);

        $this->artisan('hr:build-routing-structure', [
            '--organization-id' => $organization->id,
            '--fresh' => true,
            '--force' => true,
        ])->assertExitCode(0);

        $rootNode = HrOrganizationalUnit::query()
            ->where('organization_id', $organization->id)
            ->where('source_type', 'generated_organogram_root')
            ->first();

        $this->assertNotNull($rootNode);

        $rootChildren = HrOrganizationalUnit::query()
            ->where('organization_id', $organization->id)
            ->where('parent_id', $rootNode->id)
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();

        $this->assertSame(['Clinical Services', 'Corporate Services'], $rootChildren);

        $corporateServices = HrOrganizationalUnit::query()
            ->where('organization_id', $organization->id)
            ->where('parent_id', $rootNode->id)
            ->where('name', 'Corporate Services')
            ->first();

        $this->assertNotNull($corporateServices);

        $corporateChildren = HrOrganizationalUnit::query()
            ->where('organization_id', $organization->id)
            ->where('parent_id', $corporateServices->id)
            ->pluck('name')
            ->all();

        $this->assertContains('ICT & Records', $corporateChildren);
        $this->assertContains('Facilities & Security', $corporateChildren);
    }
}
