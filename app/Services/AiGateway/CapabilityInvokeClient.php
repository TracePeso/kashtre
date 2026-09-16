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
     * @param  array<string, mixed>  $input
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
    public function invoke(string $capability, array $input, string $module = 'inventory', ?string $message = null): array
    {
        $token = $this->tokenFor($module);
        $moduleCode = $this->moduleCodeFor($module);

        if ($token === '') {
            return $this->failure('Inventory AI is not connected. Set AI_GATEWAY_INVENTORY_TOKEN.', available: false);
        }

        $body = $input;
        if ($message !== null && $message !== '') {
            $body['message'] = $message;
        }

        $headers = [
            'Accept' => 'application/json',
            'X-Module-Code' => $moduleCode,
            'X-Request-ID' => (string) Str::uuid(),
        ];

        $tenantId = trim((string) config('services.ai_gateway.tenant_id', ''));
        if ($tenantId !== '') {
            $headers['X-Tenant-ID'] = $tenantId;
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
                return [
                    'ok' => true,
                    'available' => true,
                    'status' => is_string($payload['status'] ?? null) ? $payload['status'] : null,
                    'result' => is_array($payload['result'] ?? null) ? $payload['result'] : null,
                    'warnings' => $this->stringList($payload['warnings'] ?? []),
                    'requiresHumanReview' => (bool) ($payload['requiresHumanReview'] ?? true),
                    'requestId' => is_string($payload['requestId'] ?? null) ? $payload['requestId'] : null,
                    'error' => null,
                    'errorCode' => null,
                    'details' => null,
                ];
            }

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
                'requestId' => is_string($payload['requestId'] ?? null) ? $payload['requestId'] : $headers['X-Request-ID'],
                'error' => $messageText,
                'errorCode' => is_string($error['code'] ?? null) ? $error['code'] : null,
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
