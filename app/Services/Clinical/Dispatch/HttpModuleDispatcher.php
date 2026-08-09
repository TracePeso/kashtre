<?php

namespace App\Services\Clinical\Dispatch;

use App\Contracts\ModuleDispatcher;
use App\Services\Clinical\Facts\Fact;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * DISPATCH_DRIVER=http binding: posts the exact same payload LocalModuleDispatcher
 * would hand to an in-process receiver, to the target module's own server
 * instead. Same shared-secret idiom as CallingServiceClient/VerifyImagingApiKey
 * for modules still trusted as a single integration partner (imaging,
 * inventory); LIMS additionally gets HMAC-SHA256 request signing + an
 * idempotency key, per the Clinical-to-LIMS ICD, since it is a genuinely
 * separate, externally-owned service.
 */
class HttpModuleDispatcher implements ModuleDispatcher
{
    public function dispatch(Fact $fact): array
    {
        $module = $fact->targetModule();
        $endpoint = config("services.module_endpoints.{$module}");

        if (! is_array($endpoint) || empty($endpoint['url'])) {
            throw new RuntimeException("No module_endpoints.{$module}.url configured for the http dispatch driver.");
        }

        $payload = $fact->toPayload();
        $path = '/api/v1/'.$module.'/facts/'.$fact->factType();

        $request = Http::baseUrl(rtrim($endpoint['url'], '/'))->timeout(10)->retry(3, 500);

        if ($module === 'lims') {
            $body = json_encode($payload);
            $signature = hash_hmac('sha256', $body, (string) ($endpoint['secret'] ?? ''));

            $response = $request->withHeaders([
                'Authorization' => 'Bearer '.($endpoint['secret'] ?? ''),
                'X-KashTre-Signature' => $signature,
                'X-Idempotency-Key' => $payload['lab_order_uuid'] ?? (string) Str::uuid(),
                'Content-Type' => 'application/json',
            ])->withBody($body, 'application/json')->post($path);
        } elseif ($module === 'imaging') {
            // Matches VerifyImagingApiKey exactly (X-Imaging-API-Key, not
            // Authorization: Bearer) — the real middleware the eventual
            // facts endpoint runs behind, reusing the same
            // services.imaging_module.api_key secret the existing
            // imaging.api routes already check.
            $response = $request->withHeaders([
                'X-Imaging-API-Key' => (string) config('services.imaging_module.api_key'),
            ])->post($path, $payload);
        } else {
            $response = $request->withHeaders([
                'Authorization' => 'Bearer '.($endpoint['api_key'] ?? ''),
            ])->post($path, $payload);
        }

        $response->throw();

        return (array) $response->json();
    }
}
