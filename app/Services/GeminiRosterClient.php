<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class GeminiRosterClient
{
    private string $baseUrl;
    private string $apiKey;
    private string $model;
    private bool $verifySsl;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.gemini.base_url', ''), '/');
        $this->apiKey = (string) config('services.gemini.api_key', '');
        $this->model = (string) config('services.gemini.model', 'gemini-2.5-flash');
        $this->verifySsl = (bool) config('services.gemini.verify_ssl', true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function generateAssignments(string $prompt): array
    {
        if ($this->apiKey === '') {
            throw ValidationException::withMessages([
                'roster' => 'Set GEMINI_API_KEY before using AI roster generation.',
            ]);
        }

        $responsesTried = [];
        $lastParseFailure = null;

        foreach ($this->requestVariants($prompt) as $variant) {
            try {
                $response = $this->sendGenerateContentRequest($variant['payload']);
            } catch (Throwable $exception) {
                Log::error($variant['transport_log_message'], $this->diagnosticContext([
                    'exception_class' => $exception::class,
                    'exception_message' => $exception->getMessage(),
                ]));

                throw ValidationException::withMessages([
                    'roster' => 'AI roster generation is temporarily unavailable. Please try again.',
                ]);
            }

            $responsesTried[] = [
                'label' => $variant['label'],
                'response' => $response,
            ];

            if (! $response->successful()) {
                if ($response->status() === 400 && ($variant['can_fallback_to_next'] ?? false)) {
                    Log::warning($variant['schema_retry_log_message'], $this->responseContext($response));
                    continue;
                }

                throw ValidationException::withMessages([
                    'roster' => $this->errorMessageForResponse($response),
                ]);
            }

            $parsed = $this->extractAssignmentsFromResponse($response);

            if ($parsed['assignments'] !== null) {
                if ($variant['label'] !== 'plain_json_mode') {
                    Log::info($variant['success_log_message'], $this->responseContext($response, [
                        'attempt_label' => $variant['label'],
                        'responses_tried' => array_column($responsesTried, 'label'),
                    ]));
                }

                return $parsed['assignments'];
            }

            $lastParseFailure = $parsed['message'];
            Log::warning($variant['parse_retry_log_message'], $this->responseContext($response, [
                'attempt_label' => $variant['label'],
                'parse_failure' => $parsed['message'],
                'response_text' => $parsed['response_text'],
            ]));
        }

        throw ValidationException::withMessages([
            'roster' => $lastParseFailure ?: 'Gemini returned an unreadable roster response.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendGenerateContentRequest(array $payload): Response
    {
        return Http::acceptJson()
            ->asJson()
            ->withOptions([
                'verify' => $this->verifySsl,
            ])
            ->withHeaders([
                'x-goog-api-key' => $this->apiKey,
            ])
            ->connectTimeout((int) config('services.gemini.connect_timeout', 10))
            ->timeout((int) config('services.gemini.timeout', 120))
            ->baseUrl($this->baseUrl)
            ->post(sprintf('/models/%s:generateContent', $this->model), $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function structuredPayload(string $prompt, string $schemaField): array
    {
        $generationConfig = [
            'temperature' => 0.7,
            'candidateCount' => max(1, (int) config('services.gemini.roster_candidate_count', 2)),
            'maxOutputTokens' => max(1024, (int) config('services.gemini.roster_max_output_tokens', 8192)),
            'responseMimeType' => 'application/json',
        ];

        $generationConfig[$schemaField] = $this->assignmentSchema();

        return [
            'systemInstruction' => [
                'parts' => [[
                    'text' => 'You generate duty roster assignments. Return strict JSON only.',
                ]],
            ],
            'contents' => [[
                'parts' => [[
                    'text' => $prompt,
                ]],
            ]],
            'generationConfig' => $generationConfig,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackPayload(string $prompt): array
    {
        return [
            'systemInstruction' => [
                'parts' => [[
                    'text' => 'Return only a single JSON object with an assignments array. Do not wrap the JSON in markdown.',
                ]],
            ],
            'contents' => [[
                'parts' => [[
                    'text' => $prompt,
                ]],
            ]],
            'generationConfig' => [
                'temperature' => 0.7,
                'candidateCount' => max(1, (int) config('services.gemini.roster_candidate_count', 2)),
                'maxOutputTokens' => max(1024, (int) config('services.gemini.roster_max_output_tokens', 8192)),
                'responseMimeType' => 'application/json',
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, payload: array<string, mixed>, can_fallback_to_next: bool, transport_log_message: string, schema_retry_log_message: string, parse_retry_log_message: string, success_log_message: string}>
     */
    private function requestVariants(string $prompt): array
    {
        return [
            [
                'label' => 'response_schema',
                'payload' => $this->structuredPayload($prompt, 'responseSchema'),
                'can_fallback_to_next' => true,
                'transport_log_message' => 'Gemini roster responseSchema request failed to connect.',
                'schema_retry_log_message' => 'Gemini roster responseSchema request was rejected; retrying with responseJsonSchema.',
                'parse_retry_log_message' => 'Gemini roster responseSchema request returned unusable content; trying the next response strategy.',
                'success_log_message' => 'Gemini roster responseSchema request succeeded.',
            ],
            [
                'label' => 'response_json_schema',
                'payload' => $this->structuredPayload($prompt, 'responseJsonSchema'),
                'can_fallback_to_next' => true,
                'transport_log_message' => 'Gemini roster responseJsonSchema request failed to connect.',
                'schema_retry_log_message' => 'Gemini roster responseJsonSchema request was rejected; retrying without schema.',
                'parse_retry_log_message' => 'Gemini roster responseJsonSchema request returned unusable content; trying plain JSON mode.',
                'success_log_message' => 'Gemini roster responseJsonSchema request succeeded.',
            ],
            [
                'label' => 'plain_json_mode',
                'payload' => $this->fallbackPayload($prompt),
                'can_fallback_to_next' => false,
                'transport_log_message' => 'Gemini roster plain JSON request failed to connect.',
                'schema_retry_log_message' => 'Gemini roster plain JSON request failed.',
                'parse_retry_log_message' => 'Gemini roster plain JSON request returned unusable content.',
                'success_log_message' => 'Gemini roster plain JSON request succeeded.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function assignmentSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'assignments' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'staff_assignment_id' => ['type' => 'integer'],
                            'date' => ['type' => 'string'],
                            'shift_type_id' => ['type' => 'integer'],
                            'reason' => ['type' => 'string'],
                        ],
                        'required' => ['staff_assignment_id', 'date', 'shift_type_id'],
                    ],
                ],
            ],
            'required' => ['assignments'],
        ];
    }

    /**
     * @return array{assignments: array<int, array<string, mixed>>|null, message: string, response_text: string}
     */
    private function extractAssignmentsFromResponse(Response $response): array
    {
        $promptBlockReason = (string) $response->json('promptFeedback.blockReason', '');

        if ($promptBlockReason !== '') {
            return [
                'assignments' => null,
                'message' => 'Gemini blocked the roster prompt and returned no candidates.',
                'response_text' => $this->truncateForLogs((string) $response->body()),
            ];
        }

        $candidates = $response->json('candidates');

        if (! is_array($candidates) || $candidates === []) {
            return [
                'assignments' => null,
                'message' => 'Gemini returned no roster candidates.',
                'response_text' => $this->truncateForLogs((string) $response->body()),
            ];
        }

        $bestAssignments = null;
        $lastFailure = 'Gemini returned an unreadable roster response.';
        $lastResponseText = '';

        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $rawText = $this->candidateText($candidate);
            $finishReason = (string) ($candidate['finishReason'] ?? '');

            if ($rawText === '') {
                $lastFailure = $finishReason === 'MAX_TOKENS'
                    ? 'Gemini stopped before completing the roster JSON. The request will be retried.'
                    : 'Gemini returned an empty roster response.';
                $lastResponseText = $this->truncateForLogs((string) json_encode($candidate));
                continue;
            }

            try {
                $decoded = json_decode($this->normalizeJsonPayload($rawText), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                $lastFailure = $finishReason === 'MAX_TOKENS'
                    ? 'Gemini stopped before completing the roster JSON. The request will be retried.'
                    : 'Gemini returned an unreadable roster response.';
                $lastResponseText = $this->truncateForLogs($rawText);
                continue;
            }

            $assignments = $this->assignmentsFromDecodedPayload($decoded);

            if ($assignments === null) {
                $lastFailure = 'Gemini did not return roster assignments in the expected format.';
                $lastResponseText = $this->truncateForLogs($rawText);
                continue;
            }

            if ($bestAssignments === null || count($assignments) > count($bestAssignments)) {
                $bestAssignments = $assignments;
            }
        }

        return [
            'assignments' => $bestAssignments,
            'message' => $lastFailure,
            'response_text' => $lastResponseText,
        ];
    }

    private function candidateText(array $candidate): string
    {
        $parts = $candidate['content']['parts'] ?? null;

        if (! is_array($parts) || $parts === []) {
            return '';
        }

        $text = collect($parts)
            ->map(fn ($part): string => is_array($part) ? trim((string) ($part['text'] ?? '')) : '')
            ->filter()
            ->implode("\n");

        return trim($text);
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function assignmentsFromDecodedPayload(mixed $decoded): ?array
    {
        if (is_array($decoded) && array_is_list($decoded)) {
            $assignments = $decoded;
        } elseif (is_array($decoded)) {
            $assignments = $decoded['assignments']
                ?? $decoded['schedule']
                ?? $decoded['entries']
                ?? $decoded['roster_assignments']
                ?? null;
        } else {
            return null;
        }

        if (! is_array($assignments)) {
            return null;
        }

        $normalized = array_values(array_filter($assignments, fn ($assignment): bool => is_array($assignment)));

        return $normalized === [] ? [] : $normalized;
    }

    private function normalizeJsonPayload(string $rawText): string
    {
        $text = trim($rawText);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;
        $text = trim($text);

        if ($text !== '' && ! str_starts_with($text, '{') && ! str_starts_with($text, '[')) {
            $objectStart = strpos($text, '{');
            $objectEnd = strrpos($text, '}');
            $arrayStart = strpos($text, '[');
            $arrayEnd = strrpos($text, ']');

            if ($objectStart !== false && $objectEnd !== false && $objectEnd >= $objectStart) {
                $text = substr($text, $objectStart, ($objectEnd - $objectStart) + 1);
            } elseif ($arrayStart !== false && $arrayEnd !== false && $arrayEnd >= $arrayStart) {
                $text = substr($text, $arrayStart, ($arrayEnd - $arrayStart) + 1);
            }
        }

        return $text;
    }

    private function errorMessageForResponse(Response $response): string
    {
        $status = $response->status();
        $body = Str::lower((string) $response->body());

        Log::warning('Gemini roster request returned an unsuccessful response.', $this->responseContext($response));

        if (in_array($status, [401, 403], true)) {
            return 'Gemini rejected the configured API credentials. Update GEMINI_API_KEY and try again.';
        }

        if ($status === 404) {
            return 'The configured Gemini model was not found. Update GEMINI_MODEL and try again.';
        }

        if ($status === 429 || $status >= 500) {
            return 'AI roster generation is temporarily unavailable. Please try again.';
        }

        if (str_contains($body, 'token') && (str_contains($body, 'limit') || str_contains($body, 'too large') || str_contains($body, 'too long'))) {
            return 'Gemini rejected this roster request as too large. The roster window will need to be split into smaller AI requests.';
        }

        return 'AI roster generation failed to get a valid response from Gemini.';
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function diagnosticContext(array $extra = []): array
    {
        return array_merge([
            'model' => $this->model,
            'base_url' => $this->baseUrl,
            'verify_ssl' => $this->verifySsl,
        ], $extra);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function responseContext(Response $response, array $extra = []): array
    {
        return $this->diagnosticContext(array_merge([
            'status' => $response->status(),
            'body' => $this->truncateForLogs($response->body()),
        ], $extra));
    }

    private function truncateForLogs(?string $value): string
    {
        return Str::limit(trim((string) $value), 2000);
    }
}
