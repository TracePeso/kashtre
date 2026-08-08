<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class KashtreClinicalModuleSetting extends Model
{
    protected $fillable = [
        'url',
        'service_key',
        'inbound_api_key',
        'encounter_webhook_enabled',
    ];

    protected $casts = [
        'service_key' => 'encrypted',
        'inbound_api_key' => 'encrypted',
        'encounter_webhook_enabled' => 'boolean',
    ];

    protected $hidden = [
        'service_key',
        'inbound_api_key',
    ];

    public static function resolved(): self
    {
        return Cache::remember('kashtre.clinical_module_settings', 300, function () {
            $settings = static::query()->first();

            if ($settings === null) {
                $settings = static::query()->create(static::defaultAttributes());
            }

            return $settings;
        });
    }

    public static function forgetCache(): void
    {
        Cache::forget('kashtre.clinical_module_settings');
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultAttributes(): array
    {
        return [
            'url' => rtrim((string) config('services.clinical_module.url', ''), '/'),
            'service_key' => config('services.clinical_module.service_key'),
            'inbound_api_key' => config('services.clinical_module.inbound_api_key'),
            'encounter_webhook_enabled' => (bool) config('services.clinical_module.encounter_webhook_enabled', true),
        ];
    }

    public function baseUrl(): string
    {
        return rtrim(trim((string) ($this->url ?? '')), '/');
    }

    public function serviceKey(): string
    {
        return trim((string) ($this->service_key ?? ''));
    }

    public function inboundApiKey(): string
    {
        return trim((string) ($this->inbound_api_key ?? ''));
    }

    public function isEncounterWebhookEnabled(): bool
    {
        return (bool) $this->encounter_webhook_enabled;
    }

    public function isConfiguredForOutbound(): bool
    {
        return $this->baseUrl() !== '' && $this->serviceKey() !== '';
    }
}
