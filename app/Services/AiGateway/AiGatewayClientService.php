<?php

namespace App\Services\AiGateway;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * KashTre AI Gateway Integration Specification v1.0. A single shared
 * external service every module (Clinical/LIMS/RIS) calls identically —
 * direct calls to OpenAI/Google AI from any module are prohibited by the
 * spec. Doesn't exist yet, so every call here degrades gracefully
 * (returns ['available' => false, ...]) rather than throwing — nothing
 * in Clinical should ever hard-depend on the Gateway being up. Every
 * successful response is draft-only per the spec's own
 * requiresReview/requiresValidation/requiresClinicianApproval flags —
 * this client doesn't interpret or act on them, callers must.
 */
class AiGatewayClientService
{
    public function isAvailable(): bool
    {
        return ! empty(config('services.ai_gateway.url'));
    }

    /**
     * @return array{available: bool, intent_id?: string, observations?: array, requiresValidation?: bool, error?: string}
     */
    public function extractObservations(string $patientId, ?string $visitId, string $text): array
    {
        return $this->call('/api/v1/ai/extract-observations', [
            'patient_id' => $patientId,
            'visit_id' => $visitId,
            'text' => $text,
        ]);
    }

    /**
     * @return array{available: bool, medications?: array, laboratoryOrders?: array, requiresReview?: bool, error?: string}
     */
    public function extractIntent(string $text): array
    {
        return $this->call('/api/v1/ai/extract-intent', ['text' => $text]);
    }

    /**
     * @return array{available: bool, candidates?: array, requiresClinicianApproval?: bool, error?: string}
     */
    public function suggestIcd11(string $diagnosisText): array
    {
        return $this->call('/api/v1/ai/icd11-suggest', ['diagnosisText' => $diagnosisText]);
    }

    /**
     * @param array{patient_id: string, visit_id: ?string, profile: string} $payload profile e.g. 'I_PASS', 'ICU_ORGAN_SYSTEM'
     * @return array{available: bool, summary?: array, narrative?: string, requiresReview?: bool, error?: string}
     */
    public function summarizeObservations(array $payload): array
    {
        return $this->call('/api/v1/ai/summarize-observations', $payload);
    }

    private function call(string $endpoint, array $payload): array
    {
        if (! $this->isAvailable()) {
            return ['available' => false, 'error' => 'AI Gateway is not configured.'];
        }

        try {
            $response = Http::baseUrl(config('services.ai_gateway.url'))
                ->withHeaders([
                    'Authorization' => 'Bearer '.config('services.ai_gateway.api_key'),
                    'X-Module-Code' => config('services.ai_gateway.module_code'),
                    'X-Request-ID' => (string) Str::uuid(),
                ])
                ->timeout(10)
                ->retry(3, 500)
                ->post($endpoint, $payload);

            $response->throw();

            return ['available' => true, ...$response->json()];
        } catch (Throwable $e) {
            Log::warning("AI Gateway call to {$endpoint} failed: {$e->getMessage()}");

            return ['available' => false, 'error' => 'AI Gateway request failed.'];
        }
    }
}
