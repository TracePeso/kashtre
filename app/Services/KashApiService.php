<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Business;
use App\Models\ClientSpace;
use App\Models\Qualification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class KashApiService
{
    public function getStaff(array $params = []): array
    {
        $query = User::query()
            ->where('business_id', '!=', 1)
            ->with([
                'qualification:id,uuid,name',
                'department:id,uuid,name',
                'section:id,uuid,name',
                'title:id,uuid,name',
                'branch:id,uuid,name',
                'business:id,uuid,name',
            ]);

        if (! empty($params['business_id'])) {
            $businessId = (string) $params['business_id'];
            $query->where(function ($innerQuery) use ($businessId): void {
                $innerQuery->where('business_id', $businessId)
                    ->orWhereHas('business', fn ($businessQuery) => $businessQuery->where('uuid', $businessId));
            });
        }

        if (! empty($params['search'])) {
            $query->where('name', 'like', '%' . $params['search'] . '%');
        }

        if (! empty($params['email'])) {
            $query->where('email', $params['email']);
        }

        $paginator = $query
            ->latest()
            ->paginate(
                $this->perPage($params['per_page'] ?? 50),
                ['*'],
                'page',
                max(1, (int) ($params['page'] ?? 1))
            );

        $paginator->getCollection()->transform(fn (User $user): array => $this->staffPayload($user));

        return $this->paginationPayload($paginator);
    }

    public function getStaffByUuid(string $uuid): ?array
    {
        $user = User::query()
            ->where('uuid', $uuid)
            ->where('business_id', '!=', 1)
            ->with([
                'qualification:id,uuid,name',
                'department:id,uuid,name',
                'section:id,uuid,name',
                'title:id,uuid,name',
                'branch:id,uuid,name',
                'business:id,uuid,name',
            ])
            ->first();

        return $user ? $this->staffPayload($user) : null;
    }

    public function getBusinesses(): array
    {
        return Business::query()
            ->where('id', '!=', 1)
            ->select('id', 'uuid', 'name', 'email', 'phone', 'account_number')
            ->orderBy('name')
            ->get()
            ->map(fn (Business $business): array => [
                'id' => $business->id,
                'uuid' => $business->uuid,
                'external_business_uuid' => $business->uuid,
                'name' => $business->name,
                'email' => $business->email,
                'phone' => $business->phone,
                'account_number' => $business->account_number,
            ])
            ->all();
    }

    public function getBranches(array $params = []): array
    {
        return Branch::query()
            ->when(! empty($params['business_id']), function ($query) use ($params): void {
                $businessId = (string) $params['business_id'];
                $query->where(function ($innerQuery) use ($businessId): void {
                    $innerQuery->where('business_id', $businessId)
                        ->orWhereHas('business', fn ($businessQuery) => $businessQuery->where('uuid', $businessId));
                });
            })
            ->select('id', 'uuid', 'name', 'business_id', 'email', 'phone')
            ->with('business:id,uuid')
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $branch): array => [
                'id' => $branch->id,
                'uuid' => $branch->uuid,
                'name' => $branch->name,
                'business_id' => $branch->business_id,
                'business_uuid' => $branch->business?->uuid,
                'email' => $branch->email,
                'phone' => $branch->phone,
            ])
            ->all();
    }

    public function getClientSpaces(array $params = []): array
    {
        $query = ClientSpace::query()
            ->where('business_id', '!=', 1)
            ->with([
                'branch:id,uuid,name',
                'business:id,uuid',
            ]);

        if (! empty($params['business_id'])) {
            $businessId = (string) $params['business_id'];
            $query->where(function ($innerQuery) use ($businessId): void {
                $innerQuery->where('business_id', $businessId)
                    ->orWhereHas('business', fn ($businessQuery) => $businessQuery->where('uuid', $businessId));
            });
        }

        if (! empty($params['branch_id'])) {
            $branchId = (string) $params['branch_id'];
            $query->where(function ($innerQuery) use ($branchId): void {
                $innerQuery->where('branch_id', $branchId)
                    ->orWhereHas('branch', fn ($branchQuery) => $branchQuery->where('uuid', $branchId));
            });
        }

        $paginator = $query
            ->latest()
            ->paginate(
                $this->perPage($params['per_page'] ?? 50),
                ['*'],
                'page',
                max(1, (int) ($params['page'] ?? 1))
            );

        $paginator->getCollection()->transform(fn (ClientSpace $clientSpace): array => [
            'id' => $clientSpace->id,
            'uuid' => $clientSpace->uuid,
            'name' => $clientSpace->name,
            'description' => $clientSpace->description,
            'business_id' => $clientSpace->business_id,
            'business_uuid' => $clientSpace->business?->uuid,
            'branch_id' => $clientSpace->branch_id,
            'branch' => $clientSpace->branch ? [
                'id' => $clientSpace->branch->id,
                'uuid' => $clientSpace->branch->uuid,
                'name' => $clientSpace->branch->name,
            ] : null,
        ]);

        return $this->paginationPayload($paginator);
    }

    public function getQualifications(array $params = []): array
    {
        return Qualification::query()
            ->when(! empty($params['business_id']), function ($query) use ($params): void {
                $businessId = (string) $params['business_id'];
                $query->where(function ($innerQuery) use ($businessId): void {
                    $innerQuery->where('business_id', $businessId)
                        ->orWhereHas('business', fn ($businessQuery) => $businessQuery->where('uuid', $businessId));
                });
            })
            ->select('id', 'uuid', 'name', 'business_id')
            ->orderBy('name')
            ->get()
            ->map(fn (Qualification $qualification): array => [
                'id' => $qualification->id,
                'uuid' => $qualification->uuid,
                'name' => $qualification->name,
                'business_id' => $qualification->business_id,
            ])
            ->all();
    }

    public function clearCache(): void
    {
        // Local queries do not use an external sync cache.
    }

    private function staffPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'staff_uuid' => $user->staff_uuid,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'gender' => $user->gender,
            'status' => $user->status,
            'business_id' => $user->business_id,
            'branch_id' => $user->branch_id,
            'qualification_id' => $user->qualification_id,
            'department_id' => $user->department_id,
            'section_id' => $user->section_id,
            'title_id' => $user->title_id,
            'permissions' => (array) ($user->permissions ?? []),
            'hr_permissions' => User::filterHrPermissions($user->permissions ?? []),
            'created_at' => optional($user->created_at)?->toIso8601String(),
            'business' => $user->business ? [
                'id' => $user->business->id,
                'uuid' => $user->business->uuid,
                'name' => $user->business->name,
            ] : null,
            'branch' => $user->branch ? [
                'id' => $user->branch->id,
                'uuid' => $user->branch->uuid,
                'name' => $user->branch->name,
            ] : null,
            'qualification' => $user->qualification ? [
                'id' => $user->qualification->id,
                'uuid' => $user->qualification->uuid,
                'name' => $user->qualification->name,
            ] : null,
            'department' => $user->department ? [
                'id' => $user->department->id,
                'uuid' => $user->department->uuid,
                'name' => $user->department->name,
            ] : null,
            'section' => $user->section ? [
                'id' => $user->section->id,
                'uuid' => $user->section->uuid,
                'name' => $user->section->name,
            ] : null,
            'title' => $user->title ? [
                'id' => $user->title->id,
                'uuid' => $user->title->uuid,
                'name' => $user->title->name,
            ] : null,
        ];
    }

    private function paginationPayload(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'data' => $paginator->items(),
            'from' => $paginator->firstItem(),
            'last_page' => $paginator->lastPage(),
            'path' => '',
            'per_page' => $paginator->perPage(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
        ];
    }

    private function perPage(mixed $value): int
    {
        return max(1, min(100, (int) $value));
    }
}
