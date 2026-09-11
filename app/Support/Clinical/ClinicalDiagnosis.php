<?php

namespace App\Support\Clinical;

use Illuminate\Database\Eloquent\Model;

/**
 * A recorded condition on a patient's problem list.
 *
 * Main's local table calls the free-text field `description` and treats the
 * ICD-11 code as optional; the Clinical Module requires both a code and a
 * `display_label`. This carries both names so a panel written against either
 * shape renders unchanged.
 */
class ClinicalDiagnosis
{
    public function __construct(
        public readonly string $description,
        public readonly ?string $icd11_code = null,
        public readonly ?string $visit_id = null,
        public readonly ?string $clinical_status = null,
        public readonly ?string $recorded_at = null,
        public readonly ?string $recorded_by = null,
        public readonly int|string|null $id = null,
    ) {
    }

    /** Alias so views written against the local model keep working. */
    public function __get(string $name): mixed
    {
        return $name === 'display_label' ? $this->description : null;
    }

    public function __isset(string $name): bool
    {
        return $name === 'display_label';
    }

    public static function fromModel(Model $model): self
    {
        return new self(
            description: (string) ($model->description ?? ''),
            icd11_code: $model->icd11_code ? (string) $model->icd11_code : null,
            visit_id: $model->visit_id ? (string) $model->visit_id : null,
            clinical_status: $model->clinical_status ?? null,
            recorded_at: $model->recorded_at?->toIso8601String(),
            recorded_by: $model->recorded_by_user_id ? (string) $model->recorded_by_user_id : null,
            id: $model->id,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        return new self(
            description: (string) ($payload['display_label'] ?? $payload['description'] ?? ''),
            icd11_code: isset($payload['icd11_code']) ? (string) $payload['icd11_code'] : null,
            visit_id: isset($payload['visit_id']) ? (string) $payload['visit_id'] : null,
            // Clinical names these status / created_at / confirmed_by_user_id;
            // the local table uses clinical_status / recorded_at.
            clinical_status: self::firstFilled($payload, ['status', 'clinical_status', 'diagnosis_type']),
            recorded_at: self::firstFilled($payload, ['created_at', 'recorded_at', 'confirmed_at']),
            recorded_by: self::firstFilled($payload, ['confirmed_by_user_id', 'recorded_by', 'recorded_by_user_id']),
            id: $payload['id'] ?? null,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $keys
     */
    private static function firstFilled(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($payload[$key]) && $payload[$key] !== '') {
                return (string) $payload[$key];
            }
        }

        return null;
    }
}
