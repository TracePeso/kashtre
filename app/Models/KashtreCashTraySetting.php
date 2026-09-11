<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class KashtreCashTraySetting extends Model
{
    protected $fillable = [
        'contact_email',
        'contact_phone',
        'contact_address',
        'tagline',
        'powered_by_line',
        'website_links',
        'copyright_line',
        'is_active',
    ];

    protected $casts = [
        'website_links' => 'array',
        'is_active' => 'boolean',
    ];

    public static function resolved(): self
    {
        return Cache::remember('kashtre.cash_tray_settings', 300, function () {
            $settings = static::query()->first();

            if ($settings === null) {
                $settings = static::query()->create(static::defaultAttributes());
            }

            return $settings;
        });
    }

    public static function forgetCache(): void
    {
        Cache::forget('kashtre.cash_tray_settings');
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultAttributes(): array
    {
        return [
            'contact_email' => config('mail.kashtre_email', 'admin@kashtre.com'),
            'contact_phone' => null,
            'contact_address' => null,
            'tagline' => 'Kashtre is a product of Kashtre Ltd',
            'powered_by_line' => 'Powered by Kashtre',
            'website_links' => [
                ['label' => 'Website', 'url' => 'https://kashtre.com'],
            ],
            'copyright_line' => '© Copyright '.date('Y').' Kashtre. All Rights Reserved',
            'is_active' => true,
        ];
    }

    public function isEnabled(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    public function normalizedWebsiteLinks(): array
    {
        $links = [];

        foreach ($this->website_links ?? [] as $link) {
            $label = trim((string) ($link['label'] ?? ''));
            $url = trim((string) ($link['url'] ?? ''));

            if ($label === '' || $url === '') {
                continue;
            }

            $links[] = [
                'label' => $label,
                'url' => $url,
            ];
        }

        return $links;
    }

    public function documentPoweredByLine(): string
    {
        $line = trim((string) ($this->powered_by_line ?? ''));

        return $line !== '' ? $line : 'Powered by Kashtre';
    }

    /**
     * @return array{label: string, url: string}|null
     */
    public function primaryWebsiteLink(): ?array
    {
        $links = $this->normalizedWebsiteLinks();

        return $links[0] ?? null;
    }

    public function copyrightLine(): string
    {
        $line = trim((string) ($this->copyright_line ?? ''));

        if ($line !== '') {
            return str_replace('{year}', (string) date('Y'), $line);
        }

        return '© Copyright '.date('Y').' Kashtre. All Rights Reserved';
    }

    public function hasContactDetails(): bool
    {
        return filled($this->contact_email)
            || filled($this->contact_phone)
            || filled($this->contact_address);
    }

    public function hasDisplayContent(): bool
    {
        return $this->isEnabled() && (
            $this->hasContactDetails()
            || filled($this->tagline)
            || $this->normalizedWebsiteLinks() !== []
            || filled($this->copyright_line)
        );
    }
}
