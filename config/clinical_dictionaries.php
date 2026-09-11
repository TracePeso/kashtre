<?php

/**
 * The Clinical Module's configuration dictionaries, as presented in
 * Settings → Clinical Dictionaries.
 *
 * One entry per dictionary. Adding a dictionary that Clinical publishes later
 * costs an entry here rather than a new screen — which matters because these
 * are explicitly tenant-configurable and the list grows.
 *
 * Each entry:
 *   group    section heading in the picker
 *   label    what an administrator calls it
 *   path     the Clinical API path, relative to /api/v1/
 *   about    one line explaining what breaks if it is wrong
 *   columns  field => heading, in table order
 *   fields   the create/edit form. `required` mirrors Clinical's own
 *            validation, discovered from its 422 responses rather than
 *            guessed, so the form refuses what the API would refuse.
 *   readonly true where Clinical publishes no store/update
 *
 * Field types: text | number | textarea | boolean
 */
return [

    'units-of-measure' => [
        'group' => 'Measurement',
        'label' => 'Units of measure',
        'path' => 'settings/dictionaries/units-of-measure',
        'about' => 'Every observation is normalised to a CDE base unit on write. A missing unit means a result arrives unconvertible and is skipped rather than charted.',
        'columns' => ['id' => 'ID', 'unit_label' => 'Label', 'category' => 'Category'],
        'fields' => [
            'unit_label' => ['label' => 'Unit label', 'type' => 'text', 'required' => true, 'help' => 'UCUM-aligned, e.g. mmol/L'],
            'category' => ['label' => 'Category', 'type' => 'text', 'required' => true, 'help' => 'e.g. MASS, VOLUME, CONCENTRATION'],
        ],
    ],

    'reason-codes' => [
        'group' => 'Measurement',
        'label' => 'Reason codes',
        'path' => 'settings/dictionaries/reason-codes',
        'about' => 'Wastage, CDSS override, break-glass and cancellation reasons. A reviewer reading the audit trail sees these strings, so vague codes make an override unauditable.',
        'columns' => ['id' => 'ID', 'category_code' => 'Category', 'reason_code' => 'Code', 'display_label' => 'Label'],
        'fields' => [
            'category_code' => ['label' => 'Category', 'type' => 'text', 'required' => true, 'help' => 'e.g. MAR_WASTAGE, CDSS_OVERRIDE, BREAK_GLASS'],
            'reason_code' => ['label' => 'Reason code', 'type' => 'text', 'required' => true],
            'display_label' => ['label' => 'Display label', 'type' => 'text', 'required' => true],
            'requires_free_text' => ['label' => 'Requires a written justification', 'type' => 'boolean', 'help' => 'Use for reasons that are not self-explanatory — "OVERRIDE_OTHER" with no note is an audit record nobody can act on.'],
        ],
    ],

    'routes-and-frequencies' => [
        'group' => 'Measurement',
        'label' => 'Routes and frequencies',
        'path' => 'settings/dictionaries/routes-and-frequencies',
        'about' => 'Populates the prescribing pickers. Frequencies carry the interval the MAR scheduler expands into individual doses.',
        'columns' => ['id' => 'ID', 'code' => 'Code', 'display_label' => 'Label', 'type' => 'Type', 'minute_interval' => 'Interval (min)'],
        'fields' => [
            'type' => ['label' => 'Type', 'type' => 'text', 'required' => true, 'help' => 'ROUTE or FREQUENCY'],
            'code' => ['label' => 'Code', 'type' => 'text', 'required' => true, 'help' => 'e.g. PO, IV, TID, STAT'],
            'display_label' => ['label' => 'Display label', 'type' => 'text', 'required' => true],
            'minute_interval' => ['label' => 'Interval (minutes)', 'type' => 'number', 'help' => 'Frequencies only — the interval the MAR scheduler expands doses on.'],
        ],
    ],

    'scoring-models' => [
        'group' => 'Measurement',
        'label' => 'Scoring models',
        'path' => 'settings/dictionaries/scoring-models',
        'about' => 'NEWS2, SATS, APGAR, GCS and the rest. The matrices decide when a patient is escalated.',
        'columns' => ['id' => 'ID', 'score_code' => 'Code', 'score_name' => 'Name', 'version' => 'Version'],
        'fields' => [
            'score_code' => ['label' => 'Score code', 'type' => 'text', 'required' => true, 'help' => 'e.g. NEWS2, SATS, APGAR, GCS'],
            'score_name' => ['label' => 'Score name', 'type' => 'text', 'required' => true],
            'version' => ['label' => 'Version', 'type' => 'text', 'required' => true],
            'matrix_payload' => ['label' => 'Matrix (JSON)', 'type' => 'json', 'required' => true, 'help' => 'The scoring matrix as a JSON object.'],
        ],
    ],

    'escalation-rules' => [
        'group' => 'Measurement',
        'label' => 'Escalation rules',
        'path' => 'settings/dictionaries/escalation-rules',
        'about' => 'Which score band alerts whom, and how fast. Wrong tiers mean a deteriorating patient is escalated late.',
        'columns' => ['id' => 'ID', 'tier_code' => 'Tier', 'display_label' => 'Label'],
        // Confirmed genuinely read-only by probing directly: POST, PUT and
        // PATCH on the collection all return 405. Unlike the five dictionaries
        // this file used to mismark the same way, this one really is Clinical's
        // to change.
        'readonly' => true,
    ],

    'cde-registry' => [
        'group' => 'Clinical data',
        'label' => 'CDE registry',
        'path' => 'settings/cde-registry',
        'about' => 'The atomic observations that can be charted. A result whose CDE is not registered cannot be recorded at all.',
        'columns' => ['id' => 'ID', 'cde_code' => 'Code', 'cde_name' => 'Name', 'data_type' => 'Type', 'base_uom_id' => 'Base unit'],
        'fields' => [
            'cde_code' => ['label' => 'CDE code', 'type' => 'text', 'required' => true, 'help' => 'e.g. PULSE_RATE, GLUCOSE_RANDOM'],
            'cde_name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
            'data_type' => ['label' => 'Data type', 'type' => 'text', 'required' => true, 'help' => 'NUMERIC, CODED or TEXT'],
            'base_uom_id' => ['label' => 'Base unit id', 'type' => 'number', 'required' => true, 'help' => 'From Units of measure — values are normalised to this on write.'],
        ],
    ],

    'observation-schedules' => [
        'group' => 'Clinical data',
        'label' => 'Observation schedules',
        'path' => 'settings/observation-schedules',
        'about' => 'How often a given observation is expected. Drives the compliance view that shows a missed round.',
        'columns' => ['id' => 'ID', 'schedule_code' => 'Code', 'schedule_name' => 'Name', 'cde_code' => 'CDE', 'interval_minutes' => 'Every (min)'],
        'fields' => [
            'schedule_code' => ['label' => 'Schedule code', 'type' => 'text', 'required' => true],
            'schedule_name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
            'group_id' => ['label' => 'Group id', 'type' => 'number', 'required' => true],
            'cde_code' => ['label' => 'CDE code', 'type' => 'text', 'required' => true],
            'interval_minutes' => ['label' => 'Interval (minutes)', 'type' => 'number', 'required' => true],
        ],
    ],

    'terminology-map' => [
        'group' => 'Clinical data',
        'label' => 'Terminology map',
        'path' => 'settings/terminology-map',
        'about' => 'Maps our codes onto external systems. An unverified mapping is the silent-failure shape the verified flag exists to prevent.',
        'columns' => ['id' => 'ID', 'internal_domain' => 'Domain', 'internal_code' => 'Internal code', 'system_uri' => 'System', 'code' => 'External code', 'is_verified' => 'Verified'],
        'fields' => [
            'internal_domain' => ['label' => 'Internal domain', 'type' => 'text', 'required' => true],
            'internal_code' => ['label' => 'Internal code', 'type' => 'text', 'required' => true],
            'system_uri' => ['label' => 'System URI', 'type' => 'text', 'required' => true],
            'code' => ['label' => 'External code', 'type' => 'text', 'required' => true],
            'is_verified' => ['label' => 'Verified', 'type' => 'boolean'],
            'verified_by' => ['label' => 'Verified by', 'type' => 'text', 'help' => 'Required when marking verified — a verified mapping with nobody attributed is refused.'],
        ],
    ],

    'client-spaces' => [
        'group' => 'Ward structure',
        'label' => 'Wards, rooms and bays',
        'path' => 'settings/client-spaces',
        'about' => 'The ward structure the census board renders. sub_store_id is the Room-to-Store mapping that routes a patient\'s medication consumption to the right sub-store.',
        'columns' => ['id' => 'ID', 'ward_code' => 'Ward code', 'ward_name' => 'Ward', 'building_wing' => 'Wing', 'room_number' => 'Room', 'sub_store_id' => 'Sub-store', 'beds_count' => 'Beds'],
        'fields' => [
            'ward_code' => ['label' => 'Ward code', 'type' => 'text', 'required' => true, 'help' => 'Rooms sharing a code roll up into one ward, e.g. ICU'],
            'ward_name' => ['label' => 'Ward name', 'type' => 'text', 'required' => true],
            'building_wing' => ['label' => 'Building wing', 'type' => 'text'],
            'room_number' => ['label' => 'Room number', 'type' => 'text'],
            'sub_store_id' => ['label' => 'Sub-store id', 'type' => 'text', 'help' => 'Room-to-Store mapping for medication consumption.'],
        ],
    ],

    'care-teams' => [
        'group' => 'Ward structure',
        'label' => 'Care teams',
        'path' => 'settings/care-teams',
        'about' => 'Team-model care assignment. A clinician on the team inherits access to the team\'s patients.',
        'columns' => ['id' => 'ID', 'team_code' => 'Code', 'team_name' => 'Name', 'specialty' => 'Specialty'],
        'fields' => [
            'team_code' => ['label' => 'Team code', 'type' => 'text', 'required' => true],
            'team_name' => ['label' => 'Team name', 'type' => 'text', 'required' => true],
            'specialty' => ['label' => 'Specialty', 'type' => 'text', 'required' => true],
        ],
    ],

    'order-sets' => [
        'group' => 'Orders and safety',
        'label' => 'Order sets',
        'path' => 'settings/order-sets',
        'about' => 'Bundles a clinician applies in one action instead of typing each order.',
        'columns' => ['id' => 'ID', 'set_code' => 'Code', 'set_name' => 'Name'],
        'fields' => [
            'set_code' => ['label' => 'Set code', 'type' => 'text', 'required' => true],
            'set_name' => ['label' => 'Set name', 'type' => 'text', 'required' => true],
        ],
    ],

    'work-order-rules' => [
        'group' => 'Orders and safety',
        'label' => 'Work order rules',
        'path' => 'settings/work-order-rules',
        'about' => 'Raises ad-hoc ward tasks automatically off a trigger. For ward work only — lab and imaging requests must go through the order endpoints or they skip the safety shield and LIMS dispatch.',
        'columns' => ['id' => 'ID', 'rule_code' => 'Code', 'trigger_event' => 'Trigger', 'order_type' => 'Type', 'order_name' => 'Task', 'assigned_role_code' => 'Role'],
        'fields' => [
            'rule_code' => ['label' => 'Rule code', 'type' => 'text', 'required' => true],
            'trigger_event' => ['label' => 'Trigger event', 'type' => 'text', 'required' => true],
            'order_type' => ['label' => 'Order type', 'type' => 'text', 'required' => true],
            'order_name' => ['label' => 'Task name', 'type' => 'text', 'required' => true],
            'assigned_role_code' => ['label' => 'Assigned role', 'type' => 'text', 'required' => true],
        ],
    ],

    'cdss-interactions' => [
        'group' => 'Orders and safety',
        'label' => 'CDSS drug interactions',
        'path' => 'settings/cdss/interactions',
        'about' => 'The deterministic safety shield. A hard block here is what stops a dangerous prescription reaching a patient.',
        'columns' => ['id' => 'ID', 'drug_a' => 'Drug A', 'drug_b' => 'Drug B', 'severity' => 'Severity', 'description' => 'Description'],
        'fields' => [
            'drug_a' => ['label' => 'Drug A', 'type' => 'text', 'required' => true],
            'drug_b' => ['label' => 'Drug B', 'type' => 'text', 'required' => true],
            // Clinical rejects free-text here (validated against a fixed set)
            // but its error doesn't enumerate the accepted values — confirmed
            // CRITICAL/MAJOR/MINOR/SEVERE/CONTRAINDICATED/HIGH/LOW are all
            // rejected. Left as free text rather than guessing a wrong list;
            // Clinical's own error message will name the real one.
            'severity' => ['label' => 'Severity', 'type' => 'text', 'required' => true, 'help' => 'Exact value required by Clinical — ask them for the accepted set if this is refused.'],
            'description' => ['label' => 'Description', 'type' => 'textarea', 'required' => true],
        ],
    ],

    'process-registry' => [
        'group' => 'Workflow',
        'label' => 'Clinical transitions',
        'path' => 'settings/process-registry',
        'about' => 'Admission, transfer, discharge, referral and death certification. Steps are ordered and role-owned, and some carry real effects like allocating a bed or locking a chart. A facility gets its own copy of the five reference workflows the moment it is provisioned — see Settings → Clinical Module for the provisioning status.',
        'columns' => ['id' => 'ID', 'process_code' => 'Code', 'process_name' => 'Name', 'description' => 'Description', 'is_active' => 'Active'],
        // Still not a flat create/update-by-id resource — POST creates the
        // header, a separate PUT wholesale-replaces its steps. Header
        // GET-by-id + PATCH + activate/deactivate now work (re-confirmed
        // 2026-08-15; they 404'd when this was first probed), but the steps
        // array still can't go through a flat field=>value form, so create
        // stays on the dedicated component below. Editing an existing
        // process's header/steps still has no UI — only create.
        'custom_form' => 'clinical.process-registry-form',
    ],

    'recall-rules' => [
        'group' => 'Workflow',
        'label' => 'Recall rules',
        'path' => 'settings/recall-rules',
        'about' => 'Chronic follow-up. Wrong offsets mean a patient is recalled at the wrong interval, or not at all.',
        'columns' => ['id' => 'ID', 'rule_code' => 'Code', 'rule_name' => 'Name', 'trigger_event' => 'Trigger', 'recall_type' => 'Type'],
        'fields' => [
            'rule_code' => ['label' => 'Rule code', 'type' => 'text', 'required' => true],
            'rule_name' => ['label' => 'Rule name', 'type' => 'text', 'required' => true],
            'trigger_event' => ['label' => 'Trigger event', 'type' => 'text', 'required' => true],
            'offsets_days' => ['label' => 'Offsets (days)', 'type' => 'text', 'required' => true, 'help' => 'Comma-separated, e.g. 30,90,180'],
            'recall_type' => ['label' => 'Recall type', 'type' => 'text', 'required' => true],
        ],
    ],

    'maternity-options' => [
        'group' => 'Workflow',
        'label' => 'Maternity options',
        'path' => 'settings/maternity-options',
        'about' => 'Delivery modes, birth outcomes and APGAR components shown while recording a birth.',
        'columns' => ['id' => 'ID', 'option_type' => 'Type', 'code' => 'Code', 'display_label' => 'Label'],
        'fields' => [
            'option_type' => ['label' => 'Option type', 'type' => 'text', 'required' => true, 'help' => 'e.g. DELIVERY_MODE, BIRTH_OUTCOME'],
            'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
            'display_label' => ['label' => 'Display label', 'type' => 'text', 'required' => true],
        ],
    ],

    'ai-cde-map' => [
        'group' => 'Workflow',
        'label' => 'AI to CDE mapping',
        'path' => 'settings/ai-cde-map',
        'about' => 'Maps what the AI gateway extracts onto registered CDEs. Nothing is charted without a clinician accepting it, so an unmapped term is a missed suggestion, not a wrong record.',
        'columns' => ['id' => 'ID', 'gateway_code' => 'Gateway term', 'cde_code' => 'CDE code'],
        'fields' => [
            'gateway_code' => ['label' => 'Gateway term', 'type' => 'text', 'required' => true],
            'cde_code' => ['label' => 'CDE code', 'type' => 'text', 'required' => true, 'help' => 'Must match an existing entry in the CDE registry — Clinical rejects an unknown code.'],
        ],
    ],

    'module-aliases' => [
        'group' => 'Workflow',
        'label' => 'Module aliases',
        'path' => 'settings/dictionaries/module-aliases',
        'about' => 'How other modules name the same thing. Wrong aliases are why an integration silently matches nothing.',
        'columns' => ['id' => 'ID', 'module_code' => 'Module code', 'display_name' => 'Display name'],
        'fields' => [
            'module_code' => ['label' => 'Module code', 'type' => 'text', 'required' => true],
            'display_name' => ['label' => 'Display name', 'type' => 'text', 'required' => true],
        ],
    ],
];
