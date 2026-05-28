<?php

namespace Tests\Feature;

use App\Livewire\OrganizationalStructure;
use App\Livewire\TierStaffAssignmentManager;
use App\Models\HrOrganizationTierLevel;
use App\Models\HrOrganizationalUnit;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HrFinalNodePromptFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_final_node_redirects_into_last_node_client_space_setup(): void
    {
        $user = User::factory()->create([
            'permissions' => ['Add HR Setup'],
        ]);

        $organization = Organization::create([
            'name' => 'Prompt Flow Org',
            'external_business_uuid' => 'prompt-flow-org',
            'weekend_days' => [0, 6],
        ]);

        $rootLevel = HrOrganizationTierLevel::create([
            'organization_id' => $organization->id,
            'name' => 'Branch',
            'level_order' => 1,
        ]);

        $finalLevel = HrOrganizationTierLevel::create([
            'organization_id' => $organization->id,
            'name' => 'Section',
            'level_order' => 2,
        ]);

        $rootNode = HrOrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => null,
            'tier_level_id' => $rootLevel->id,
            'name' => 'Branch',
            'type' => 'Branch',
            'unit_kind' => HrOrganizationalUnit::KIND_ROUTING_NODE,
        ]);

        $this->actingAs($user);
        $this->withSession(['current_organization_id' => $organization->id]);

        $component = Livewire::test(TierStaffAssignmentManager::class)
            ->set('selectedTierId', $rootNode->id)
            ->set('newSubtierTierLevelId', $finalLevel->id)
            ->call('createSubtier');

        $finalNode = HrOrganizationalUnit::query()
            ->where('organization_id', $organization->id)
            ->where('parent_id', $rootNode->id)
            ->where('tier_level_id', $finalLevel->id)
            ->first();

        $this->assertNotNull($finalNode);

        $component->assertRedirect(route('hr.organizational-structure.index', [
            'prompt_leaf_unit' => $finalNode->id,
            'prompt_leaf_action' => 'client-spaces',
        ]));
    }

    public function test_saving_last_node_client_spaces_immediately_opens_staff_assignment_prompt(): void
    {
        $user = User::factory()->create([
            'permissions' => ['Add HR Setup'],
        ]);

        $organization = Organization::create([
            'name' => 'Leaf Staff Prompt Org',
            'external_business_uuid' => 'leaf-staff-prompt-org',
            'weekend_days' => [0, 6],
        ]);

        $rootLevel = HrOrganizationTierLevel::create([
            'organization_id' => $organization->id,
            'name' => 'Division',
            'level_order' => 1,
        ]);

        $leafLevel = HrOrganizationTierLevel::create([
            'organization_id' => $organization->id,
            'name' => 'Unit',
            'level_order' => 2,
        ]);

        HrOrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => null,
            'tier_level_id' => $rootLevel->id,
            'name' => 'Division',
            'type' => 'Division',
            'unit_kind' => HrOrganizationalUnit::KIND_ROUTING_NODE,
        ]);

        $leafNode = HrOrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => null,
            'tier_level_id' => $leafLevel->id,
            'name' => 'Unit',
            'type' => 'Unit',
            'unit_kind' => HrOrganizationalUnit::KIND_ROUTING_NODE,
        ]);

        $clientSpace = HrOrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => null,
            'tier_level_id' => null,
            'name' => 'Ward A',
            'type' => 'Client Space',
            'unit_kind' => HrOrganizationalUnit::KIND_CLIENT_SPACE,
        ]);

        $this->actingAs($user);
        $this->withSession(['current_organization_id' => $organization->id]);

        Livewire::test(OrganizationalStructure::class)
            ->call('openLeafClientSpacesModal', $leafNode->id)
            ->set('selectedLeafClientSpaceIds', [$clientSpace->id])
            ->call('saveLeafClientSpaces')
            ->assertSet('showLeafClientSpacesModal', false)
            ->assertSet('showLeafStaffModal', true)
            ->assertSet('selectedLeafUnitId', $leafNode->id)
            ->assertSet('selectedLeafTargetClientSpaceIds', [$clientSpace->id])
            ->assertSet('autoPromptLeafStaffAssignments', true);
    }
}
