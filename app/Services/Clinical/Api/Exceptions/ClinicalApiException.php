<?php

namespace App\Services\Clinical\Api\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base for every non-2xx response from the Clinical Module (API Integration
 * Guide §6). The guide's failure envelope is
 *
 *   { "message": "...", "errors": { "error_code": "...", ... }, "request_id": "..." }
 *
 * so `message` is written to be shown to a clinician, `errorCode` is what
 * code branches on, and `requestId` is what you quote when raising a ticket
 * — it appears on every Clinical log line. Always log it.
 *
 * Subclasses exist only for the refusals a caller is expected to *handle*
 * differently (a CDSS block needs an override prompt; a ReBAC denial needs a
 * break-glass prompt). Everything else surfaces as this base class with the
 * status and error code intact.
 */
class ClinicalApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $errors
     */
    public function __construct(
        string $message,
        private readonly int $status = 0,
        private readonly ?string $errorCode = null,
        private readonly array $errors = [],
        private readonly ?string $requestId = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * Retrying the identical request is pointless for a refusal (the answer
     * will not change) but sensible for a degraded dependency.
     */
    public function isRetryable(): bool
    {
        return $this->status === 503 || $this->status === 0;
    }

    /**
     * Structured form for logging. Never log the request body alongside this
     * — clinical payloads are patient data.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'status' => $this->status,
            'error_code' => $this->errorCode,
            'request_id' => $this->requestId,
        ];
    }
}
