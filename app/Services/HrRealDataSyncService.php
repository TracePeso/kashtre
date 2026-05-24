<?php

namespace App\Services;

use App\Models\ApprovalWorkflow;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Department;
use App\Models\HrClientSpace;
use App\Models\HrOrganizationalUnit;
use App\Models\Organization;
use App\Models\Section;
use App\Models\StaffAssignment;
use App\Models\Title;
use App\Models\User;
use App\Support\StaffRecordData;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class HrRealDataSyncService
{
    public function __construct(
        private readonly KashApiService $api,
        private readonly ?ClientSpaceRoutingService $clientSpaceRoutingService = null,
    )
    {
    }

    public function sync(?User $user = null, ?Organization $preferredOrganization = null): array
    {
        $businessScope = $this->businessScopeFor($user);

        $stats = $this->emptyStats();

        if ($businessScope !== null && empty($businessScope)) {
            return $stats;
        }

        $businesses = collect($this->api->getBusinesses())
            ->filter(fn (array $business): bool => filled($this->sourceId($business)))
            ->when(
                $businessScope !== null,
                fn (Collection $collection): Collection => $collection
                    ->filter(fn (array $business): bool => $this->businessMatchesScope($business, $businessScope))
            )
            ->values();

        if ($businessScope !== null && $businesses->isEmpty()) {
            return $stats;
        }

        $businessIds = $businessScope === null
            ? null
            : $businesses
                ->map(fn (array $business): ?string => $this->businessIdForApi($business))
                ->filter()
                ->unique()
                ->values()
                ->all();

        $staffRecords = $this->getAllStaff($businessIds);
        $branches = $this->getAllBranches($businessIds);
        $clientSpaces = $this->getAllClientSpaces($businessIds);
        $stats['businesses_seen'] = $businesses->count();
        $stats['branches_seen'] = count($branches);
        $stats['client_spaces_seen'] = count($clientSpaces);
        $preferredOrganizationId = $businessScope !== null && $businesses->count() === 1
            ? $preferredOrganization?->id
            : null;
        $localBusinessesByReference = $this->localBusinessesByReference($businesses);

        DB::transaction(function () use ($businesses, $branches, $clientSpaces, $staffRecords, $preferredOrganizationId, $localBusinessesByReference, &$stats): void {
            $organizationsByBusinessId = [];

            foreach ($businesses as $business) {
                $organization = $this->organizationForBusiness($business, $preferredOrganizationId);
                $stats['organizations']++;
                $stats['current_organization_id'] ??= $organization->id;

                foreach ($this->businessReferencesFromBusiness($business) as $businessReference) {
                    $organizationsByBusinessId[$businessReference] = $organization;
                }
            }

            $organizationsByBranchId = $this->organizationsByBranchId($branches, $organizationsByBusinessId);

            foreach ($clientSpaces as $clientSpace) {
                $organization = $this->organizationForClientSpace(
                    $clientSpace,
                    $organizationsByBusinessId,
                    $organizationsByBranchId
                );

                if (! $organization || ! $this->sourceId($clientSpace)) {
                    $stats['client_spaces_skipped']++;
                    continue;
                }

                $unit = $this->upsertClientSpace($organization, $clientSpace);

                $stats['client_spaces']++;

                if (! $unit->hasClientSpacePlacement()) {
                    $stats['client_spaces_unattached']++;
                }
            }

            foreach ($staffRecords as $staff) {
                $organization = $this->organizationForStaff($staff, $organizationsByBusinessId);
                $business = $this->localBusinessForStaff($staff, $organization, $localBusinessesByReference);
                $staffUuid = StaffRecordData::uuid($staff);

                if (! $organization || ! $staffUuid) {
                    continue;
                }

                $lookupIds = $business
                    ? $this->ensureLookupDataForStaff($business, $staff)
                    : ['department_id' => null, 'section_id' => null, 'title_id' => null, 'branch_id' => null];

                StaffAssignment::updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'staff_uuid' => $staffUuid,
                    ],
                    [
                        'staff_name' => StaffRecordData::name($staff) ?? 'Unnamed Staff',
                        'staff_cadre' => StaffRecordData::cadre($staff),
                        'staff_department' => StaffRecordData::department($staff),
                        'staff_title' => StaffRecordData::title($staff),
                        'home_branch_external_id' => StaffRecordData::branchExternalId($staff) ?? '',
                        'home_branch_name' => StaffRecordData::branchName($staff),
                        'assignment_type' => 'primary',
                        'status' => ($staff['status'] ?? 'active') === 'active' ? 'active' : 'inactive',
                        'assigned_at' => now(),
                    ]
                );
                $stats['staff_assignments']++;

                if ($this->upsertUser($staff, $business, $lookupIds)) {
                    $stats['users']++;
                }
            }

            foreach ($organizationsByBusinessId as $organization) {
                $stats['approval_workflows'] += $this->ensureApprovalWorkflows($organization);
            }
        });

        return $stats;
    }

    private function emptyStats(): array
    {
        return [
            'organizations' => 0,
            'staff_assignments' => 0,
            'users' => 0,
            'businesses_seen' => 0,
            'branches_seen' => 0,
            'client_spaces' => 0,
            'client_spaces_seen' => 0,
            'client_spaces_skipped' => 0,
            'client_spaces_unattached' => 0,
            'current_organization_id' => null,
            'approval_workflows' => 0,
        ];
    }

    private function getAllStaff(?array $businessIds = null): array
    {
        if ($businessIds === null) {
            return $this->getPagedStaff([]);
        }

        $staff = [];

        foreach ($businessIds as $businessId) {
            $staff = array_merge($staff, $this->getPagedStaff(['business_id' => $businessId]));
        }

        return $staff;
    }

    private function getAllBranches(?array $businessIds = null): array
    {
        if ($businessIds === null) {
            return $this->recordsFromResponse($this->api->getBranches());
        }

        $branches = [];

        foreach ($businessIds as $businessId) {
            $branches = array_merge($branches, $this->recordsFromResponse(
                $this->api->getBranches(['business_id' => $businessId])
            ));
        }

        return $branches;
    }

    private function getAllClientSpaces(?array $businessIds = null): array
    {
        if ($businessIds === null) {
            return $this->getPagedClientSpaces([]);
        }

        $clientSpaces = [];

        foreach ($businessIds as $businessId) {
            $clientSpaces = array_merge($clientSpaces, $this->getPagedClientSpaces(['business_id' => $businessId]));
        }

        return $clientSpaces ?: $this->getPagedClientSpaces([]);
    }

    private function getPagedStaff(array $baseParams): array
    {
        $staff = [];
        $page = 1;

        do {
            $response = $this->api->getStaff(array_merge($baseParams, [
                'per_page' => 100,
                'page' => $page,
                '_sync' => now()->timestamp,
            ]));
            $staff = array_merge($staff, $response['data'] ?? []);
            $lastPage = (int) ($response['last_page'] ?? $page);
            $page++;
        } while ($page <= $lastPage);

        return $staff;
    }

    private function getPagedClientSpaces(array $baseParams): array
    {
        $clientSpaces = [];
        $page = 1;

        do {
            $response = $this->api->getClientSpaces(array_merge($baseParams, [
                'per_page' => 100,
                'page' => $page,
                '_sync' => now()->timestamp,
            ]));

            if (is_array($response) && array_key_exists('data', $response)) {
                $records = $response['data'] ?? [];
                $lastPage = (int) ($response['last_page'] ?? $page);
            } else {
                $records = $response;
                $lastPage = $page;
            }

            if (Arr::isAssoc($records)) {
                $records = [$records];
            }

            $clientSpaces = array_merge($clientSpaces, array_filter($records, 'is_array'));
            $page++;
        } while ($page <= $lastPage);

        return $clientSpaces;
    }

    private function recordsFromResponse(array $response): array
    {
        $records = $response;

        if (array_key_exists('data', $response)) {
            $records = $response['data'] ?? [];
        }

        if (Arr::isAssoc($records)) {
            $records = [$records];
        }

        return array_values(array_filter($records, 'is_array'));
    }

    private function upsertClientSpace(Organization $organization, array $clientSpace): HrOrganizationalUnit
    {
        $externalUuid = (string) $this->sourceId($clientSpace);
        $sourceName = $clientSpace['name'] ?? 'Unnamed Client Space';

        $import = HrClientSpace::withTrashed()
            ->where('organization_id', $organization->id)
            ->where('external_uuid', $externalUuid)
            ->first();

        if ($import?->trashed()) {
            $import->restore();
        }

        $unit = $import
            ? $import->organizationalUnit()->withTrashed()->first()
            : null;

        if (! $unit) {
            $unit = new HrOrganizationalUnit();
        } elseif ($unit->trashed()) {
            $unit->restore();
        }

        $unit->fill([
            'organization_id' => $organization->id,
            'name' => $sourceName,
            'type' => 'Client Space',
            'unit_kind' => HrOrganizationalUnit::KIND_CLIENT_SPACE,
        ]);

        if (! $unit->exists || ! $this->hasValidClientSpaceParent($unit, $organization)) {
            $unit->parent_id = null;
        }

        $unit->save();
        $this->clientSpaceRoutingServiceInstance()->ensurePrimaryRouteConsistency($unit);

        $businessReferences = $this->businessReferencesFromClientSpace($clientSpace);
        $branchReferences = $this->branchReferencesFromClientSpace($clientSpace);

        $import ??= new HrClientSpace();
        $import->fill([
            'organization_id' => $organization->id,
            'organizational_unit_id' => $unit->id,
            'external_uuid' => $externalUuid,
            'external_business_uuid' => $businessReferences[0] ?? null,
            'external_branch_uuid' => $branchReferences[0] ?? null,
            'source_name' => $sourceName,
            'source_payload' => $clientSpace,
            'last_synced_at' => now(),
            'active' => true,
        ]);
        $import->save();

        return $unit;
    }

    private function clientSpaceRoutingServiceInstance(): ClientSpaceRoutingService
    {
        return $this->clientSpaceRoutingService ?? app(ClientSpaceRoutingService::class);
    }

    private function upsertUser(array $staff, ?Business $business = null, array $lookupIds = []): bool
    {
        $staffUuid = (string) ($staff['uuid'] ?? $staff['id'] ?? '');
        $email = $staff['email'] ?? null;
        $name = $staff['name'] ?? null;

        if (! $staffUuid || ! $email || ! $name) {
            return false;
        }

        $user = User::where('staff_uuid', $staffUuid)
            ->orWhere('uuid', $staffUuid)
            ->orWhere('email', $email)
            ->first() ?? new User();

        $user->name = $name;
        $user->email = $email;
        $user->staff_uuid = $user->staff_uuid ?: $staffUuid;
        $user->email_verified_at ??= now();
        $user->status = ($staff['status'] ?? 'active') === 'active' ? 'active' : 'inactive';

        if ($user->status === 'inactive') {
            $user->deactivated_at ??= now();
        } else {
            $user->deactivated_at = null;
        }

        if ($business) {
            $user->business_id = $business->id;
        }

        if (! empty($lookupIds['branch_id'])) {
            $user->branch_id = $lookupIds['branch_id'];
        }

        if (! empty($lookupIds['department_id'])) {
            $user->department_id = $lookupIds['department_id'];
        }

        if (! empty($lookupIds['section_id'])) {
            $user->section_id = $lookupIds['section_id'];
        }

        if (! empty($lookupIds['title_id'])) {
            $user->title_id = $lookupIds['title_id'];
        }

        if (array_key_exists('permissions', $staff) || array_key_exists('hr_permissions', $staff)) {
            $user->permissions = array_values(array_unique(array_merge(
                (array) ($user->permissions ?? []),
                User::filterHrPermissions($staff['hr_permissions'] ?? $staff['permissions'] ?? [])
            )));
        }

        if (! $user->exists) {
            $user->is_hr_admin = false;
            $user->permissions ??= [];
            $user->password = Hash::make(config('services.kashtre.synced_user_password'));
        }

        $user->save();

        return true;
    }

    private function localBusinessesByReference(Collection $businesses): array
    {
        $map = [];

        foreach ($businesses as $business) {
            if (! is_array($business)) {
                continue;
            }

            $localBusiness = $this->resolveLocalBusinessFromReferences($this->businessReferencesFromBusiness($business));

            if (! $localBusiness) {
                continue;
            }

            foreach ($this->businessReferencesFromBusiness($business) as $reference) {
                $map[(string) $reference] = $localBusiness;
            }
        }

        return $map;
    }

    private function localBusinessForStaff(array $staff, ?Organization $organization, array $localBusinessesByReference): ?Business
    {
        foreach ($this->businessReferencesFromStaff($staff) as $reference) {
            if (isset($localBusinessesByReference[(string) $reference])) {
                return $localBusinessesByReference[(string) $reference];
            }
        }

        if ($organization?->external_business_uuid) {
            return $this->resolveLocalBusinessFromReferences([(string) $organization->external_business_uuid]);
        }

        return null;
    }

    private function resolveLocalBusinessFromReferences(array $references): ?Business
    {
        foreach ($references as $reference) {
            if (! filled($reference)) {
                continue;
            }

            $query = Business::query();

            if (is_numeric($reference)) {
                $business = (clone $query)->whereKey((int) $reference)->first();

                if ($business) {
                    return $business;
                }
            }

            $business = $query->where('uuid', (string) $reference)->first();

            if ($business) {
                return $business;
            }
        }

        return null;
    }

    private function ensureLookupDataForStaff(Business $business, array $staff): array
    {
        $departmentId = $this->ensureDepartmentId($business, StaffRecordData::department($staff));
        $sectionId = $this->ensureSectionId($business, StaffRecordData::section($staff));
        $titleId = $this->ensureTitleId($business, StaffRecordData::title($staff));

        return [
            'branch_id' => $this->resolveBranchIdForStaff($business, $staff),
            'department_id' => $departmentId,
            'section_id' => $sectionId,
            'title_id' => $titleId,
        ];
    }

    private function resolveBranchIdForStaff(Business $business, array $staff): ?int
    {
        $branchReference = StaffRecordData::branchExternalId($staff);
        $branchName = StaffRecordData::branchName($staff);

        if (filled($branchReference)) {
            $query = Branch::query()->where('business_id', $business->id);

            if (is_numeric($branchReference)) {
                $branch = (clone $query)->whereKey((int) $branchReference)->first();

                if ($branch) {
                    return (int) $branch->id;
                }
            }

            $branch = $query->where('uuid', (string) $branchReference)->first();

            if ($branch) {
                return (int) $branch->id;
            }
        }

        if (filled($branchName)) {
            return Branch::query()
                ->where('business_id', $business->id)
                ->where('name', (string) $branchName)
                ->value('id');
        }

        return null;
    }

    private function ensureDepartmentId(Business $business, ?string $name): ?int
    {
        return $this->ensureLookupModelId(
            Department::class,
            $business->id,
            $name,
            'Synced department from HR staff data'
        );
    }

    private function ensureSectionId(Business $business, ?string $name): ?int
    {
        return $this->ensureLookupModelId(
            Section::class,
            $business->id,
            $name,
            'Synced section from HR staff data'
        );
    }

    private function ensureTitleId(Business $business, ?string $name): ?int
    {
        return $this->ensureLookupModelId(
            Title::class,
            $business->id,
            $name,
            'Synced title from HR staff data'
        );
    }

    private function ensureLookupModelId(string $modelClass, int $businessId, ?string $name, string $description): ?int
    {
        $label = trim((string) $name);

        if ($label === '') {
            return null;
        }

        $model = $modelClass::withTrashed()
            ->where('business_id', $businessId)
            ->where('name', $label)
            ->first();

        if (! $model) {
            $model = new $modelClass([
                'business_id' => $businessId,
                'name' => $label,
                'description' => $description,
            ]);
        } elseif (method_exists($model, 'trashed') && $model->trashed()) {
            $model->restore();
        }

        if (! $model->exists || blank($model->description)) {
            $model->fill(['description' => $model->description ?: $description]);
        }

        $model->save();

        return (int) $model->id;
    }

    private function ensureApprovalWorkflows(Organization $organization): int
    {
        $staff = StaffAssignment::where('organization_id', $organization->id)
            ->where('status', 'active')
            ->orderBy('staff_name')
            ->get(['staff_uuid', 'staff_name'])
            ->values();

        if ($staff->count() < 3) {
            return 0;
        }

        $categories = ['leave', 'roster', 'coverage', 'offsite_duty'];
        $updated = 0;

        foreach ($categories as $categoryIndex => $category) {
            $workflow = ApprovalWorkflow::withTrashed()
                ->where('organization_id', $organization->id)
                ->where('approval_category', $category)
                ->first();

            if ($workflow && $workflow->trashed()) {
                $workflow->restore();
            }

            $workflow ??= ApprovalWorkflow::create([
                'organization_id' => $organization->id,
                'approval_category' => $category,
                'is_active' => true,
            ]);

            $workflow->update(['is_active' => true]);

            $existingLevels = $workflow->approvers()->pluck('approver_level')->unique();
            if ($existingLevels->intersect(['primary', 'secondary', 'tertiary'])->count() === 3) {
                continue;
            }

            $workflow->approvers()->delete();

            foreach (['primary', 'secondary', 'tertiary'] as $levelIndex => $level) {
                $approver = $staff[($categoryIndex + $levelIndex) % $staff->count()];

                $workflow->approvers()->create([
                    'approver_level' => $level,
                    'approver_staff_uuid' => $approver->staff_uuid,
                    'approver_name' => $approver->staff_name,
                    'sort_order' => $levelIndex,
                ]);
            }

            $updated++;
        }

        return $updated;
    }

    private function sourceId(array $record): ?string
    {
        return isset($record['uuid']) ? (string) $record['uuid'] : (isset($record['id']) ? (string) $record['id'] : null);
    }

    private function businessScopeFor(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $businessReferences = [];

        if ($user->business?->uuid) {
            $businessReferences[] = (string) $user->business->uuid;
        }

        if ($user->staff_uuid) {
            StaffAssignment::query()
                ->with('organization')
                ->where('staff_uuid', $user->staff_uuid)
                ->get()
                ->each(function (StaffAssignment $assignment) use (&$businessReferences): void {
                    if ($assignment->organization?->external_business_uuid) {
                        $businessReferences[] = (string) $assignment->organization->external_business_uuid;
                    }
                });

            $staff = $this->normalizeStaffRecord($this->api->getStaffByUuid($user->staff_uuid));
            if ($staff) {
                $businessReferences = array_merge($businessReferences, $this->businessReferencesFromStaff($staff));
            }
        }

        if (empty($businessReferences) && $user->email) {
            $staff = $this->staffRecordForEmail($user->email);

            if ($staff) {
                $businessReferences = array_merge($businessReferences, $this->businessReferencesFromStaff($staff));
                $this->rememberUserStaffUuid($user, $staff);
            }
        }

        return collect($businessReferences)
            ->filter(fn ($reference): bool => filled($reference))
            ->map(fn ($reference): string => (string) $reference)
            ->unique()
            ->values()
            ->all();
    }

    private function staffRecordForEmail(string $email): ?array
    {
        $response = $this->api->getStaff([
            'email' => $email,
            'per_page' => 5,
        ]);

        $records = Arr::get($response, 'data', []);

        if (! $records && is_array($response) && Arr::isAssoc($response) && isset($response['email'])) {
            $records = [$response];
        }

        if (Arr::isAssoc($records)) {
            $records = [$records];
        }

        foreach ($records as $staff) {
            if (! is_array($staff)) {
                continue;
            }

            if (strtolower((string) ($staff['email'] ?? '')) === strtolower($email)) {
                return $staff;
            }
        }

        return null;
    }

    private function rememberUserStaffUuid(User $user, array $staff): void
    {
        $staffUuid = (string) ($staff['uuid'] ?? $staff['id'] ?? '');

        if (! $staffUuid || $user->staff_uuid === $staffUuid) {
            return;
        }

        if (User::where('staff_uuid', $staffUuid)->whereKeyNot($user->getKey())->exists()) {
            return;
        }

        $user->forceFill(['staff_uuid' => $staffUuid])->save();
    }

    private function normalizeStaffRecord(?array $response): ?array
    {
        if (! $response) {
            return null;
        }

        $data = $response['data'] ?? null;

        if (is_array($data) && Arr::isAssoc($data)) {
            return $data;
        }

        return $response;
    }

    private function organizationForStaff(array $staff, array $organizationsByBusinessId): ?Organization
    {
        foreach ($this->businessReferencesFromStaff($staff) as $businessReference) {
            if (isset($organizationsByBusinessId[$businessReference])) {
                return $organizationsByBusinessId[$businessReference];
            }
        }

        return null;
    }

    private function organizationForClientSpace(
        array $clientSpace,
        array $organizationsByBusinessId,
        array $organizationsByBranchId
    ): ?Organization {
        foreach ($this->businessReferencesFromClientSpace($clientSpace) as $businessReference) {
            if (isset($organizationsByBusinessId[$businessReference])) {
                return $organizationsByBusinessId[$businessReference];
            }
        }

        foreach ($this->branchReferencesFromClientSpace($clientSpace) as $branchReference) {
            if (isset($organizationsByBranchId[$branchReference])) {
                return $organizationsByBranchId[$branchReference];
            }
        }

        if (
            empty($this->businessReferencesFromClientSpace($clientSpace))
            && empty($this->branchReferencesFromClientSpace($clientSpace))
        ) {
            return $this->singleOrganizationFromMap($organizationsByBusinessId);
        }

        return null;
    }

    private function organizationsByBranchId(array $branches, array $organizationsByBusinessId): array
    {
        $organizationsByBranchId = [];

        foreach ($branches as $branch) {
            $organization = null;

            foreach ($this->businessReferencesFromBranch($branch) as $businessReference) {
                if (isset($organizationsByBusinessId[$businessReference])) {
                    $organization = $organizationsByBusinessId[$businessReference];
                    break;
                }
            }

            if (! $organization) {
                continue;
            }

            foreach ($this->branchReferencesFromBranch($branch) as $branchReference) {
                $organizationsByBranchId[$branchReference] = $organization;
            }
        }

        return $organizationsByBranchId;
    }

    private function singleOrganizationFromMap(array $organizationsByBusinessId): ?Organization
    {
        $organizations = collect($organizationsByBusinessId)
            ->filter(fn ($organization): bool => $organization instanceof Organization)
            ->unique(fn (Organization $organization): int => $organization->id)
            ->values();

        return $organizations->count() === 1
            ? $organizations->first()
            : null;
    }

    private function hasValidClientSpaceParent(HrOrganizationalUnit $unit, Organization $organization): bool
    {
        return $unit->parent_id
            && HrOrganizationalUnit::where('organization_id', $organization->id)
                ->routingNodes()
                ->whereKey($unit->parent_id)
                ->exists();
    }

    private function businessMatchesScope(array $business, array $businessScope): bool
    {
        return collect($this->businessReferencesFromBusiness($business))
            ->intersect($businessScope)
            ->isNotEmpty();
    }

    private function businessReferencesFromBusiness(array $business): array
    {
        $references = [];

        foreach (['id', 'uuid', 'external_business_uuid'] as $key) {
            if (filled($business[$key] ?? null)) {
                $references[] = (string) $business[$key];
            }
        }

        if ($sourceId = $this->sourceId($business)) {
            $references[] = $sourceId;
        }

        return collect($references)->unique()->values()->all();
    }

    private function businessReferencesFromStaff(array $staff): array
    {
        $references = [];

        foreach (['business_id', 'business_uuid', 'organization_id', 'organisation_id', 'organization_uuid', 'organisation_uuid'] as $key) {
            if (filled($staff[$key] ?? null)) {
                $references[] = (string) $staff[$key];
            }
        }

        foreach (['business', 'organization', 'organisation'] as $relation) {
            $record = $staff[$relation] ?? null;

            if (! is_array($record)) {
                continue;
            }

            foreach (['id', 'uuid'] as $key) {
                if (filled($record[$key] ?? null)) {
                    $references[] = (string) $record[$key];
                }
            }
        }

        return collect($references)->unique()->values()->all();
    }

    private function businessReferencesFromClientSpace(array $clientSpace): array
    {
        $references = [];

        foreach (['business_id', 'business_uuid', 'organization_id', 'organisation_id', 'organization_uuid', 'organisation_uuid'] as $key) {
            if (filled($clientSpace[$key] ?? null)) {
                $references[] = (string) $clientSpace[$key];
            }
        }

        foreach (['business', 'organization', 'organisation'] as $relation) {
            $record = $clientSpace[$relation] ?? null;

            if (! is_array($record)) {
                continue;
            }

            foreach (['id', 'uuid'] as $key) {
                if (filled($record[$key] ?? null)) {
                    $references[] = (string) $record[$key];
                }
            }
        }

        $branch = $clientSpace['branch'] ?? null;
        if (is_array($branch)) {
            foreach (['business_id', 'business_uuid', 'organization_id', 'organisation_id', 'organization_uuid', 'organisation_uuid'] as $key) {
                if (filled($branch[$key] ?? null)) {
                    $references[] = (string) $branch[$key];
                }
            }

            foreach (['business', 'organization', 'organisation'] as $relation) {
                $record = $branch[$relation] ?? null;

                if (! is_array($record)) {
                    continue;
                }

                foreach (['id', 'uuid'] as $key) {
                    if (filled($record[$key] ?? null)) {
                        $references[] = (string) $record[$key];
                    }
                }
            }
        }

        return collect($references)->unique()->values()->all();
    }

    private function businessReferencesFromBranch(array $branch): array
    {
        $references = [];

        foreach (['business_id', 'business_uuid', 'organization_id', 'organisation_id', 'organization_uuid', 'organisation_uuid'] as $key) {
            if (filled($branch[$key] ?? null)) {
                $references[] = (string) $branch[$key];
            }
        }

        foreach (['business', 'organization', 'organisation'] as $relation) {
            $record = $branch[$relation] ?? null;

            if (! is_array($record)) {
                continue;
            }

            foreach (['id', 'uuid'] as $key) {
                if (filled($record[$key] ?? null)) {
                    $references[] = (string) $record[$key];
                }
            }
        }

        return collect($references)->unique()->values()->all();
    }

    private function branchReferencesFromBranch(array $branch): array
    {
        $references = [];

        foreach (['id', 'uuid', 'branch_id', 'branch_uuid'] as $key) {
            if (filled($branch[$key] ?? null)) {
                $references[] = (string) $branch[$key];
            }
        }

        return collect($references)->unique()->values()->all();
    }

    private function branchReferencesFromClientSpace(array $clientSpace): array
    {
        $references = [];

        foreach (['branch_id', 'branch_uuid'] as $key) {
            if (filled($clientSpace[$key] ?? null)) {
                $references[] = (string) $clientSpace[$key];
            }
        }

        $branch = $clientSpace['branch'] ?? null;

        if (is_array($branch)) {
            foreach (['id', 'uuid', 'branch_id', 'branch_uuid'] as $key) {
                if (filled($branch[$key] ?? null)) {
                    $references[] = (string) $branch[$key];
                }
            }
        }

        return collect($references)->unique()->values()->all();
    }

    private function businessIdForApi(array $business): ?string
    {
        return isset($business['id']) ? (string) $business['id'] : $this->sourceId($business);
    }

    private function organizationForBusiness(array $business, ?int $preferredOrganizationId = null): Organization
    {
        if ($preferredOrganizationId) {
            $preferredOrganization = Organization::find($preferredOrganizationId);

            if ($preferredOrganization) {
                $preferredOrganization->update(['name' => $business['name']]);

                return $preferredOrganization;
            }
        }

        return Organization::updateOrCreate(
            ['external_business_uuid' => $this->sourceId($business)],
            ['name' => $business['name']]
        );
    }
}
