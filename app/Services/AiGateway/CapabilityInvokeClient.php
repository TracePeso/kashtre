<?php

namespace App\Services\AiGateway;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * HTTP client for POST /api/ai/v1/capabilities/{code}:invoke.
 *
 * Provider keys stay on the gateway. This app only sends a module token.
 */
class CapabilityInvokeClient
{
    public const PRODUCTION_URL = 'https://ai.kashtre.com';

    public function url(): string
    {
        $configured = trim((string) config('services.ai_gateway.url', ''));

        return rtrim($configured !== '' ? $configured : self::PRODUCTION_URL, '/');
    }

    public function isConfigured(string $module = 'inventory'): bool
    {
        return $this->tokenFor($module) !== '';
    }

    /**
     * @param  array<string, mixed>  $input  Capability input, or a full invoke envelope with an `input` key
     * @return array{
     *     ok: bool,
     *     available: bool,
     *     status: ?string,
     *     result: ?array,
     *     warnings: list<string>,
     *     requiresHumanReview: bool,
     *     requestId: ?string,
     *     error: ?string,
     *     errorCode: ?string,
     *     details: mixed
     * }
     */
    public function invoke(
        string $capability,
        array $input,
        string $module = 'inventory',
        ?string $message = null,
        ?string $tenantId = null,
    ): array {
        $token = $this->tokenFor($module);
        $moduleCode = $this->moduleCodeFor($module);

        if ($token === '') {
            return $this->failure('Inventory AI is not connected. Set AI_GATEWAY_INVENTORY_TOKEN.', available: false);
        }

        $capabilityInput = $this->capabilityInput($input);
        if ($message !== null && $message !== '') {
            $capabilityInput['message'] = $message;
        }

        $requestId = (string) Str::uuid();
        $body = [
            'correlationId' => is_string($input['correlationId'] ?? null) && $input['correlationId'] !== ''
                ? $input['correlationId']
                : $requestId,
            'contractVersion' => is_string($input['contractVersion'] ?? null) && $input['contractVersion'] !== ''
                ? $input['contractVersion']
                : '1.0.0',
            'purposeOfUse' => is_string($input['purposeOfUse'] ?? null) && $input['purposeOfUse'] !== ''
                ? $input['purposeOfUse']
                : $this->purposeFor($module, $capability),
            'dataClassification' => is_string($input['dataClassification'] ?? null) && $input['dataClassification'] !== ''
                ? $input['dataClassification']
                : $this->classificationFor($module),
            'input' => $capabilityInput,
            'responseMode' => 'SYNC',
        ];

        $headers = [
            'Accept' => 'application/json',
            'X-Module-Code' => $moduleCode,
            'X-Request-ID' => $requestId,
        ];

        $resolvedTenant = $this->tenantId($tenantId);
        if ($resolvedTenant !== '') {
            $headers['X-Tenant-ID'] = $resolvedTenant;
        }

        try {
            $response = Http::baseUrl($this->url())
                ->withToken($token)
                ->withHeaders($headers)
                ->timeout((int) config('services.ai_gateway.timeout', 90))
                ->acceptJson()
                ->asJson()
                ->post('/api/ai/v1/capabilities/'.$capability.':invoke', $body);

            $json = $response->json();
            $payload = is_array($json) ? $json : [];
            $error = is_array($payload['error'] ?? null) ? $payload['error'] : null;

            if ($response->successful()) {
                $status = is_string($payload['status'] ?? null) ? $payload['status'] : null;
                $result = is_array($payload['result'] ?? null) ? $payload['result'] : null;

                if ($result === null && in_array($status, ['QUEUED', 'RUNNING'], true)) {
                    return [
                        'ok' => false,
                        'available' => true,
                        'status' => $status,
                        'result' => null,
                        'warnings' => $this->stringList($payload['warnings'] ?? []),
                        'requiresHumanReview' => true,
                        'requestId' => is_string($payload['requestId'] ?? null) ? $payload['requestId'] : $requestId,
                        'error' => 'AI accepted the request and is still working. Ask again in a moment.',
                        'errorCode' => $status,
                        'details' => null,
                    ];
                }

                return [
                    'ok' => true,
                    'available' => true,
                    'status' => $status,
                    'result' => $result,
                    'warnings' => $this->stringList($payload['warnings'] ?? []),
                    'requiresHumanReview' => (bool) ($payload['requiresHumanReview'] ?? true),
                    'requestId' => is_string($payload['requestId'] ?? null) ? $payload['requestId'] : $requestId,
                    'error' => null,
                    'errorCode' => null,
                    'details' => null,
                ];
            }

            $errorCode = is_string($error['code'] ?? null) ? $error['code'] : null;
            $messageText = is_string($error['message'] ?? null)
                ? $error['message']
                : ('AI request failed (HTTP '.$response->status().').');

            return [
                'ok' => false,
                'available' => true,
                'status' => null,
                'result' => null,
                'warnings' => [],
                'requiresHumanReview' => true,
                'requestId' => is_string($payload['requestId'] ?? null) ? $payload['requestId'] : $requestId,
                'error' => $this->friendlyError($errorCode, $messageText),
                'errorCode' => $errorCode,
                'details' => $error['details'] ?? null,
            ];
        } catch (ConnectionException $e) {
            Log::warning('AI Gateway unreachable: '.$e->getMessage(), ['url' => $this->url()]);

            return $this->failure('Could not reach the AI gateway at '.$this->url().'.');
        } catch (Throwable $e) {
            Log::warning('AI Gateway invoke failed: '.$e->getMessage(), [
                'capability' => $capability,
                'url' => $this->url(),
            ]);

            return $this->failure('AI Gateway request failed.');
        }
    }

    /**
     * @return array{
     *     ok: bool,
     *     available: bool,
     *     status: null,
     *     result: null,
     *     warnings: list<string>,
     *     requiresHumanReview: bool,
     *     requestId: null,
     *     error: string,
     *     errorCode: null,
     *     details: null
     * }
     */
    private function failure(string $error, bool $available = true): array
    {
        return [
            'ok' => false,
            'available' => $available,
            'status' => null,
            'result' => null,
            'warnings' => [],
            'requiresHumanReview' => true,
            'requestId' => null,
            'error' => $error,
            'errorCode' => null,
            'details' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function capabilityInput(array $input): array
    {
        $inner = $input['input'] ?? null;

        return is_array($inner) ? $inner : $input;
    }

    private function tenantId(?string $tenantId): string
    {
        $passed = trim((string) $tenantId);
        if ($passed !== '') {
            return $passed;
        }

        return trim((string) config('services.ai_gateway.tenant_id', ''));
    }

    private function purposeFor(string $module, string $capability): string
    {
        if ($module === 'inventory') {
            return 'REPLENISHMENT_PLANNING';
        }

        return match ($capability) {
            'ROSTER_SUGGEST', 'COVERAGE_GAP_ANALYZE', 'LEAVE_CONFLICT_ANALYZE' => 'WORKFORCE_PLANNING',
            'REPORT_NARRATIVE', 'ANOMALY_EXPLAIN' => 'MANAGEMENT_REPORTING',
            default => 'CLINICAL_DOCUMENTATION',
        };
    }

    private function classificationFor(string $module): string
    {
        return $module === 'inventory' ? 'CONFIDENTIAL' : 'CLINICAL';
    }

    private function friendlyError(?string $code, string $fallback): string
    {
        return match ($code) {
            'CALLER_CONTEXT_REQUIRED' => 'Inventory AI needs the Inventory app tenant. Set AI_GATEWAY_TENANT_ID to the UUID shown when you created the Inventory app on the gateway.',
            'AUTHENTICATION_FAILED' => 'The Inventory AI token was rejected. Mint a new token from Apps → Inventory on the gateway and set AI_GATEWAY_INVENTORY_TOKEN.',
            'TENANT_SCOPE_DENIED' => 'This Inventory token cannot assume that business. Use the tenant UUID granted to the Inventory app.',
            'AUTHORIZATION_DENIED' => 'Inventory is not subscribed to this AI task, or the purpose is not allowed. Grant Inventory access on the gateway.',
            'CAPABILITY_REJECTED_INPUT' => 'The gateway refused the forecast history. Need at least three weeks of real (non-missing) numbers.',
            'CAPABILITY_NOT_FOUND' => 'That AI task is not published on the gateway yet.',
            'CAPABILITY_UNAVAILABLE' => 'No approved AI route is available for Inventory on the gateway.',
            'BUDGET_EXCEEDED' => 'The Inventory AI budget on the gateway has been reached.',
            default => $fallback,
        };
    }

    private function tokenFor(string $module): string
    {
        if ($module === 'inventory') {
            return trim((string) (
                config('services.ai_gateway.inventory.token')
                ?: config('services.ai_gateway.token')
                ?: config('services.ai_gateway.api_key')
            ));
        }

        return trim((string) (config('services.ai_gateway.token') ?: config('services.ai_gateway.api_key')));
    }

    private function moduleCodeFor(string $module): string
    {
        if ($module === 'inventory') {
            return (string) config('services.ai_gateway.inventory.module_code', 'INVENTORY_ORCHESTRATOR');
        }

        return (string) config('services.ai_gateway.module_code', 'CLINICAL_ORCHESTRATOR');
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $out[] = $item;
            }
        }

        return array_values($out);
    }
}
