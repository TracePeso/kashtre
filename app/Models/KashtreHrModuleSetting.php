<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class KashtreHrModuleSetting extends Model
{
    protected $fillable = [
        'url',
        'api_key',
        'sync_enabled',
    ];

    protected $casts = [
        'sync_enabled' => 'boolean',
        'api_key' => 'encrypted',
    ];

    protected $hidden = [
        'api_key',
    ];

    public static function resolved(): self
    {
        return Cache::remember('kashtre.hr_module_settings', 300, function () {
            $settings = static::query()->first();

            if ($settings === null) {
                $settings = static::query()->create(static::defaultAttributes());
            }

            return $settings;
        });
    }

    public static function forgetCache(): void
    {
        Cache::forget('kashtre.hr_module_settings');
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultAttributes(): array
    {
        return [
            'url' => rtrim((string) config('services.hr_module.url', ''), '/'),
            'api_key' => config('services.hr_module.api_key'),
            'sync_enabled' => (bool) config('services.hr_module.sync_enabled', true),
        ];
    }

    public function baseUrl(): string
    {
        return rtrim(trim((string) ($this->url ?? '')), '/');
    }

    public function apiKey(): string
    {
        return trim((string) ($this->api_key ?? ''));
    }

    public function isSyncEnabled(): bool
    {
        return (bool) $this->sync_enabled;
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl() !== '' && $this->apiKey() !== '';
    }
}
