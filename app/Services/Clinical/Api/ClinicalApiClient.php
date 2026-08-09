<?php

namespace App\Services\Clinical\Api;

use App\Services\Clinical\Api\Exceptions\ClinicalAccessDeniedException;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Services\Clinical\Api\Exceptions\ClinicalAuthException;
use App\Services\Clinical\Api\Exceptions\ClinicalBiometricRequiredException;
use App\Services\Clinical\Api\Exceptions\ClinicalChartLockedException;
use App\Services\Clinical\Api\Exceptions\ClinicalRuleRefusedException;
use App\Services\Clinical\Api\Exceptions\ClinicalSafetyBlockException;
use App\Services\Clinical\Api\Exceptions\ClinicalUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The single HTTP entry point to the Clinical Module (CLINICAL_ORCHESTRATOR),
 * implementing the cross-cutting parts of the API Integration Guide so no
 * gateway has to re-implement them:
 *
 *   §2  version prefix and base URL
 *   §3  service key + clinician identity
 *   §4  tenancy header
 *   §5  the { data, meta } / { message, errors, request_id } envelope
 *   §6  status codes mapped onto typed exceptions
 *   §7  idempotency keys on mutating requests
 *   §8  the watermark headers an off-premises response carries
 *
 * Every gateway calls get/post/patch/delete here and receives the unwrapped
 * `data` payload, or throws. Callers never see an Illuminate Response.
 */
class ClinicalApiClient
{
    public function __construct(private readonly ClinicalRequestContext $context)
    {
    }

    /**
     * True when there is somewhere to send a request. An unconfigured URL is
     * a deployment state, not an error — it is what CLINICAL_DRIVER=local
     * exists for — so callers that can degrade gracefully check this first.
     */
    public function isConfigured(): bool
    {
        return ! empty(config('services.clinical.url'));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = [], array $options = []): array
    {
        return $this->send('get', $path, $query, $options);
    }

    /**
     * Mutating verbs take an idempotency key. §7 is worth reading in full:
     * the protection is opt-in, and a caller that omits the key gets none.
     * The failure it prevents is ordinary — a tablet loses signal mid-request,
     * the client retries, and one dose is administered twice.
     *
     * Derive the key from the *logical action* (dose 4821, attempt N/A), never
     * from the HTTP attempt, or every retry looks like a new action.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload = [], array $options = []): array
    {
        return $this->send('post', $path, $payload, $options);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function patch(string $path, array $payload = [], array $options = []): array
    {
        return $this->send('patch', $path, $payload, $options);
    }

    /**
     * PUT, used by the settings endpoints that replace a collection wholesale
     * rather than merging into it — group members, care-team rosters, process
     * step lists. The distinction from PATCH is meaningful there: a PUT with a
     * member omitted removes that member.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function put(string $path, array $payload = [], array $options = []): array
    {
        return $this->send('put', $path, $payload, $options);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function delete(string $path, array $payload = [], array $options = []): array
    {
        return $this->send('delete', $path, $payload, $options);
    }

    /**
     * Returns the full envelope rather than just `data`, for the handful of
     * collection endpoints where `meta.count` matters to the caller.
     *
     * @param  array<string, mixed>  $query
     * @return array{data: mixed, meta: array<string, mixed>}
     */
    public function getEnvelope(string $path, array $query = [], array $options = []): array
    {
        $response = $this->execute('get', $path, $query, $options);
        $body = $this->decode($response);

        return [
            'data' => $body['data'] ?? [],
            'meta' => $body['meta'] ?? [],
        ];
    }

    /**
     * Liveness probe (§2). The only public endpoint — deliberately
     * unauthenticated so load balancers can reach it — and the fastest way to
     * tell "Clinical is down" from "my service key is wrong".
     *
     * @return array{ok: bool, status: string, checks: array<string, mixed>}
     */
    public function health(): array
    {
        try {
            $response = $this->request(withIdentity: false)->get($this->url('/health'));
            $data = $this->decode($response)['data'] ?? [];

            return [
                'ok' => $response->successful() && ($data['status'] ?? null) === 'ok',
                'status' => (string) ($data['status'] ?? 'unreachable'),
                'checks' => (array) ($data['checks'] ?? []),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'status' => 'unreachable', 'checks' => []];
        }
    }

    /**
     * Calls an endpoint that does not use the standard envelope and returns
     * the decoded body verbatim.
     *
     * FHIR is the reason this exists (§13): it answers `application/fhir+json`
     * with a Bundle or a Resource at the top level, and failures come back as
     * an OperationOutcome rather than our `{ message, errors }` shape. Forcing
     * it through the envelope unwrapper would silently return an empty array
     * for every successful read.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function getRaw(string $path, array $query = [], array $options = []): array
    {
        $response = $this->execute('get', $path, $query, $options + ['accept' => 'application/fhir+json']);

        return $this->decode($response);
    }

    /**
     * Downloads a binary/document response (IPS exports, §13).
     */
    public function download(string $path, array $query = [], array $options = []): string
    {
        return $this->execute('get', $path, $query, $options)->body();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $data, array $options): array
    {
        $response = $this->execute($method, $path, $data, $options);

        return (array) ($this->decode($response)['data'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function execute(string $method, string $path, array $data, array $options): Response
    {
        if (! $this->isConfigured()) {
            throw new ClinicalUnavailableException(
                'The Clinical Module is not configured (CLINICAL_MODULE_URL is empty).',
                503,
                'CLINICAL_NOT_CONFIGURED'
            );
        }

        $requestId = $options['request_id'] ?? (string) Str::uuid();

        $request = $this->request(
            withIdentity: $options['with_identity'] ?? true,
            businessId: $options['business_id'] ?? null,
            requestId: $requestId,
            accept: $options['accept'] ?? 'application/json',
        );

        // §3.3: the diagnostic-engine callbacks authenticate by signature
        // instead of service key. Sign the exact bytes sent — re-serialising
        // the body before signing is the commonest integration bug there is,
        // so the encoded body is computed once and reused for both.
        if (! empty($options['hmac_secret'])) {
            $body = json_encode($data);

            $request = $request
                ->withHeaders(['X-KashTre-Signature' => hash_hmac('sha256', $body, (string) $options['hmac_secret'])])
                ->withBody($body, 'application/json');

            $data = [];
        }

        if (! empty($options['idempotency_key'])) {
            $request = $request->withHeaders([
                'X-Idempotency-Key' => (string) $options['idempotency_key'],
            ]);
        }

        // Off-premises device attestation (§8.2). Passed straight through from
        // whatever the client device supplied — this server cannot mint them.
        if (! empty($options['device_headers'])) {
            $request = $request->withHeaders((array) $options['device_headers']);
        }

        try {
            $response = $request->{$method}($this->url($path), $data);
        } catch (ConnectionException $e) {
            // Never reached Clinical, so nothing was recorded there and a
            // retry is safe — which is exactly why mutating callers should be
            // sending an idempotency key regardless.
            throw new ClinicalUnavailableException(
                'The Clinical Module could not be reached.',
                503,
                'CLINICAL_UNREACHABLE',
                [],
                $requestId,
                $e
            );
        }

        if ($response->failed()) {
            throw $this->exceptionFor($response, $requestId);
        }

        $this->recordReplay($response, $path);

        return $response;
    }

    private function request(
        bool $withIdentity = true,
        ?int $businessId = null,
        ?string $requestId = null,
        string $accept = 'application/json',
    ): PendingRequest {
        $headers = [
            'Accept' => $accept,
            'X-Service-Key' => (string) config('services.clinical.service_key'),
            'X-Tenant-Id' => $this->context->tenantId($businessId),
        ];

        if ($requestId) {
            // §5: Clinical echoes this back on the response and stamps it on
            // every log line at their end. Propagating ours makes a single
            // request traceable across both modules.
            $headers['X-Request-Id'] = $requestId;
        }

        if ($withIdentity) {
            $headers += $this->context->identityHeaders();
        }

        return Http::withHeaders($headers)
            ->timeout((int) config('services.clinical.timeout', 10))
            // Retries transport failures only. A 4xx refusal is never retried
            // — the answer will not change, and replaying a CDSS block or a
            // ReBAC denial just burns the clinician's time.
            ->retry(
                (int) config('services.clinical.retry_times', 2),
                (int) config('services.clinical.retry_sleep_ms', 250),
                fn ($exception) => $exception instanceof ConnectionException,
                throw: false,
            )
            ->asJson();
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.clinical.url'), '/')
            .'/api/v1/'
            .ltrim($path, '/');
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $body = $response->json();

        return is_array($body) ? $body : [];
    }

    /**
     * §6 status → typed exception. The mapping is what lets a Livewire
     * component write `catch (ClinicalSafetyBlockException $e)` and prompt for
     * an override, instead of string-matching a message.
     */
    private function exceptionFor(Response $response, string $requestId): ClinicalApiException
    {
        $body = $this->decode($response);
        $status = $response->status();
        $errors = (array) ($body['errors'] ?? []);
        $errorCode = $errors['error_code'] ?? null;

        // Clinical's own request_id wins over ours — it is the one on their
        // log lines, and it is what support will ask for.
        $requestId = $body['request_id'] ?? $requestId;

        $message = $body['message'] ?? match ($status) {
            401 => 'The Clinical Module rejected our credentials.',
            403 => 'You do not have access to this patient record.',
            404 => 'That clinical record could not be found.',
            503 => 'The Clinical Module is temporarily unavailable.',
            default => "The Clinical Module returned HTTP {$status}.",
        };

        Log::warning('Clinical API refused a request', [
            'status' => $status,
            'error_code' => $errorCode,
            'request_id' => $requestId,
            'path' => $response->effectiveUri()?->getPath(),
        ]);

        $arguments = [$message, $status, $errorCode, $errors, $requestId];

        return match (true) {
            $status === 401 => new ClinicalAuthException(...$arguments),
            $status === 403 => new ClinicalAccessDeniedException(...$arguments),
            $status === 409 => new ClinicalChartLockedException(...$arguments),
            $status === 428 => new ClinicalBiometricRequiredException(...$arguments),
            $status === 422 && $errorCode === 'CDSS_HARD_BLOCK' => new ClinicalSafetyBlockException(...$arguments),
            $status === 422 => new ClinicalRuleRefusedException(...$arguments),
            $status >= 500 => new ClinicalUnavailableException(...$arguments),
            default => new ClinicalApiException(...$arguments),
        };
    }

    /**
     * §7: a replayed response is a signal, not a problem — it means our retry
     * did the right thing and the action was performed exactly once. Worth a
     * log line, because a *persistent* stream of replays means our keys are
     * too coarse and distinct actions are colliding.
     */
    private function recordReplay(Response $response, string $path): void
    {
        if ($response->header('X-Idempotent-Replay') === 'true') {
            Log::info('Clinical API replayed an idempotent request', [
                'path' => $path,
                'request_id' => $response->header('X-Request-Id'),
            ]);
        }
    }
}
