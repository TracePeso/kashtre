<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class HrPolicyVersion extends Model
{
    use SoftDeletes;

    public const HOLIDAY_COMPENSATORY_DYNAMIC_PERCENTAGE_25 = 0.25;
    public const HOLIDAY_COMPENSATORY_DYNAMIC_PERCENTAGE_50 = 0.50;
    public const HOLIDAY_COMPENSATORY_DYNAMIC_PERCENTAGE_75 = 0.75;
    public const HOLIDAY_COMPENSATORY_DYNAMIC_PERCENTAGE_100 = 1.00;

    public const HOLIDAY_COMPENSATORY_SCOPE_CROSSING_PUBLIC_HOLIDAY = 'crossing_public_holiday';
    public const HOLIDAY_COMPENSATORY_SCOPE_WITHIN_PUBLIC_HOLIDAY = 'within_public_holiday';

    public const HOLIDAY_COMPENSATORY_CREDIT_NONE = 'none';
    public const HOLIDAY_COMPENSATORY_CREDIT_PER_SHIFT = 'per_shift';
    public const HOLIDAY_COMPENSATORY_CREDIT_PER_PUBLIC_HOLIDAY_DATE = 'per_public_holiday_date';

    protected $fillable = [
        'uuid',
        'organization_id',
        'regional_policy_id',
        'version_label',
        'effective_from',
        'effective_to',
        'is_active',
        'weekly_standard_minutes',
        'weekly_absolute_ceiling_minutes',
        'daily_net_cap_minutes',
        'minimum_rest_gap_minutes',
        'consecutive_work_days_limit',
        'rest_after_consecutive_days_minutes',
        'anchor_window_minutes',
        'overtime_trigger_minutes',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
        'weekly_standard_minutes' => 'integer',
        'weekly_absolute_ceiling_minutes' => 'integer',
        'daily_net_cap_minutes' => 'integer',
        'minimum_rest_gap_minutes' => 'integer',
        'consecutive_work_days_limit' => 'integer',
        'rest_after_consecutive_days_minutes' => 'integer',
        'anchor_window_minutes' => 'integer',
        'overtime_trigger_minutes' => 'integer',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->uuid = $model->uuid ?? (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function regionalPolicy()
    {
        return $this->belongsTo(HrRegionalPolicy::class, 'regional_policy_id');
    }

    public static function holidayCompensatoryCreditPolicyOptions(): array
    {
        return [
            self::HOLIDAY_COMPENSATORY_CREDIT_NONE => 'No compensatory credit',
            self::HOLIDAY_COMPENSATORY_CREDIT_PER_SHIFT => 'One credit per paired shift',
            self::HOLIDAY_COMPENSATORY_CREDIT_PER_PUBLIC_HOLIDAY_DATE => 'One credit per public holiday date',
        ];
    }

    public static function holidayCompensatoryCreditScopeOptions(): array
    {
        return [
            self::HOLIDAY_COMPENSATORY_SCOPE_CROSSING_PUBLIC_HOLIDAY => 'Shifts crossing public holidays',
            self::HOLIDAY_COMPENSATORY_SCOPE_WITHIN_PUBLIC_HOLIDAY => 'Shifts fully within public holidays',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function holidayCompensatoryDynamicPercentageOptions(): array
    {
        return [
            '0.25' => '25%',
            '0.50' => '50%',
            '0.75' => '75%',
            '1.00' => '100%',
        ];
    }

    /**
     * @return array<string, array{rule: string, credit_days: float}>
     */
    public static function defaultHolidayCompensatoryCreditSettings(): array
    {
        return [
            self::HOLIDAY_COMPENSATORY_SCOPE_CROSSING_PUBLIC_HOLIDAY => [
                'rule' => self::HOLIDAY_COMPENSATORY_CREDIT_PER_SHIFT,
                'credit_days' => 1.0,
            ],
            self::HOLIDAY_COMPENSATORY_SCOPE_WITHIN_PUBLIC_HOLIDAY => [
                'rule' => self::HOLIDAY_COMPENSATORY_CREDIT_PER_SHIFT,
                'credit_days' => 1.0,
            ],
        ];
    }

    /**
     * @return array<string, array{rule: string, credit_days: float}>
     */
    public function holidayCompensatoryCreditSettings(): array
    {
        $defaults = self::defaultHolidayCompensatoryCreditSettings();
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $stored = $metadata['holiday_compensatory_credit_settings'] ?? null;

        if (! is_array($stored)) {
            $legacyPolicy = $metadata['holiday_compensatory_credit_policy'] ?? null;

            if (array_key_exists((string) $legacyPolicy, self::holidayCompensatoryCreditPolicyOptions())) {
                $defaults[self::HOLIDAY_COMPENSATORY_SCOPE_CROSSING_PUBLIC_HOLIDAY]['rule'] = (string) $legacyPolicy;
                $defaults[self::HOLIDAY_COMPENSATORY_SCOPE_WITHIN_PUBLIC_HOLIDAY]['rule'] = (string) $legacyPolicy;
            }

            return $defaults;
        }

        return collect($defaults)
            ->mapWithKeys(function (array $defaultConfig, string $scope) use ($stored): array {
                $rawConfig = is_array($stored[$scope] ?? null) ? $stored[$scope] : [];
                $rule = $rawConfig['rule'] ?? $defaultConfig['rule'];
                $creditDays = (float) ($rawConfig['credit_days'] ?? $defaultConfig['credit_days']);

                if (! array_key_exists((string) $rule, self::holidayCompensatoryCreditPolicyOptions())) {
                    $rule = $defaultConfig['rule'];
                }

                $creditDays = self::normalizeHolidayCompensatoryCreditDays($creditDays);

                return [
                    $scope => [
                        'rule' => (string) $rule,
                        'credit_days' => $creditDays > 0 ? $creditDays : 0.0,
                    ],
                ];
            })
            ->all();
    }

    /**
     * @return array{rule: string, credit_days: float}
     */
    public function holidayCompensatoryCreditSettingFor(string $scope): array
    {
        $settings = $this->holidayCompensatoryCreditSettings();

        return $settings[$scope] ?? self::defaultHolidayCompensatoryCreditSettings()[$scope];
    }

    public function holidayCompensatoryCreditPolicy(): string
    {
        return $this->holidayCompensatoryCreditSettingFor(self::HOLIDAY_COMPENSATORY_SCOPE_CROSSING_PUBLIC_HOLIDAY)['rule'];
    }

    public function holidayCompensatoryCreditPolicyLabel(): string
    {
        return self::holidayCompensatoryCreditPolicyOptions()[$this->holidayCompensatoryCreditPolicy()];
    }

    /**
     * @return array<int, string>
     */
    public function holidayCompensatoryCreditSettingLabels(): array
    {
        return collect(self::holidayCompensatoryCreditScopeOptions())
            ->map(function (string $scopeLabel, string $scope): string {
                $setting = $this->holidayCompensatoryCreditSettingFor($scope);
                $ruleLabel = self::holidayCompensatoryCreditPolicyOptions()[$setting['rule']] ?? $setting['rule'];
                $creditDays = rtrim(rtrim(number_format((float) $setting['credit_days'], 2, '.', ''), '0'), '.');

                if ($scope === self::HOLIDAY_COMPENSATORY_SCOPE_CROSSING_PUBLIC_HOLIDAY) {
                    return sprintf(
                        '%s: %s with dynamic 25%%/50%%/75%%/100%% of %s whole-day credit',
                        $scopeLabel,
                        $ruleLabel,
                        $creditDays
                    );
                }

                return sprintf('%s: %s at %s day(s)', $scopeLabel, $ruleLabel, $creditDays);
            })
            ->values()
            ->all();
    }

    public static function normalizeHolidayCompensatoryCreditDays(float $creditDays): float
    {
        return max(0.0, round($creditDays * 4) / 4);
    }
}
