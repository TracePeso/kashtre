<?php

namespace App\Services\Clinical\Api\Resources;

/**
 * Settings dictionaries — API Integration Guide §10.9.
 *
 * The governing instruction: **never hardcode a clinical value in your
 * module.** Units, reason codes, routes, frequencies, escalation tiers, CDE
 * definitions, CDSS rules and process registries are all tenant-configurable.
 * A hardcoded NEWS2 threshold or MAR wastage reason will disagree with what a
 * facility actually configured, and the disagreement surfaces as a clinician
 * being unable to record what they just did.
 *
 * These are service-key routes: no clinician identity required, no care
 * relationship involved. They are also the most cacheable part of the surface
 * — identical for everyone in a tenant, changed only by an administrator.
 */
class SettingsResource extends ClinicalResource
{
    // ---------------------------------------------------------------- units

    public function unitsOfMeasure(array $query = [], array $options = []): array
    {
        return $this->rows($this->client->get('settings/dictionaries/units-of-measure', $this->filled($query), $options), 'units');
    }

    public function createUnitOfMeasure(array $payload, array $options = []): array
    {
        return $this->client->post('settings/dictionaries/units-of-measure', $this->filled($payload), $options);
    }

    public function updateUnitOfMeasure(int|string $id, array $payload, array $options = []): array
    {
        return $this->client->patch("settings/dictionaries/units-of-measure/{$id}", $this->filled($payload), $options);
    }

    // ---------------------------------------------------------------- unit conversions

    public function unitConversions(array $query = [], array $options = []): array
    {
        return $this->rows($this->client->get('settings/dictionaries/unit-conversions', $this->filled($query), $options), 'conversions');
    }

    public function createUnitConversion(array $payload, array $options = []): array
    {
        return $this->client->post('settings/dictionaries/unit-conversions', $this->filled($payload), $options);
    }

    public function updateUnitConversion(int|string $id, array $payload, array $options = []): array
    {
        return $this->client->patch("settings/dictionaries/unit-conversions/{$id}", $this->filled($payload), $options);
    }

    /**
     * Converts a value between units for a CDE.
     *
     * Use this rather than a local factor: conversions are per-CDE and can be
     * multiplicative, divisive or formula-based, and a locally applied factor
     * will silently disagree for the formula cases (temperature, most obviously).
     */
    public function convert(string $cdeCode, float $value, int $fromUnitId, int $toUnitId, array $options = []): array
    {
        return $this->client->post('settings/dictionaries/unit-conversions/convert', [
            'cde_code' => $cdeCode,
            'value' => $value,
            'from_unit_id' => $fromUnitId,
            'to_unit_id' => $toUnitId,
        ], $options);
    }

    // ---------------------------------------------------------------- reason codes

    /**
     * @param  string|null  $category  MAR_WASTAGE | CDSS_OVERRIDE | BREAK_GLASS | CANCELLATION | ...
     */
    public function reasonCodes(?string $category = null, array $options = []): array
    {
        return $this->rows(
            $this->client->get('settings/dictionaries/reason-codes', $this->filled(['category' => $category]), $options),
            'reason_codes'
        );
    }

    public function createReasonCode(array $payload, array $options = []): array
    {
        return $this->client->post('settings/dictionaries/reason-codes', $this->filled($payload), $options);
    }

    public function updateReasonCode(int|string $id, array $payload, array $options = []): array
    {
        return $this->client->patch("settings/dictionaries/reason-codes/{$id}", $this->filled($payload), $options);
    }

    // ---------------------------------------------------------------- scoring

    public function scoringModels(array $query = [], array $options = []): array
    {
        return $this->rows($this->client->get('settings/dictionaries/scoring-models', $this->filled($query), $options), 'models');
    }

    public function scoringModel(string $scoreCode, array $options = []): array
    {
        return $this->client->get("settings/dictionaries/scoring-models/{$scoreCode}", [], $options);
    }

    public function createScoringModel(array $payload, array $options = []): array
    {
        return $this->client->post('settings/dictionaries/scoring-models', $this->filled($payload), $options);
    }

    public function updateScoringModel(int|string $id, array $payload, array $options = []): array
    {
        return $this->client->patch("settings/dictionaries/scoring-models/{$id}", $this->filled($payload), $options);
    }

    // ---------------------------------------------------------------- escalation

    /**
     * Which score bands page whom, and how fast. Read-and-update only — the
     * tiers themselves are not caller-creatable.
     */
    public function escalationRules(array $query = [], array $options = []): array
    {
        return $this->rows($this->client->get('settings/dictionaries/escalation-rules', $this->filled($query), $options), 'rules');
    }

    public function updateEscalationRule(int|string $id, array $payload, array $options = []): array
    {
        return $this->client->patch("settings/dictionaries/escalation-rules/{$id}", $this->filled($payload), $options);
    }

    // ---------------------------------------------------------------- routes & frequencies

    public function routesAndFrequencies(array $query = [], array $options = []): array
    {
        return $this->client->get('settings/dictionaries/routes-and-frequencies', $this->filled($query), $options);
    }

    public function createRouteOrFrequency(array $payload, array $options = []): array
    {
        return $this->client->post('settings/dictionaries/routes-and-frequencies', $this->filled($payload), $options);
    }

    public function updateRouteOrFrequency(int|string $id, array $payload, array $options = []): array
    {
        return $this->client->patch("settings/dictionaries/routes-and-frequencies/{$id}", $this->filled($payload), $options);
    }

    // ---------------------------------------------------------------- module aliases

    /**
     * How this facility names other modules' concepts. Read/update only.
     */
    public function moduleAliases(array $query = [], array $options = []): array
    {
        return $this->rows($this->client->get('settings/dictionaries/module-aliases', $this->filled($query), $options), 'aliases');
    }

    public function updateModuleAlias(int|string $id, array $payload, array $options = []): array
    {
        return $this->client->patch("settings/dictionaries/module-aliases/{$id}", $this->filled($payload), $options);
    }

    // ---------------------------------------------------------------- CDE registry

    /**
     * The source of truth for what can be charted. Every dynamic charting form
     * is driven from here rather than from hardcoded vitals fields.
     */
    public function cdeRegistry(array $query = [], array $options = []): array
    {
        return $this->rows($this->client->get('settings/cde-registry', $this->filled($query), $options), 'cdes');
    }

    public function cde(string $cdeCode, array $options = []): array
    {
        return $this->client->get("settings/cde-registry/{$cdeCode}", [], $options);
    }

    public function createCde(array $payload, array $options = []): array
    {
        return $this->client->post('settings/cde-registry', $this->filled($payload), $options);
    }

    public function updateCde(int|string $id, array $payload, array $options = []): array
    {
        return $this->client->patch("settings/cde-registry/{$id}", $this->filled($payload), $options);
    }

    // ---------------------------------------------------------------- CDE groups & templates

    public function cdeGroups(array $query = [], array $options = []): array
    {
        return $this->rows($this->client->get('settings/cde-groups', $this->filled($query), $options), 'groups');
    }

    public function createCdeGroup(array $payload, array $options = []): array
    {
        return $this->client->post('settings/cde-groups', $this->filled($payload), $options);
    }

    /** PUT, not PATCH — the member list is replaced wholesale, not merged. */
    public function setCdeGroupMembers(int|string $cdeGroupId, array $members, array $options = []): array
    {
        return $this->client->put("settings/cde-groups/{$cdeGroupId}/members", ['members' => $members], $options);
    }

    public function cdeTemplates(array $query = [], array $options = []): array
    {
        return $this->rows($this->client->get('settings/cde-templates', $this->filled($query), $options), 'templates');
    }

    public function cdeTemplate(string $templateCode, array $options = []): array
    {
        return $this->client->get("settings/cde-templates/{$templateCode}", [], $options);
    }

    public function createCdeTemplate(array $payload, array $options = []): array
    {
        return $this->client->post('settings/cde-templates', $this->filled($payload), $options);
    }

    // ---------------------------------------------------------------- observation schedules

    /**
     * Which observations are due how often. Drives the compliance state
     * machine that Clinical's scheduler advances — an unconfigured schedule
     * means missed rounds are never escalated.
     */
    public function observationSchedules(array $query = [], array $options = []): array
    {
        return $this->rows($this->client->get('settings/observation-schedules', $this->filled($query), $options), 'schedules');
    }

    public function createObservationSchedule(array $payload, array $options = []): array
    {
        return $this->client->post('settings/observation-schedules', $this->filled($payload), $options);
    }

    public function assignObservationSchedule(int|string $scheduleId, array $payload, array $options = []): array
    {
        return $this->client->post("settings/observation-schedules/{$scheduleId}/assign", $this->filled($payload), $options);
    }

    // ---------------------------------------------------------------- spaces & teams

    public function clientSpaces(array $query = [], array $options = []): array
    {
        return $this->rows($this->client->get('settings/client-spaces', $this->filled($query), $options), 'spaces');
    }

    public function createClientSpace(array $payload, array $options = []): array
    {
        return $this->client->post('settings/client-spaces', $this->filled($payload), $options);
    }

    public function createBedsInSpace(int|string $clientSpaceId, array $payload, array $options = []): array
    {
        return $this->client->post("settings/client-spaces/{$clientSpaceId}/beds", $this->filled($payload), $options);
    }

    public function careTeams(array $query = [], array $options = []): array
    {
        return $this->rows($this->client->get('settings/care-teams', $this->filled($query), $options), 'teams');
    }

    public function createCareTeam(array $payload, array $options = []): array
    {
        return $this->client->post('settings/care-teams', $this->filled($payload), $options);
    }

    /** PUT — replaces the roster wholesale. */
    public function setCareTeamMembers(int|string $careTeamId, array $members, array $options = []): array
    {
        return $this->client->put("settings/care-teams/{$careTeamId}/members", ['members' => $members], $options);
    }

    // ---------------------------------------------------------------- CDSS rules

    /**
     * Drug-drug interactions. Severity decides whether a match is a hard block
     * or a warning, so editing these directly changes what clinicians can
     * prescribe without an override.
     */
    public function cdssInteractions(array $query = [], array $options = []): array
    {
        return $this->rows($this->client->get('settings/cdss/interactions', $this->filled($query), $options), 'interactions');
    }

    public function createCdssInteraction(array $payload, array $options = []): array
    {
        return $this->client->post('settings/cdss/interactions', $this->filled($payload), $options);
    }

    public function updateCdssInteraction(int|string $id, array $payload, array $options = []): array
    {
        return $this->client->patch("settings/cdss/interactions/{$id}", $this->filled($payload), $options);
    }

    public function cdssDoseLimits(array $query = [], array $options = []): array
    {
        return $this->rows($this->client->get('settings/cdss/dose-limits', $this->filled($query), $options), 'dose_limits');
    }

    public function createCdssDoseLimit(array $payload, array $options = []): array
    {
        return $this->client->post('settings/cdss/dose-limits', $this->filled($payload), $options);
    }

    public function updateCdssDoseLimit(int|string $id, array $payload, array $options = []): array
    {
        return $this->client->patch("settings/cdss/dose-limits/{$id}", $this->filled($payload), $options);
    }

    public function cdssRenalRules(array $query = [], array $options = []): array
    {
        return $this->rows($this->client->get('settings/cdss/renal-rules', $this->filled($query), $options), 'rules');
    }

    public function createCdssRenalRule(array $payload, array $options = []): array
    {
        return $this->client->post('settings/cdss/renal-rules', $this->filled($payload), $options);
    }

    // ---------------------------------------------------------------- process registry

    /**
     * The step machines behind §10.6 transitions — which steps exist, in what
     * order, owned by which role, carrying which effects.
     */
    public function processRegistry(array $query = [], array $options = []): array
    {
        return $this->rows($this->client->get('settings/process-registry', $this->filled($query), $options), 'processes');
    }

    public function createProcess(array $payload, array $options = []): array
    {
        return $this->client->post('settings/process-registry', $this->filled($payload), $options);
    }

    /** PUT — replaces the ordered step list wholesale. */
    public function setProcessSteps(int|string $processId, array $steps, array $options = []): array
    {
        return $this->client->put("settings/process-registry/{$processId}/steps", ['steps' => $steps], $options);
    }
}
