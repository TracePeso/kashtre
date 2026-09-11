<?php

namespace App\Domain\Units\Exceptions;

use RuntimeException;

final class ConversionException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function unknownUnit(string $publicId): self
    {
        return new self('UNKNOWN_UNIT', 'Unit was not found.', ['unitPublicId' => $publicId]);
    }

    public static function inactiveUnit(string $publicId): self
    {
        return new self('INACTIVE_UNIT', 'Unit is not active for conversion.', ['unitPublicId' => $publicId]);
    }

    public static function dimensionMismatch(array $from, array $to): self
    {
        return new self(
            'DIMENSION_MISMATCH',
            'Units are not dimensionally compatible without an approved contextual bridge.',
            ['sourceDimension' => $from, 'targetDimension' => $to]
        );
    }

    public static function contextRequired(string $type): self
    {
        return new self('CONTEXT_REQUIRED', $type.' context is required.', ['contextType' => $type]);
    }

    public static function ruleMissing(string $fromPublicId, string $toPublicId): self
    {
        return new self('RULE_MISSING', 'No approved conversion rule exists.', [
            'from' => $fromPublicId,
            'to' => $toPublicId,
        ]);
    }

    public static function ambiguousRule(string $fromPublicId, string $toPublicId): self
    {
        return new self('AMBIGUOUS_RULE', 'More than one preferred conversion rule matches.', [
            'from' => $fromPublicId,
            'to' => $toPublicId,
        ]);
    }

    public static function outOfRange(string $value): self
    {
        return new self('OUT_OF_RANGE', 'Input value is outside the rule applicability range.', [
            'value' => $value,
        ]);
    }
}
