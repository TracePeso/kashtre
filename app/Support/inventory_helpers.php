<?php

if (! function_exists('inventory_label')) {
    /**
     * Tenant terminology override. Falls back to platform defaults.
     *
     * @param  'client'|'client_space'|'item'|'store'|'usage_record'  $key
     */
    function inventory_label(string $key, ?int $businessId = null): string
    {
        $defaults = [
            'client' => 'Client',
            'client_space' => 'Client Space',
            'item' => 'Inventory Item',
            'store' => 'Store',
            'usage_record' => 'Usage Record',
        ];

        $businessId = $businessId
            ?? (\App\Support\InventoryBusinessContext::effectiveBusinessId() ?: null);

        if (! $businessId) {
            return $defaults[$key] ?? Str::title(str_replace('_', ' ', $key));
        }

        static $cache = [];
        if (! array_key_exists($businessId, $cache)) {
            $cache[$businessId] = \App\Models\InventoryModuleConfig::query()
                ->where('business_id', $businessId)
                ->value('label_dictionary') ?? [];
        }

        $dict = is_array($cache[$businessId]) ? $cache[$businessId] : [];
        $override = trim((string) ($dict[$key] ?? ''));

        return $override !== '' ? $override : ($defaults[$key] ?? \Illuminate\Support\Str::title(str_replace('_', ' ', $key)));
    }
}
