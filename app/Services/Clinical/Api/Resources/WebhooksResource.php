<?php

namespace App\Services\Clinical\Api\Resources;

use RuntimeException;

/**
 * The diagnostic-engine callbacks Clinical exposes — API Integration Guide
 * §9.3, §9.4 and §11.
 *
 * **Main is not the intended caller.** These belong to LIMS and RIS, which own
 * the HMAC secrets. They are included here for two real reasons: to exercise
 * the endpoints while testing the integration, and to let this module stand in
 * for a not-yet-deployed LIMS or RIS in a staging environment.
 *
 * Authentication is by signature, not service key (§3.3). The signature covers
 * the **exact bytes sent** — re-serialising the body before signing is the
 * commonest integration bug there is, so the client computes the encoded body
 * once and uses it for both the signature and the request.
 *
 * Callbacks are de-duplicated on the signature itself, so a retried delivery is
 * safe. An explicit event id would be safer; send one if you have it.
 */
class WebhooksResource extends ClinicalResource
{
    // ---------------------------------------------------------------- LIMS

    public function labStatusUpdate(array $payload, ?string $secret = null, array $options = []): array
    {
        return $this->signed('clinical/lab-proxy/status-update', $payload, $secret, 'lims', $options);
    }

    /**
     * A panic value. Raises a clinical alert, and that alert then **blocks
     * discharge** until acknowledged (§10.6) — this is not a passive
     * notification.
     */
    public function labCriticalResult(array $payload, ?string $secret = null, array $options = []): array
    {
        return $this->signed('clinical/lab-proxy/critical-result', $payload, $secret, 'lims', $options);
    }

    /**
     * Validated results become chart observations.
     *
     * A result whose `unit_label` is not in Clinical's registry is **skipped,
     * not charted** — it cannot be normalised, and charting an unconvertible
     * number is worse than not charting it. If results are silently missing
     * from a chart, check the unit labels first.
     */
    public function labResultValidated(array $payload, ?string $secret = null, array $options = []): array
    {
        return $this->signed('clinical/lab-proxy/result-validated', $payload, $secret, 'lims', $options);
    }

    /** Reagent draw, relayed on to Inventory as LAB_CONSUMPTION_FACT. */
    public function labReagentConsumption(array $payload, ?string $secret = null, array $options = []): array
    {
        return $this->signed('clinical/lab-reagent-proxy', $payload, $secret, 'lims', $options);
    }

    // ---------------------------------------------------------------- RIS / PACS

    public function imagingStatusUpdate(array $payload, ?string $secret = null, array $options = []): array
    {
        return $this->signed('clinical/imaging-proxy/status-update', $payload, $secret, 'ris', $options);
    }

    public function imagingCstoreComplete(array $payload, ?string $secret = null, array $options = []): array
    {
        return $this->signed('clinical/imaging-proxy/pacs-cstore-complete', $payload, $secret, 'ris', $options);
    }

    public function imagingCriticalFinding(array $payload, ?string $secret = null, array $options = []): array
    {
        return $this->signed('clinical/imaging-proxy/critical-finding', $payload, $secret, 'ris', $options);
    }

    public function imagingReportValidated(array $payload, ?string $secret = null, array $options = []): array
    {
        return $this->signed('clinical/imaging-proxy/report-validated', $payload, $secret, 'ris', $options);
    }

    /** Contrast and film use, relayed to Inventory. */
    public function radiologyConsumption(array $payload, ?string $secret = null, array $options = []): array
    {
        return $this->signed('clinical/radiology-consumption-proxy', $payload, $secret, 'ris', $options);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function signed(string $path, array $payload, ?string $secret, string $module, array $options): array
    {
        $secret ??= config("services.module_endpoints.{$module}.secret")
            ?? config("services.module_endpoints.{$module}.hmac_secret");

        if (! $secret) {
            // Better to say which secret is missing than to send an unsigned
            // request and read back a bare 401 INVALID_HMAC_SIGNATURE.
            throw new RuntimeException(
                "No HMAC secret configured for [{$module}]. These callbacks belong to that module; "
                ."set services.module_endpoints.{$module}.secret to exercise them from here."
            );
        }

        return $this->client->post($path, $payload, $options + [
            'hmac_secret' => $secret,
            // Signature-authenticated, not service-key — and definitely not
            // clinician-attributed. This is machine traffic.
            'with_identity' => false,
        ]);
    }
}
