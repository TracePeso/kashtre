<?php

namespace App\Domain\Time\Models;

use Illuminate\Database\Eloquent\Model;

class TimeTenantSetting extends Model
{
    protected $table = 'core_time_tenant_settings';

    protected $fillable = [
        'tenant_key',
        'day_rollover_offset_minutes',
        'enforce_financial_periods',
        'allow_user_presentation_timezone',
        'metadata',
    ];

    protected $casts = [
        'day_rollover_offset_minutes' => 'integer',
        'enforce_financial_periods' => 'boolean',
        'allow_user_presentation_timezone' => 'boolean',
        'metadata' => 'array',
    ];

    public static function forTenant(string $tenantKey): self
    {
        return static::query()->firstOrCreate(
            ['tenant_key' => $tenantKey],
            [
                'day_rollover_offset_minutes' => (int) config('time.day_rollover.offset_minutes', 0),
                'enforce_financial_periods' => false,
                'allow_user_presentation_timezone' => true,
            ],
        );
    }
}
