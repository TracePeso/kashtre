<?php

namespace App\Services;

use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class KashtreEntityService
{
    /**
     * Kashtre super-admin business id — not a customer entity.
     */
    public const SUPER_BUSINESS_ID = 1;

    /**
     * Base query for all organisations onboarded on Kashtre.
     */
    public function query(): Builder
    {
        return Business::query()
            ->kashtreEntities()
            ->with(['country:id,name,iso_code,currency_code']);
    }

    /**
     * Entities with at least one active staff account (signals current use).
     */
    public function activelyUtilizingQuery(): Builder
    {
        return $this->query()->activelyUtilizing();
    }

    /**
     * Entities flagged as network suppliers during onboarding.
     */
    public function registeredSuppliersQuery(): Builder
    {
        return $this->query()->registeredAsSupplier();
    }

    public function findByUuid(string $uuid): ?Business
    {
        return $this->query()->where('uuid', $uuid)->first();
    }

    public function findByAccountNumber(string $accountNumber): ?Business
    {
        return $this->query()->where('account_number', $accountNumber)->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function toRegistryArray(Business $business): array
    {
        $business->loadMissing(['country', 'inventoryModuleConfig']);
        $business->loadCount([
            'users as active_staff_count' => fn ($query) => $query->where('status', 'active'),
        ]);

        return [
            'id' => $business->id,
            'uuid' => $business->uuid,
            'name' => $business->name,
            'email' => $business->email,
            'phone' => $business->phone,
            'address' => $business->address,
            'account_number' => $business->account_number,
            'entity_code' => $business->entity_code,
            'registered_as_supplier' => $business->isRegisteredAsSupplier(),
            'utilizes_kashtre' => true,
            'actively_utilizing' => ($business->active_staff_count ?? 0) > 0,
            'active_staff_count' => (int) ($business->active_staff_count ?? 0),
            'inventory_module_active' => (bool) $business->inventoryModuleConfig?->is_active,
            'country' => $business->country ? [
                'id' => $business->country->id,
                'name' => $business->country->name,
                'iso_code' => $business->country->iso_code,
                'currency_code' => $business->country->currency_code,
            ] : null,
            'onboarded_at' => $business->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function registry(
        ?bool $registeredAsSupplier = null,
        ?bool $activelyUtilizing = null,
        ?string $search = null,
    ): Collection {
        $query = $this->query()->withCount([
            'users as active_staff_count' => fn ($q) => $q->where('status', 'active'),
        ])->with('inventoryModuleConfig:id,business_id,is_active');

        if ($registeredAsSupplier === true) {
            $query->registeredAsSupplier();
        } elseif ($registeredAsSupplier === false) {
            $query->where('registered_as_supplier', false);
        }

        if ($activelyUtilizing === true) {
            $query->activelyUtilizing();
        } elseif ($activelyUtilizing === false) {
            $query->whereDoesntHave('users', fn ($q) => $q->where('status', 'active'));
        }

        if ($search) {
            $term = '%' . $search . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('account_number', 'like', $term)
                    ->orWhere('entity_code', 'like', $term);
            });
        }

        return $query->orderBy('name')->get()->map(fn (Business $business) => $this->toRegistryArray($business));
    }
}
