<?php

namespace App\Services\Clinical\Api;

use App\Models\Client;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use Illuminate\Support\Facades\Log;

/**
 * The calls Main is obliged to make *into* Clinical — API Integration Guide
 * §9.1. These are not reads; they are facts only Main knows, and Clinical
 * cannot do its job without them.
 *
 * Every method here is best-effort by design. A failure to notify Clinical
 * must never break the Main-side transaction that triggered it: refusing to
 * open a visit because the clinical module is down would take the whole
 * hospital offline for an outage in one subsystem. Failures are logged with
 * the request_id and the underlying record stays authoritative on our side.
 *
 * The trade-off is real and worth naming — a dropped `encounterCreated` means
 * a returning outpatient's pending orders do not follow them onto the new
 * visit, and someone reprints a barcode. That is a recoverable annoyance;
 * a blocked registration desk is not.
 */
class ClinicalMainNotifier
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    /**
     * Call whenever a visit opens.
     *
     * `previous_visit_id` is what lets Clinical carry a returning outpatient's
     * pending lab and imaging orders onto the new visit, so already-printed
     * barcodes and accession numbers stay valid. Omitting it is not an error,
     * but it does silently strand those orders on the old visit.
     */
    public function encounterCreated(
        string $globalClientId,
        string $visitId,
        ?string $previousVisitId = null,
        ?int $businessId = null,
    ): bool {
        return $this->send('clinical/encounters/created', array_filter([
            'global_client_id' => $globalClientId,
            'visit_id' => $visitId,
            'previous_visit_id' => $previousVisitId,
        ], fn ($value) => $value !== null), $businessId, "encounter-created-{$visitId}");
    }

    /**
     * Call on package purchase, so Clinical can track consumption against the
     * entitlement and intercept excess before it is delivered rather than
     * after it is billed.
     *
     * @param  array<int, array{service_code: string, allocated_qty: int}>  $allocations
     */
    public function entitlementGranted(
        string $globalClientId,
        string $packageId,
        array $allocations,
        ?int $businessId = null,
    ): bool {
        return $this->send('clinical/entitlements', [
            'patient_id' => $globalClientId,
            'package_id' => $packageId,
            'allocations' => array_values($allocations),
        ], $businessId, "entitlement-{$globalClientId}-{$packageId}");
    }

    /**
     * Call after registering an infant in response to an
     * INFANT_REGISTRATION_REQUESTED event (§12).
     *
     * Until this lands, a newborn's APGAR scores and birth weight have no
     * chart to be recorded against — they are attached to the mother's birth
     * record with nowhere to go.
     */
    public function linkInfant(
        int|string $birthRecordId,
        string $infantGlobalClientId,
        string $infantVisitId,
        ?int $businessId = null,
    ): bool {
        return $this->send(
            "clinical/maternity/birth-records/{$birthRecordId}/link-infant",
            [
                'infant_patient_id' => $infantGlobalClientId,
                'infant_visit_id' => $infantVisitId,
            ],
            $businessId,
            "link-infant-{$birthRecordId}",
        );
    }

    /**
     * Convenience wrapper for the common case of having a Client model rather
     * than the raw identifiers.
     */
    public function encounterCreatedForClient(Client $client, ?string $previousVisitId = null): bool
    {
        if (! $client->client_id || ! $client->visit_id) {
            return false;
        }

        return $this->encounterCreated(
            (string) $client->client_id,
            (string) $client->visit_id,
            $previousVisitId,
            $client->business_id ? (int) $client->business_id : null,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $path, array $payload, ?int $businessId, string $idempotencyKey): bool
    {
        if (! $this->client->isConfigured()) {
            // CLINICAL_DRIVER=local: the clinical data is in this database and
            // there is nobody to notify. Not a failure.
            return false;
        }

        try {
            $this->client->post($path, $payload, [
                'business_id' => $businessId,
                'idempotency_key' => $idempotencyKey,
                // Module traffic, not a clinician acting. §3.2: no identity is
                // a legitimate and meaningful state — it skips the
                // care-relationship gate, which is right for a registration
                // desk opening a visit.
                'with_identity' => false,
            ]);

            return true;
        } catch (ClinicalApiException $e) {
            Log::error('Failed to notify the Clinical Module.', $e->context() + [
                'path' => $path,
                'idempotency_key' => $idempotencyKey,
            ]);

            return false;
        }
    }
}
