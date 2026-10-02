<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\OrgAssignment;
use App\Models\OrgUnit;
use App\Models\ReportingRelationship;
use App\Models\StaffDeployment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrOrganizationController extends Controller
{
    /**
     * GET /api/org-units?business_id=
     */
    public function orgUnits(Request $request): JsonResponse
    {
        $query = OrgUnit::query()
            ->with($this->orgUnitRelations())
            ->orderBy('org_level')
            ->orderBy('name');

        $this->applyBusinessFilter($query, $request);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', (int) $request->input('branch_id'));
        }

        if ($request->filled('external_id')) {
            $query->where('external_id', $request->string('external_id')->toString());
        }

        if ($request->filled('parent_external_id')) {
            $parentExternalId = $request->string('parent_external_id')->toString();
            $query->whereHas('parent', fn (Builder $parent) => $parent->where('external_id', $parentExternalId));
        }

        $this->applyBooleanFilter($query, $request, 'is_terminal', 'is_terminal');

        return response()->json(
            $query->get()->map(fn (OrgUnit $unit) => $this->orgUnitPayload($unit))->values()
        );
    }

    /**
     * GET /api/org-units/{uuid|external_id}
     */
    public function orgUnitShow(string $orgUnit): JsonResponse
    {
        return response()->json($this->orgUnitPayload($this->findOrgUnit($orgUnit)));
    }

    /**
     * GET /api/assignments?business_id=
     */
    public function assignments(Request $request): JsonResponse
    {
        $query = OrgAssignment::query()
            ->with($this->assignmentRelations())
            ->orderBy('hierarchy_level')
            ->orderBy('external_id');

        $this->applyBusinessFilter($query, $request);
        $this->applyUserFilter($query, $request);
        $this->applyBooleanFilter($query, $request, 'is_primary', 'is_primary');
        $this->applyBooleanFilter($query, $request, 'roster_eligible', 'roster_eligible');

        if ($request->filled('external_id')) {
            $query->where('external_id', $request->string('external_id')->toString());
        }

        if ($request->filled('org_unit_external_id')) {
            $externalId = $request->string('org_unit_external_id')->toString();
            $query->whereHas('orgUnit', fn (Builder $unit) => $unit->where('external_id', $externalId));
        }

        return response()->json(
            $query->get()->map(fn (OrgAssignment $assignment) => $this->assignmentPayload($assignment))->values()
        );
    }

    /**
     * GET /api/assignments/{uuid|external_id}
     */
    public function assignmentShow(string $assignment): JsonResponse
    {
        return response()->json($this->assignmentPayload($this->findAssignment($assignment)));
    }

    /**
     * GET /api/reporting-relationships?business_id=
     */
    public function reportingRelationships(Request $request): JsonResponse
    {
        $query = ReportingRelationship::query()
            ->with($this->reportingRelations())
            ->orderBy('hierarchy_level')
            ->orderBy('id');

        $this->applyBusinessFilter($query, $request);
        $this->applyUserFilter($query, $request, 'subjectUser');

        if ($request->filled('assignment_external_id')) {
            $externalId = $request->string('assignment_external_id')->toString();
            $query->whereHas(
                'subjectAssignment',
                fn (Builder $assignment) => $assignment->where('external_id', $externalId)
            );
        }

        return response()->json(
            $query->get()->map(fn (ReportingRelationship $row) => $this->reportingPayload($row))->values()
        );
    }

    /**
     * GET /api/deployments?business_id=
     */
    public function deployments(Request $request): JsonResponse
    {
        $query = StaffDeployment::query()
            ->with($this->deploymentRelations())
            ->orderBy('external_id');

        $this->applyBusinessFilter($query, $request);
        $this->applyUserFilter($query, $request);

        if ($request->filled('external_id')) {
            $query->where('external_id', $request->string('external_id')->toString());
        }

        if ($request->filled('client_space_id')) {
            $query->where('client_space_id', (int) $request->input('client_space_id'));
        }

        if ($request->filled('client_space_external_id')) {
            $query->where('client_space_external_id', $request->string('client_space_external_id')->toString());
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', (int) $request->input('branch_id'));
        }

        return response()->json(
            $query->get()->map(fn (StaffDeployment $deployment) => $this->deploymentPayload($deployment))->values()
        );
    }

    private function findOrgUnit(string $key): OrgUnit
    {
        return OrgUnit::query()
            ->with($this->orgUnitRelations())
            ->where(fn (Builder $query) => $query->where('uuid', $key)->orWhere('external_id', $key))
            ->firstOrFail();
    }

    private function findAssignment(string $key): OrgAssignment
    {
        return OrgAssignment::query()
            ->with($this->assignmentRelations())
            ->where(fn (Builder $query) => $query->where('uuid', $key)->orWhere('external_id', $key))
            ->firstOrFail();
    }

    /**
     * @param  Builder<OrgUnit>|Builder<OrgAssignment>|Builder<ReportingRelationship>|Builder<StaffDeployment>  $query
     */
    private function applyBusinessFilter(Builder $query, Request $request): void
    {
        if ($request->filled('business_id')) {
            $query->where('business_id', (int) $request->input('business_id'));
        }
    }

    /**
     * @param  Builder<OrgAssignment>|Builder<ReportingRelationship>|Builder<StaffDeployment>  $query
     */
    private function applyUserFilter(Builder $query, Request $request, string $relation = 'user'): void
    {
        if ($request->filled('user_id')) {
            $query->where($relation === 'user' ? 'user_id' : 'subject_user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('user_uuid')) {
            $uuid = $request->string('user_uuid')->toString();
            $query->whereHas($relation, fn (Builder $user) => $user->where('uuid', $uuid));
        }
    }

    private function applyBooleanFilter(Builder $query, Request $request, string $param, string $column): void
    {
        if (! $request->exists($param) || $request->input($param) === null || $request->input($param) === '') {
            return;
        }

        $query->where($column, filter_var($request->input($param), FILTER_VALIDATE_BOOLEAN));
    }

    /**
     * @return list<string>
     */
    private function orgUnitRelations(): array
    {
        return [
            'branch:id,uuid,name',
            'department:id,uuid,name',
            'parent:id,uuid,external_id,name',
            'headUser:id,uuid,name',
        ];
    }

    /**
     * @return list<string>
     */
    private function assignmentRelations(): array
    {
        return [
            'user:id,uuid,name,email,employee_code',
            'orgUnit:id,uuid,external_id,name',
            'terminalOrgUnit:id,uuid,external_id,name',
            'department:id,uuid,name',
            'branch:id,uuid,name',
            'clientSpace:id,uuid,name',
        ];
    }

    /**
     * @return list<string>
     */
    private function reportingRelations(): array
    {
        return [
            'subjectUser:id,uuid,name',
            'subjectAssignment:id,uuid,external_id,position_title',
            'lineManagerAssignment:id,uuid,external_id,position_title,user_id',
            'lineManagerAssignment.user:id,uuid,name',
            'approvalParentAssignment:id,uuid,external_id',
            'approvalTerminalAssignment:id,uuid,external_id',
        ];
    }

    /**
     * @return list<string>
     */
    private function deploymentRelations(): array
    {
        return [
            'user:id,uuid,name',
            'assignment:id,uuid,external_id',
            'orgUnit:id,uuid,external_id,name',
            'branch:id,uuid,name',
            'clientSpace:id,uuid,name',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orgUnitPayload(OrgUnit $unit): array
    {
        return [
            'id' => $unit->id,
            'uuid' => $unit->uuid,
            'external_id' => $unit->external_id,
            'name' => $unit->name,
            'org_unit_type' => $unit->org_unit_type,
            'department_id' => $unit->department_id,
            'department' => $this->namedRef($unit->department),
            'head_name' => $unit->head_name,
            'head_user_id' => $unit->head_user_id,
            'head_user' => $unit->headUser ? [
                'id' => $unit->headUser->id,
                'uuid' => $unit->headUser->uuid,
                'name' => $unit->headUser->name,
            ] : null,
            'head_assignment_id' => $unit->head_assignment_id,
            'head_assignment_external_id' => $unit->head_assignment_external_id,
            'org_level' => $unit->org_level,
            'is_terminal' => $unit->is_terminal,
            'org_path' => $unit->org_path,
            'business_id' => $unit->business_id,
            'branch_id' => $unit->branch_id,
            'branch' => $this->namedRef($unit->branch),
            'parent_id' => $unit->parent_id,
            'parent_external_id' => $unit->parent?->external_id,
            'parent' => $unit->parent ? [
                'id' => $unit->parent->id,
                'uuid' => $unit->parent->uuid,
                'external_id' => $unit->parent->external_id,
                'name' => $unit->parent->name,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function assignmentPayload(OrgAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'uuid' => $assignment->uuid,
            'external_id' => $assignment->external_id,
            'business_id' => $assignment->business_id,
            'user_id' => $assignment->user_id,
            'user' => $assignment->user ? [
                'id' => $assignment->user->id,
                'uuid' => $assignment->user->uuid,
                'name' => $assignment->user->name,
                'email' => $assignment->user->email,
                'employee_code' => $assignment->user->employee_code,
            ] : null,
            'position_title' => $assignment->position_title,
            'organizational_home' => $assignment->orgUnit?->name,
            'org_unit_id' => $assignment->org_unit_id,
            'org_unit' => $this->orgRef($assignment->orgUnit),
            'terminal_node' => $assignment->terminalOrgUnit?->name,
            'terminal_org_unit_id' => $assignment->terminal_org_unit_id,
            'terminal_org_unit' => $this->orgRef($assignment->terminalOrgUnit),
            'department_id' => $assignment->department_id,
            'department' => $this->namedRef($assignment->department),
            'assignment_type' => $assignment->assignment_type,
            'is_primary' => $assignment->is_primary,
            'hierarchy_level' => $assignment->hierarchy_level,
            'roster_eligible' => $assignment->roster_eligible,
            'client_space_id' => $assignment->client_space_id,
            'client_space_label' => $assignment->client_space_label,
            'client_space' => $this->namedRef($assignment->clientSpace),
            'branch_id' => $assignment->branch_id,
            'branch' => $this->namedRef($assignment->branch),
            'scope_note' => $assignment->scope_note,
            'effective_from' => $assignment->effective_from?->toDateString(),
            'effective_to' => $assignment->effective_to?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reportingPayload(ReportingRelationship $row): array
    {
        $manager = $row->lineManagerAssignment;

        return [
            'id' => $row->id,
            'uuid' => $row->uuid,
            'business_id' => $row->business_id,
            'subject_user_id' => $row->subject_user_id,
            'subject_user' => $row->subjectUser ? [
                'id' => $row->subjectUser->id,
                'uuid' => $row->subjectUser->uuid,
                'name' => $row->subjectUser->name,
            ] : null,
            'subject_assignment_id' => $row->subject_assignment_id,
            'subject_assignment' => $row->subjectAssignment ? [
                'id' => $row->subjectAssignment->id,
                'uuid' => $row->subjectAssignment->uuid,
                'external_id' => $row->subjectAssignment->external_id,
                'position_title' => $row->subjectAssignment->position_title,
            ] : null,
            'line_manager_assignment_id' => $row->line_manager_assignment_id,
            'line_manager_assignment_external_id' => $manager?->external_id,
            'manager' => $manager?->user ? [
                'id' => $manager->user->id,
                'uuid' => $manager->user->uuid,
                'name' => $manager->user->name,
                'position_title' => $manager->position_title,
            ] : null,
            'approval_parent_assignment_id' => $row->approval_parent_assignment_id,
            'approval_parent_assignment_external_id' => $row->approvalParentAssignment?->external_id,
            'approval_terminal_assignment_id' => $row->approval_terminal_assignment_id,
            'approval_terminal_assignment_external_id' => $row->approvalTerminalAssignment?->external_id,
            'hierarchy_level' => $row->hierarchy_level,
            'approval_route' => $row->approval_route,
            'approval_path_names' => $row->approval_path_names,
            'approval_path_external_ids' => $row->approval_path_external_ids ?? [],
            'approval_depth' => $row->approval_depth,
            'notes' => $row->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deploymentPayload(StaffDeployment $deployment): array
    {
        return [
            'id' => $deployment->id,
            'uuid' => $deployment->uuid,
            'external_id' => $deployment->external_id,
            'business_id' => $deployment->business_id,
            'user_id' => $deployment->user_id,
            'user' => $deployment->user ? [
                'id' => $deployment->user->id,
                'uuid' => $deployment->user->uuid,
                'name' => $deployment->user->name,
            ] : null,
            'org_assignment_id' => $deployment->org_assignment_id,
            'assignment_external_id' => $deployment->assignment?->external_id,
            'position_title' => $deployment->position_title,
            'org_unit_id' => $deployment->org_unit_id,
            'terminal_node' => $this->orgRef($deployment->orgUnit),
            'branch_id' => $deployment->branch_id,
            'branch' => $this->namedRef($deployment->branch),
            'client_space_id' => $deployment->client_space_id,
            'client_space_external_id' => $deployment->client_space_external_id,
            'client_space' => $this->namedRef($deployment->clientSpace),
            'allocation_percent' => (float) $deployment->allocation_percent,
            'effective_from' => $deployment->effective_from?->toDateString(),
            'effective_to' => $deployment->effective_to?->toDateString(),
            'purpose' => $deployment->purpose,
        ];
    }

    /**
     * @return array{id: int, uuid: string, name: string}|null
     */
    private function namedRef(?object $model): ?array
    {
        if ($model === null) {
            return null;
        }

        return [
            'id' => $model->id,
            'uuid' => $model->uuid,
            'name' => $model->name,
        ];
    }

    /**
     * @return array{id: int, uuid: string, external_id: string, name: string}|null
     */
    private function orgRef(?OrgUnit $unit): ?array
    {
        if ($unit === null) {
            return null;
        }

        return [
            'id' => $unit->id,
            'uuid' => $unit->uuid,
            'external_id' => $unit->external_id,
            'name' => $unit->name,
        ];
    }
}
