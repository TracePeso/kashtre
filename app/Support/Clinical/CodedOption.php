<?php

namespace App\Support\Clinical;

use Illuminate\Database\Eloquent\Model;

/**
 * A code/label pair from one of the settings dictionaries — reason codes,
 * routes, frequencies, delivery modes.
 *
 * API Integration Guide §10.9: "Never hardcode a clinical value in your
 * module." These are all tenant-configurable, which is precisely why they
 * arrive as data rather than as a PHP enum.
 *
 * Exposes both `code` and `reason_code` for the same value because the local
 * tables disagree with each other — pharmacy_route_frequency_master uses
 * `code`, clinical_reason_codes uses `reason_code` — and the Blade views were
 * written against whichever they had.
 */
class CodedOption
{
    public function __construct(
        public readonly string $code,
        public readonly string $display_label,
        public readonly ?int $minute_interval = null,
        /**
         * Reason codes only: this reason is not self-explanatory and the
         * clinician must type a justification. Break-glass and CDSS overrides
         * are reviewed by the Medical Director, and "OVERRIDE_OTHER" with no
         * note is an audit record nobody can act on.
         */
        public readonly bool $requires_free_text = false,
    ) {
    }

    /** Alias so reason-code dropdowns render unchanged. */
    public function __get(string $name): mixed
    {
        return $name === 'reason_code' ? $this->code : null;
    }

    public function __isset(string $name): bool
    {
        return $name === 'reason_code';
    }

    public static function fromModel(Model $model): self
    {
        return new self(
            code: (string) ($model->code ?? $model->reason_code ?? ''),
            display_label: (string) ($model->display_label ?? $model->label ?? ''),
            minute_interval: isset($model->minute_interval) ? (int) $model->minute_interval : null,
            requires_free_text: (bool) ($model->requires_free_text ?? false),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        return new self(
            code: (string) ($payload['code'] ?? $payload['reason_code'] ?? ''),
            display_label: (string) ($payload['display_label'] ?? $payload['label'] ?? ''),
            minute_interval: isset($payload['minute_interval']) ? (int) $payload['minute_interval'] : null,
            requires_free_text: (bool) ($payload['requires_free_text'] ?? false),
        );
    }
}
