<?php

namespace App\Support;

use App\Models\Business;
use App\Models\InventoryModuleConfig;
use Illuminate\Support\Facades\Auth;

class InventoryBusinessContext
{
    public const SESSION_KEY = 'inventory_context_business_id';

    public static function isKashtreAdmin(): bool
    {
        return (int) (Auth::user()?->business_id ?? 0) === 1;
    }

    public static function hasContext(): bool
    {
        return self::isKashtreAdmin() && session()->has(self::SESSION_KEY);
    }

    public static function isAdminBrowsing(): bool
    {
        return self::hasContext();
    }

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

    public static function moduleConfig(): ?InventoryModuleConfig
    {
        return InventoryModuleConfig::query()
            ->where('business_id', self::effectiveBusinessId())
            ->where('is_active', true)
            ->first();
    }

    public static function moduleEnabled(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        if (self::isKashtreAdmin()) {
            return self::hasContext() && self::moduleConfig() !== null;
        }

        return InventoryModuleConfig::query()
            ->where('business_id', Auth::user()->business_id)
            ->where('is_active', true)
            ->exists();
    }

    public static function setContext(int $businessId): void
    {
        if (! self::isKashtreAdmin()) {
            abort(403);
        }

        if ($businessId === 1) {
            abort(422, 'Cannot browse inventory for the platform business.');
        }

        $enabled = InventoryModuleConfig::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->exists();

        if (! $enabled) {
            abort(422, 'The inventory module is not active for this organisation.');
        }

        session([self::SESSION_KEY => $businessId]);
    }

    public static function clearContext(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public static function assertWritable(): void
    {
        if (self::isAdminBrowsing()) {
            abort(403, 'This action is read-only while browsing another organisation\'s inventory.');
        }
    }

    public static function config(): ?InventoryModuleConfig
    {
        return self::moduleConfig();
    }

    public static function floorStockEnabled(): bool
    {
        return self::moduleConfig()?->floorStockEnabled() ?? true;
    }

    public static function crashCartEnabled(): bool
    {
        return self::moduleConfig()?->crashCartEnabled() ?? false;
    }

    public static function batchLotTrackingEnabled(): bool
    {
        return self::moduleConfig()?->batchLotTrackingEnabled() ?? false;
    }

    public static function serialNumberTrackingEnabled(): bool
    {
        return self::moduleConfig()?->serialNumberTrackingEnabled() ?? false;
    }
}
