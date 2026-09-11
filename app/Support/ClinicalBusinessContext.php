<?php

namespace App\Support;

use App\Models\Business;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Auth;

/**
 * Which business the Kashtre super admin is currently configuring.
 *
 * Business 1 is the platform, not a facility — it oversees every other
 * business, so its administrators need to set each facility's clinical
 * dictionaries rather than only their own. Everyone else is pinned to their own
 * business and never sees a picker.
 *
 * Follows InventoryBusinessContext's session-key convention deliberately, with
 * one difference worth stating: Inventory's assertWritable() makes admin
 * browsing read-only. Here it is writable, because configuring a facility's
 * units, reason codes and wards on its behalf is the entire purpose.
 */
class ClinicalBusinessContext
{
    public const SESSION_KEY = 'clinical_context_business_id';

    public static function isKashtreAdmin(): bool
    {
        return (int) (Auth::user()?->business_id ?? 0) === 1;
    }

    public static function hasContext(): bool
    {
        return self::isKashtreAdmin() && session()->has(self::SESSION_KEY);
    }

    /**
     * The business whose dictionaries are being read or written — and therefore
     * the tenant presented to the Clinical Module.
     */
    public static function effectiveBusinessId(): int
    {
        $user = Auth::user();

        if (! $user) {
            abort(403);
        }

        if (self::isKashtreAdmin()) {
            $contextId = (int) session(self::SESSION_KEY, 0);

            if ($contextId > 0) {
                return $contextId;
            }
        }

        return (int) $user->business_id;
    }

    public static function contextBusiness(): ?Business
    {
        if (! self::hasContext()) {
            return null;
        }

        return Business::query()->find((int) session(self::SESSION_KEY));
    }

    /**
     * A super admin must choose a facility first. Without this the screen would
     * silently read tenant "1" — the platform — and show an empty dictionary
     * that looks like a facility with nothing configured.
     */
    public static function requiresSelection(): bool
    {
        return self::isKashtreAdmin() && ! self::hasContext();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Business>
     */
    public static function selectableBusinesses()
    {
        return Business::query()
            ->where('id', '!=', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'entity_code']);
    }

    public static function setContext(int $businessId): void
    {
        if (! self::isKashtreAdmin()) {
            abort(403);
        }

        if ($businessId === 1) {
            abort(422, 'Choose a facility — the platform business has no clinical dictionaries of its own.');
        }

        if (! Business::query()->whereKey($businessId)->exists()) {
            abort(404, 'That business does not exist.');
        }

        session([self::SESSION_KEY => $businessId]);
    }

    public static function clearContext(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * An actor carrying the business being configured rather than the admin's
     * own, so the gateway presents the right tenant. The user id stays the real
     * one — the audit trail must name who made the change, not the facility.
     */
    public static function actor(): ClinicalActor
    {
        $user = Auth::user();

        if (! $user) {
            abort(403);
        }

        return new ClinicalActor(
            userId: (int) $user->id,
            businessId: self::effectiveBusinessId(),
            branchId: $user->branch_id ? (int) $user->branch_id : null,
            name: (string) $user->name,
            permissions: (array) ($user->permissions ?? []),
        );
    }
}
