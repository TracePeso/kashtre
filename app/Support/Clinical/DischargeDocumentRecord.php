<?php

namespace App\Support\Clinical;

/**
 * An attested discharge document — API_GUIDE_V6.1 §1 (Care Transitions,
 * Volume 8). Immutable once attested: there is no correction/amend/supersede
 * endpoint. A wrong document means issuing a new one.
 */
class DischargeDocumentRecord
{
    public function __construct(
        public readonly string $publicId,
        public readonly string $status,
        public readonly int $versionNo,
        public readonly ?string $attestedAt,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        return new self(
            publicId: (string) ($payload['public_id'] ?? ''),
            status: (string) ($payload['status'] ?? ''),
            versionNo: (int) ($payload['version_no'] ?? 1),
            attestedAt: $payload['attested_at'] ?? null,
        );
    }
}
