<?php

namespace App\Support\Clinical;

/**
 * Every route in §16 of the Clinical Module API Integration Guide, as data.
 *
 * This is the manifest `php artisan clinical:probe` walks to report which
 * endpoints the deployed service actually honours — the fastest way to see how
 * much of the contract is real versus documented.
 *
 * Each entry:
 *   method     HTTP verb
 *   path       relative to {CLINICAL_MODULE_URL}/api/v1/
 *   group      the §16 heading it appears under
 *   auth       P patient-gated · Z ZTNA-evaluated · S service key · H HMAC · - public
 *   safe       true when calling it changes nothing, so the probe may call it
 *   note       why it is interesting, where that is not obvious
 *
 * Path parameters use {braces} and are substituted from the probe's sample
 * values. An endpoint whose parameters cannot be filled is reported as SKIPPED
 * rather than guessed at — a probe that invents a patient id is testing the
 * 404 handler, not the endpoint.
 */
class ClinicalEndpointCatalog
{
    public const AUTH_PUBLIC = '-';

    public const AUTH_PATIENT = 'P';

    public const AUTH_ZTNA = 'Z';

    public const AUTH_SERVICE = 'S';

    public const AUTH_HMAC = 'H';

    /**
     * @return array<int, array{method: string, path: string, group: string, auth: string, safe: bool, note?: string}>
     */
    public static function all(): array
    {
        return array_merge(
            self::health(),
            self::patientChart(),
            self::ordersMarConsumption(),
            self::bedsTasksTransitions(),
            self::diagnosticsTelemetryScores(),
            self::aiInteropMaternityRecall(),
            self::securityDevicesAudit(),
            self::webhooks(),
            self::settings(),
            self::fhir(),
        );
    }

    /**
     * Endpoints the probe is allowed to call because they change nothing.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function safe(): array
    {
        return array_values(array_filter(self::all(), fn (array $e) => $e['safe']));
    }

    /**
     * @return array<int, string>
     */
    public static function groups(): array
    {
        return array_values(array_unique(array_column(self::all(), 'group')));
    }

    public static function count(): int
    {
        return count(self::all());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function health(): array
    {
        return [
            self::e('GET', 'health', 'Health', self::AUTH_PUBLIC, true,
                'Deliberately unauthenticated so load balancers can reach it. 503 + status=degraded when a check fails.'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function patientChart(): array
    {
        $g = 'Patient chart';

        return [
            self::e('POST', 'clinical/observations', $g, self::AUTH_PATIENT, false,
                'Normalises to the CDE base unit; refuses physiologically impossible values 422.'),
            self::e('GET', 'clinical/patients/{patientId}/observations', $g, self::AUTH_PATIENT, true,
                'display_uom_id re-scales alert boundaries along with the values.'),
            self::e('GET', 'clinical/patients/{patientId}/observation-compliance', $g, self::AUTH_PATIENT, true),
            self::e('POST', 'clinical/patients/{patientId}/observation-compliance/refresh', $g, self::AUTH_PATIENT, false),
            self::e('GET', 'clinical/patients/{patientId}/care-team', $g, self::AUTH_PATIENT, true),
            self::e('GET', 'clinical/patients/{patientId}/allergies', $g, self::AUTH_PATIENT, true,
                'Feeds the CDSS DRUG_ALLERGY hard block.'),
            self::e('POST', 'clinical/patients/{patientId}/allergies', $g, self::AUTH_PATIENT, false),
            self::e('PATCH', 'clinical/patients/{patientId}/allergies/{allergy}', $g, self::AUTH_PATIENT, false),
            self::e('GET', 'clinical/patients/{patientId}/diagnoses', $g, self::AUTH_PATIENT, true),
            self::e('POST', 'clinical/diagnoses', $g, self::AUTH_PATIENT, false),
            self::e('PATCH', 'clinical/diagnoses/{diagnosis}', $g, self::AUTH_PATIENT, false),
            self::e('GET', 'clinical/patients/{patientId}/immunizations', $g, self::AUTH_PATIENT, true),
            self::e('POST', 'clinical/immunizations', $g, self::AUTH_PATIENT, false),
            self::e('GET', 'clinical/patients/{patientId}/entitlements', $g, self::AUTH_PATIENT, true),
            self::e('GET', 'clinical/patients/{patientId}/work-orders', $g, self::AUTH_PATIENT, true),
            self::e('POST', 'clinical/patients/{patientId}/care-pathways', $g, self::AUTH_PATIENT, false),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function ordersMarConsumption(): array
    {
        $g = 'Orders, MAR, consumption';

        return [
            self::e('POST', 'clinical/orders/medications', $g, self::AUTH_PATIENT, false,
                'Blocked entirely until Main exposes the catalogue lookup (§14).'),
            self::e('POST', 'clinical/orders/laboratory', $g, self::AUTH_PATIENT, false),
            self::e('POST', 'clinical/orders/imaging', $g, self::AUTH_PATIENT, false),
            self::e('POST', 'clinical/orders/procedures', $g, self::AUTH_PATIENT, false),
            self::e('GET', 'clinical/patients/{patientId}/orders', $g, self::AUTH_PATIENT, true),
            self::e('POST', 'clinical/orders/translate', $g, self::AUTH_ZTNA, false,
                'Dry-run catalogue resolution — the cheapest check that §14 is wired.'),
            self::e('POST', 'clinical/orders/{order}/cancel', $g, self::AUTH_ZTNA, false),
            self::e('POST', 'clinical/orders/{order}/redispatch', $g, self::AUTH_ZTNA, false),
            self::e('POST', 'clinical/cdss/evaluate', $g, self::AUTH_PATIENT, false,
                'Dry run: returns a verdict rather than refusing.'),
            self::e('GET', 'clinical/cdss/overrides', $g, self::AUTH_ZTNA, true),
            self::e('GET', 'clinical/order-sets', $g, self::AUTH_ZTNA, true),
            self::e('POST', 'clinical/order-sets/{orderSet}/apply', $g, self::AUTH_PATIENT, false),
            self::e('GET', 'clinical/patients/{patientId}/mar', $g, self::AUTH_PATIENT, true),
            self::e('POST', 'clinical/mar/doses/{dose}/administer', $g, self::AUTH_PATIENT, false,
                'Barred off-premises. Emits MEDICATION_ADMINISTERED to Inventory.'),
            self::e('POST', 'clinical/mar/doses/{dose}/refuse', $g, self::AUTH_PATIENT, false),
            self::e('POST', 'clinical/mar/doses/{dose}/hold', $g, self::AUTH_PATIENT, false),
            self::e('POST', 'clinical/mar/items/{orderItem}/administer-prn', $g, self::AUTH_PATIENT, false),
            self::e('POST', 'clinical/mar/items/{orderItem}/waste', $g, self::AUTH_PATIENT, false),
            self::e('POST', 'clinical/consumption/crash-cart', $g, self::AUTH_PATIENT, false),
            self::e('POST', 'clinical/consumption/floor-stock', $g, self::AUTH_PATIENT, false),
            self::e('GET', 'clinical/consumption/outbox', $g, self::AUTH_ZTNA, true,
                'status=FAILED shows undelivered consumption facts.'),
            self::e('POST', 'clinical/handshake/issue-token', $g, self::AUTH_ZTNA, false),
            self::e('POST', 'clinical/handshake/validate-token', $g, self::AUTH_ZTNA, false),
            self::e('POST', 'clinical/entitlements', $g, self::AUTH_ZTNA, false),
            self::e('POST', 'clinical/entitlements/preview', $g, self::AUTH_ZTNA, false),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function bedsTasksTransitions(): array
    {
        $g = 'Beds, tasks, transitions';

        return [
            self::e('GET', 'clinical/wards/{wardCode}/census', $g, self::AUTH_ZTNA, true),
            self::e('GET', 'clinical/wards/{wardCode}/task-summary', $g, self::AUTH_ZTNA, true),
            self::e('POST', 'clinical/wards/{clientSpace}/overflow-bed', $g, self::AUTH_ZTNA, false),
            self::e('POST', 'clinical/beds/{bed}/reserve', $g, self::AUTH_ZTNA, false),
            self::e('POST', 'clinical/beds/{bed}/assign', $g, self::AUTH_ZTNA, false),
            self::e('POST', 'clinical/beds/{bed}/release', $g, self::AUTH_ZTNA, false),
            self::e('DELETE', 'clinical/beds/{bed}', $g, self::AUTH_ZTNA, false),
            self::e('GET', 'clinical/tasks/visibility', $g, self::AUTH_ZTNA, true,
                'scope=MY_PATIENTS | MY_WARD | MY_TEAM'),
            self::e('POST', 'clinical/work-orders/{workOrder}/transition', $g, self::AUTH_ZTNA, false),
            self::e('POST', 'clinical/transitions/{processCode}/start', $g, self::AUTH_ZTNA, false),
            self::e('POST', 'clinical/transitions/execute', $g, self::AUTH_ZTNA, false,
                'Discharge is blocked while a critical result is unacknowledged.'),
            self::e('POST', 'clinical/transitions/decision-to-admit', $g, self::AUTH_ZTNA, false),
            self::e('GET', 'clinical/transitions/{transition}', $g, self::AUTH_ZTNA, true),
            self::e('POST', 'clinical/transitions/{transition}/abandon', $g, self::AUTH_ZTNA, false),
            self::e('GET', 'clinical/patients/{patientId}/transitions', $g, self::AUTH_ZTNA, true),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function diagnosticsTelemetryScores(): array
    {
        $g = 'Diagnostics, telemetry, scores';

        return [
            self::e('GET', 'clinical/patients/{patientId}/diagnostics', $g, self::AUTH_ZTNA, true),
            self::e('GET', 'clinical/patients/{patientId}/critical-alerts', $g, self::AUTH_ZTNA, true,
                'An unacknowledged alert blocks discharge.'),
            self::e('POST', 'clinical/critical-alerts/{alert}/acknowledge', $g, self::AUTH_ZTNA, false),
            self::e('POST', 'clinical/encounters/created', $g, self::AUTH_ZTNA, false,
                'Main calls this when a visit opens; carries pending orders forward.'),
            self::e('POST', 'clinical/device-telemetry', $g, self::AUTH_ZTNA, false),
            self::e('GET', 'clinical/device-telemetry/pending', $g, self::AUTH_ZTNA, true),
            self::e('POST', 'clinical/device-telemetry/{deviceReading}/validate', $g, self::AUTH_ZTNA, false),
            self::e('POST', 'clinical/device-telemetry/{deviceReading}/reject', $g, self::AUTH_ZTNA, false),
            self::e('POST', 'clinical/scores/{scoreCode}/calculate', $g, self::AUTH_ZTNA, false,
                'NEWS2 | SATS | APGAR | GCS | EGFR_CKD_EPI | BMI. Stateless, but a POST.'),
            self::e('POST', 'clinical/observation-compliance/refresh', $g, self::AUTH_ZTNA, false),
            self::e('POST', 'clinical/scheduled-observations/{scheduledObservation}/skip', $g, self::AUTH_ZTNA, false),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function aiInteropMaternityRecall(): array
    {
        $g = 'AI, interop, maternity, recall';

        return [
            self::e('POST', 'clinical/scratchpad', $g, self::AUTH_PATIENT, false),
            self::e('POST', 'clinical/scratchpad/dictation-session', $g, self::AUTH_PATIENT, false,
                'Blocked (§14): needs a gateway-issued session token.'),
            self::e('GET', 'clinical/patients/{patientId}/scratchpad', $g, self::AUTH_PATIENT, true),
            self::e('GET', 'clinical/scratchpad/{note}', $g, self::AUTH_PATIENT, true),
            self::e('PATCH', 'clinical/scratchpad/{note}', $g, self::AUTH_PATIENT, false),
            self::e('POST', 'clinical/scratchpad/{note}/discard', $g, self::AUTH_PATIENT, false),
            self::e('POST', 'clinical/scratchpad/{note}/extract-observations', $g, self::AUTH_PATIENT, false,
                '503 until the AI gateway is configured.'),
            self::e('POST', 'clinical/scratchpad/{note}/extract-intent', $g, self::AUTH_PATIENT, false),
            self::e('POST', 'clinical/ai/icd11-suggest', $g, self::AUTH_PATIENT, false),
            self::e('POST', 'clinical/ai/summarize-observations', $g, self::AUTH_PATIENT, false),
            self::e('POST', 'clinical/ai/recommend-protocol', $g, self::AUTH_PATIENT, false),
            self::e('GET', 'clinical/patients/{patientId}/ai-suggestions', $g, self::AUTH_PATIENT, true),
            self::e('GET', 'clinical/ai-suggestions/{suggestion}', $g, self::AUTH_PATIENT, true),
            self::e('POST', 'clinical/ai-suggestions/{suggestion}/acknowledge', $g, self::AUTH_PATIENT, false),
            self::e('POST', 'clinical/ai-suggestions/items/{item}/accept', $g, self::AUTH_PATIENT, false,
                'The only route AI output reaches a chart by.'),
            self::e('POST', 'clinical/ai-suggestions/items/{item}/reject', $g, self::AUTH_PATIENT, false),
            self::e('POST', 'clinical/exchange-documents', $g, self::AUTH_PATIENT, false),
            self::e('GET', 'clinical/patients/{patientId}/exchange-documents', $g, self::AUTH_PATIENT, true),
            self::e('GET', 'clinical/exchange-documents/{document}/download', $g, self::AUTH_PATIENT, true),
            self::e('POST', 'clinical/document-imports', $g, self::AUTH_PATIENT, false,
                'Staged, never auto-charted.'),
            self::e('GET', 'clinical/patients/{patientId}/document-imports', $g, self::AUTH_PATIENT, true),
            self::e('GET', 'clinical/document-imports/{import}', $g, self::AUTH_PATIENT, true),
            self::e('POST', 'clinical/document-imports/items/{item}/merge', $g, self::AUTH_PATIENT, false,
                'Allergies, conditions and immunisations only.'),
            self::e('POST', 'clinical/document-imports/items/{item}/reject', $g, self::AUTH_PATIENT, false),
            self::e('POST', 'clinical/maternity/birth-events', $g, self::AUTH_PATIENT, false),
            self::e('GET', 'clinical/patients/{patientId}/birth-events', $g, self::AUTH_PATIENT, true),
            self::e('GET', 'clinical/maternity/birth-events/{birthEvent}', $g, self::AUTH_PATIENT, true),
            self::e('POST', 'clinical/maternity/birth-records/{birthRecord}/apgar', $g, self::AUTH_PATIENT, false),
            self::e('POST', 'clinical/maternity/birth-records/{birthRecord}/link-infant', $g, self::AUTH_SERVICE, false,
                'Main calls this after registering an infant.'),
            self::e('POST', 'clinical/maternity/birth-records/{birthRecord}/resend-registration', $g, self::AUTH_SERVICE, false),
            self::e('GET', 'clinical/maternity/options', $g, self::AUTH_SERVICE, true),
            self::e('GET', 'clinical/patients/{patientId}/recalls', $g, self::AUTH_PATIENT, true),
            self::e('POST', 'clinical/recalls/{recall}/complete', $g, self::AUTH_PATIENT, false),
            self::e('POST', 'clinical/recalls/{recall}/cancel', $g, self::AUTH_PATIENT, false),
            self::e('GET', 'clinical/recalls/worklist', $g, self::AUTH_ZTNA, true,
                'Empty on a live system usually means schedule:run is not running.'),
            self::e('GET', 'clinical/recalls/rules', $g, self::AUTH_ZTNA, true),
            self::e('POST', 'clinical/recalls/refresh', $g, self::AUTH_ZTNA, false),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function securityDevicesAudit(): array
    {
        $g = 'Security, devices, audit';

        return [
            self::e('POST', 'clinical/security/break-glass', $g, self::AUTH_ZTNA, false,
                'Grants a four-hour audited window.'),
            self::e('GET', 'clinical/security/break-glass', $g, self::AUTH_ZTNA, true),
            self::e('GET', 'clinical/security/context', $g, self::AUTH_ZTNA, true,
                'On/off premises, roles, tenant, scope — the best single smoke test.'),
            self::e('POST', 'clinical/md/generate-token', $g, self::AUTH_ZTNA, false),
            self::e('POST', 'clinical/device/complete-enrollment', $g, self::AUTH_ZTNA, false),
            self::e('POST', 'clinical/device/challenge', $g, self::AUTH_ZTNA, false,
                'Single-use, five-minute expiry. Device gate ships disabled (§14).'),
            self::e('GET', 'clinical/md/devices', $g, self::AUTH_ZTNA, true),
            self::e('PATCH', 'clinical/md/devices/{device}', $g, self::AUTH_ZTNA, false),
            self::e('GET', 'clinical/md/offsite-audit-feed', $g, self::AUTH_ZTNA, true),
            self::e('POST', 'clinical/md/offsite-audit-feed/{accessLog}/demand-justification', $g, self::AUTH_ZTNA, false),
            self::e('POST', 'clinical/md/offsite-audit-feed/{accessLog}/justify', $g, self::AUTH_ZTNA, false),
            self::e('POST', 'clinical/md/offsite-audit-feed/{accessLog}/close-session', $g, self::AUTH_ZTNA, false),
            self::e('GET', 'clinical/audit-trail', $g, self::AUTH_ZTNA, true),
            self::e('GET', 'clinical/audit-trail/verify', $g, self::AUTH_ZTNA, true,
                'A failure here is a governance incident, not a bug.'),
            self::e('GET', 'clinical/care-assignments', $g, self::AUTH_ZTNA, true),
            self::e('POST', 'clinical/care-assignments', $g, self::AUTH_ZTNA, false),
            self::e('POST', 'clinical/care-assignments/check', $g, self::AUTH_ZTNA, false,
                'Advisory only — Clinical re-runs the gate on every call regardless.'),
            self::e('DELETE', 'clinical/care-assignments/{careAssignment}', $g, self::AUTH_ZTNA, false),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function webhooks(): array
    {
        $g = 'Webhooks (HMAC)';

        return [
            self::e('POST', 'clinical/lab-proxy/status-update', $g, self::AUTH_HMAC, false),
            self::e('POST', 'clinical/lab-proxy/critical-result', $g, self::AUTH_HMAC, false),
            self::e('POST', 'clinical/lab-proxy/result-validated', $g, self::AUTH_HMAC, false,
                'Unknown unit_label means the result is skipped, not charted.'),
            self::e('POST', 'clinical/lab-reagent-proxy', $g, self::AUTH_HMAC, false),
            self::e('POST', 'clinical/imaging-proxy/status-update', $g, self::AUTH_HMAC, false),
            self::e('POST', 'clinical/imaging-proxy/pacs-cstore-complete', $g, self::AUTH_HMAC, false),
            self::e('POST', 'clinical/imaging-proxy/critical-finding', $g, self::AUTH_HMAC, false),
            self::e('POST', 'clinical/imaging-proxy/report-validated', $g, self::AUTH_HMAC, false),
            self::e('POST', 'clinical/radiology-consumption-proxy', $g, self::AUTH_HMAC, false),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function settings(): array
    {
        $g = 'Settings dictionaries';
        $entries = [];

        // Most dictionaries follow one shape: list, create, update. Generating
        // them keeps the catalog honest — a hand-written list of fifty-one
        // near-identical routes is where typos hide.
        $crud = [
            'dictionaries/units-of-measure',
            'dictionaries/unit-conversions',
            'dictionaries/reason-codes',
            'dictionaries/scoring-models',
            'dictionaries/routes-and-frequencies',
            'cde-registry',
            'cdss/interactions',
            'cdss/dose-limits',
        ];

        foreach ($crud as $path) {
            $entries[] = self::e('GET', "settings/{$path}", $g, self::AUTH_SERVICE, true);
            $entries[] = self::e('POST', "settings/{$path}", $g, self::AUTH_SERVICE, false);
            $entries[] = self::e('PATCH', "settings/{$path}/{id}", $g, self::AUTH_SERVICE, false);
        }

        // Read + update only — no create.
        foreach (['dictionaries/escalation-rules', 'dictionaries/module-aliases'] as $path) {
            $entries[] = self::e('GET', "settings/{$path}", $g, self::AUTH_SERVICE, true);
            $entries[] = self::e('PATCH', "settings/{$path}/{id}", $g, self::AUTH_SERVICE, false);
        }

        // List + create only.
        foreach (['cdss/renal-rules'] as $path) {
            $entries[] = self::e('GET', "settings/{$path}", $g, self::AUTH_SERVICE, true);
            $entries[] = self::e('POST', "settings/{$path}", $g, self::AUTH_SERVICE, false);
        }

        return array_merge($entries, [
            self::e('POST', 'settings/dictionaries/unit-conversions/convert', $g, self::AUTH_SERVICE, false,
                'Stateless conversion. Per-CDE and possibly formula-based — do not reimplement locally.'),
            self::e('GET', 'settings/dictionaries/scoring-models/{scoreCode}', $g, self::AUTH_SERVICE, true),
            self::e('GET', 'settings/cde-registry/{cdeCode}', $g, self::AUTH_SERVICE, true),

            self::e('GET', 'settings/cde-groups', $g, self::AUTH_SERVICE, true),
            self::e('POST', 'settings/cde-groups', $g, self::AUTH_SERVICE, false),
            self::e('PUT', 'settings/cde-groups/{cdeGroup}/members', $g, self::AUTH_SERVICE, false,
                'PUT replaces the member list wholesale.'),

            self::e('GET', 'settings/cde-templates', $g, self::AUTH_SERVICE, true),
            self::e('POST', 'settings/cde-templates', $g, self::AUTH_SERVICE, false),
            self::e('GET', 'settings/cde-templates/{templateCode}', $g, self::AUTH_SERVICE, true),

            self::e('GET', 'settings/observation-schedules', $g, self::AUTH_SERVICE, true),
            self::e('POST', 'settings/observation-schedules', $g, self::AUTH_SERVICE, false),
            self::e('POST', 'settings/observation-schedules/{id}/assign', $g, self::AUTH_SERVICE, false),

            self::e('GET', 'settings/client-spaces', $g, self::AUTH_SERVICE, true),
            self::e('POST', 'settings/client-spaces', $g, self::AUTH_SERVICE, false),
            self::e('POST', 'settings/client-spaces/{id}/beds', $g, self::AUTH_SERVICE, false),

            self::e('GET', 'settings/care-teams', $g, self::AUTH_SERVICE, true),
            self::e('POST', 'settings/care-teams', $g, self::AUTH_SERVICE, false),
            self::e('PUT', 'settings/care-teams/{id}/members', $g, self::AUTH_SERVICE, false),

            self::e('GET', 'settings/process-registry', $g, self::AUTH_SERVICE, true),
            self::e('POST', 'settings/process-registry', $g, self::AUTH_SERVICE, false),
            self::e('PUT', 'settings/process-registry/{process}/steps', $g, self::AUTH_SERVICE, false,
                'Replaces the ordered step list wholesale.'),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function fhir(): array
    {
        $g = 'FHIR R4 (read-only)';

        $entries = [
            self::e('GET', 'fhir/metadata', $g, self::AUTH_ZTNA, true,
                'CapabilityStatement — read before assuming a search parameter exists.'),
            self::e('GET', 'fhir/Patient/{patientId}', $g, self::AUTH_PATIENT, true),
            self::e('GET', 'fhir/Patient/{patientId}/$everything', $g, self::AUTH_PATIENT, true,
                'Whole record as one Bundle. Expensive.'),
        ];

        foreach ([
            'Encounter', 'Observation', 'Condition', 'MedicationRequest',
            'ServiceRequest', 'DiagnosticReport', 'AllergyIntolerance', 'Immunization',
        ] as $resource) {
            $entries[] = self::e('GET', "fhir/{$resource}", $g, self::AUTH_PATIENT, true,
                'searchset Bundle; failures return an OperationOutcome. Local code system, not LOINC (OPEN-24).');
        }

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    private static function e(string $method, string $path, string $group, string $auth, bool $safe, ?string $note = null): array
    {
        return array_filter([
            'method' => $method,
            'path' => $path,
            'group' => $group,
            'auth' => $auth,
            'safe' => $safe,
            'note' => $note,
        ], fn ($value) => $value !== null);
    }
}
