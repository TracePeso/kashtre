<?php

namespace App\Http\Controllers\API\Clinical;

use App\Http\Controllers\Controller;
use App\Services\Clinical\Integration\LimsIntegrationProxyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Clinical-to-LIMS ICD's POST /api/v1/clinical/lab-proxy/{eventType} —
 * the real inbound endpoint a genuinely-separate LIMS would call.
 * Chunk 7 only ever exercised this direction in-process (StubLimsClient
 * lives in the same PHP request), so nothing previously verified a real
 * signed HTTP call could actually reach LimsIntegrationProxyService.
 *
 * HMAC verification here deliberately fails closed: any mismatch —
 * missing header, wrong secret, tampered body — is a 401, never a
 * silent pass-through.
 */
class LimsWebhookController extends Controller
{
    public function handle(Request $request, string $eventType, LimsIntegrationProxyService $proxy): JsonResponse
    {
        if (! $this->hasValidSignature($request)) {
            return response()->json(['error' => 'INVALID_SIGNATURE'], 401);
        }

        $payload = $request->all();

        return match ($eventType) {
            'result-validated' => response()->json($proxy->handleResultValidated($payload)),
            'critical-result' => response()->json($proxy->handleCriticalResult($payload)),
            'reagent-consumption' => response()->json($proxy->handleReagentConsumption($payload)),
            default => response()->json(['error' => "Unknown event type: {$eventType}"], 404),
        };
    }

    private function hasValidSignature(Request $request): bool
    {
        $secret = (string) config('services.module_endpoints.lims.secret');
        $signature = (string) $request->header('X-KashTre-Signature');

        if ($secret === '' || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
