<?php

return [
    /*
    | Shared Unit Engine (Main Module). Feature-flagged so Inventory can dual-run
    | against legacy item_units / suom_per_ouom until migration is complete.
    */
    'enabled' => (bool) env('UNIT_ENGINE_ENABLED', false),

    /*
    | When true, Inventory/Clinical/LIMS gateways rethrow ConversionException
    | instead of silently falling back to legacy multiply/identity.
    */
    'strict' => (bool) env('UNIT_ENGINE_STRICT', false),

    'calculation_scale' => (int) env('UNIT_ENGINE_CALCULATION_SCALE', 18),

    'max_composite_components' => (int) env('UNIT_ENGINE_MAX_COMPOSITE_COMPONENTS', 12),

    /*
    | System catalog tenant key used for pre-seeded platform units.
    | Business-scoped units use (string) business_id.
    */
    'system_tenant_key' => 'SYSTEM',
];
