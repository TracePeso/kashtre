<?php

namespace App\Support;

use App\Domain\Time\Enums\PolicyPurpose;
use App\Domain\Time\Enums\PolicyScopeType;
use App\Domain\Time\Enums\PolicyStatus;
use App\Domain\Time\Models\CoreTimeZone;
use App\Domain\Time\Models\TimeZonePolicy;
use App\Domain\Time\Services\SharedTimeGateway;
use App\Domain\Time\ValueObjects\LocalDate;
use App\Domain\Time\ValueObjects\TimeContext;
use App\Domain\Time\ValueObjects\UtcInstant;
use App\Models\Branch;
use App\Models\Business;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Convenience accessors for consuming modules.
 */
final class SharedTime
{
    /** @var array<string, array<string, mixed>> */
    private static array $contextCache = [];

    public static function enabled(): bool
    {
        return (bool) config('time.enabled', false);
    }

    public static function gateway(): SharedTimeGateway
    {
        return app(SharedTimeGateway::class);
    }

    public static function nowUtc(): CarbonImmutable
    {
        if (! self::enabled()) {
            return CarbonImmutable::now('UTC');
        }

        return self::gateway()->now()->toCarbon();
    }

    public static function now(): CarbonImmutable
    {
        return self::nowUtc();
    }

    public static function businessToday(?string $tenantId = null, ?string $branchId = null): string
    {
        $tenantId ??= Auth::user()?->business_id ? (string) Auth::user()?->business_id : null;
        if (! $tenantId) {
            return CarbonImmutable::today()->toDateString();
        }

        try {
            return self::gateway()->businessDates()->businessDateFor(
                null,
                new TimeContext(tenantId: $tenantId, branchId: $branchId),
                $tenantId,
            )->toString();
        } catch (\Throwable) {
            return CarbonImmutable::today()->toDateString();
        }
    }

    public static function businessDate(?string $tenantId = null): LocalDate
    {
        $tenantId ??= Auth::user()?->business_id ? (string) Auth::user()?->business_id : 'SYSTEM';

        return self::gateway()->businessDate($tenantId);
    }

    public static function captureSnapshot(?string $tenantId = null, ?string $branchId = null): array
    {
        $tenantId ??= Auth::user()?->business_id ? (string) Auth::user()?->business_id : null;

        return self::gateway()->snapshots()->capture($tenantId, $branchId);
    }

    public static function presentForUser(UtcInstant|CarbonImmutable|string $instant, ?\App\Models\User $user = null): array
    {
        $user ??= Auth::user();
        $iana = $user?->presentation_timezone
            ?: (string) config('time.default_timezone', 'UTC');

        $utc = $instant instanceof UtcInstant
            ? $instant
            : ($instant instanceof CarbonImmutable
                ? UtcInstant::fromDateTime($instant->utc())
                : UtcInstant::fromString((string) $instant));

        return self::gateway()->present($utc, $iana);
    }

    public static function assertPeriodOpen(?string $tenantId = null, ?string $localDate = null): void
    {
        if (! self::enabled()) {
            return;
        }

        $tenantId ??= Auth::user()?->business_id ? (string) Auth::user()?->business_id : null;
        if (! $tenantId) {
            return;
        }

        $settings = \App\Domain\Time\Models\TimeTenantSetting::forTenant($tenantId);
        if (! $settings->enforce_financial_periods) {
            return;
        }

        $date = $localDate
            ? LocalDate::parse($localDate)
            : self::businessDate($tenantId);

        self::gateway()->periods()->assertOpenFor($tenantId, $date);
    }

    /**
     * @return array<string, string> iana_id => label
     */
    public static function timezoneSelectOptions(): array
    {
        if (Schema::hasTable('core_time_zones') && CoreTimeZone::query()->exists()) {
            return CoreTimeZone::query()
                ->where('status', 'ACTIVE')
                ->orderBy('iana_id')
                ->get(['iana_id', 'display_name'])
                ->mapWithKeys(fn (CoreTimeZone $zone) => [
                    $zone->iana_id => ($zone->display_name ?: $zone->iana_id).' ('.$zone->iana_id.')',
                ])
                ->all();
        }

        $fallback = [
            'Africa/Kampala',
            'Africa/Nairobi',
            'Africa/Lagos',
            'Africa/Johannesburg',
            'Africa/Cairo',
            'UTC',
            'Europe/London',
            'America/New_York',
            'Asia/Dubai',
            'Asia/Kolkata',
        ];

        return collect($fallback)
            ->filter(fn (string $id) => in_array($id, timezone_identifiers_list(), true))
            ->mapWithKeys(fn (string $id) => [$id => str_replace('_', ' ', $id).' ('.$id.')'])
            ->all();
    }

    public static function timezoneValidationRule(bool $required = true, bool $allowInherit = false): array
    {
        $rule = [$required ? 'required' : 'nullable', 'string', 'max:64'];
        $options = array_keys(self::timezoneSelectOptions());
        if ($allowInherit) {
            $options[] = 'inherit';
        }
        if ($options !== []) {
            $rule[] = Rule::in($options);
        } else {
            $rule[] = Rule::in($allowInherit ? array_merge(timezone_identifiers_list(), ['inherit']) : timezone_identifiers_list());
        }

        return $rule;
    }

    public static function timezoneFromSpreadsheetRow(array $row): ?string
    {
        foreach (['timezone', 'operational_timezone', 'iana', 'iana_id'] as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null) {
                continue;
            }

            $value = trim((string) $row[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    public static function assignBusinessTimezone(Business|int|string $business, string $ianaId, ?string $reason = null): void
    {
        $tenantKey = (string) ($business instanceof Business ? $business->id : $business);
        $time = self::gateway();
        $time->catalogue()->ensureKnown($ianaId);
        self::cancelActiveScopePolicies(
            $time,
            $tenantKey,
            PolicyScopeType::TENANT,
            $tenantKey,
            $reason ?? 'Replace business timezone',
        );
        $time->setScopeTimezone(
            $tenantKey,
            PolicyScopeType::TENANT,
            $tenantKey,
            $ianaId,
            PolicyPurpose::OPERATIONAL,
            $reason ?? 'Business operational timezone',
        );
        self::$contextCache = [];
    }

    public static function assignBranchTimezone(Branch $branch, ?string $ianaId, ?string $reason = null): void
    {
        $tenantKey = (string) $branch->business_id;
        $time = self::gateway();

        if ($ianaId === null || $ianaId === '' || $ianaId === 'inherit') {
            TimeZonePolicy::query()
                ->where('tenant_key', $tenantKey)
                ->where('scope_type', PolicyScopeType::BRANCH->value)
                ->where('subject_public_id', (string) $branch->id)
                ->where('purpose', PolicyPurpose::OPERATIONAL->value)
                ->where('status', PolicyStatus::ACTIVE->value)
                ->get()
                ->each(fn (TimeZonePolicy $policy) => $time->policies()->cancel($policy, $reason ?? 'Branch inherits business timezone'));
            self::$contextCache = [];

            return;
        }

        $time->catalogue()->ensureKnown($ianaId);
        self::cancelActiveScopePolicies(
            $time,
            $tenantKey,
            PolicyScopeType::BRANCH,
            (string) $branch->id,
            $reason ?? 'Replace branch timezone',
        );
        $time->setScopeTimezone(
            $tenantKey,
            PolicyScopeType::BRANCH,
            (string) $branch->id,
            $ianaId,
            PolicyPurpose::OPERATIONAL,
            $reason ?? 'Branch timezone override',
        );
        self::$contextCache = [];
    }

    private static function cancelActiveScopePolicies(
        SharedTimeGateway $time,
        string $tenantKey,
        PolicyScopeType $scope,
        string $subjectPublicId,
        string $reason,
    ): void {
        TimeZonePolicy::query()
            ->where('tenant_key', $tenantKey)
            ->where('scope_type', $scope->value)
            ->where('subject_public_id', $subjectPublicId)
            ->where('purpose', PolicyPurpose::OPERATIONAL->value)
            ->where('status', PolicyStatus::ACTIVE->value)
            ->get()
            ->each(fn (TimeZonePolicy $policy) => $time->policies()->cancel($policy, $reason));
    }

    /**
     * @return array{
     *     ianaId: string,
     *     scopeType: string,
     *     inherited: bool,
     *     sourceLabel: string,
     *     localNow: string,
     *     abbreviation: string,
     *     businessDate: string,
     *     usedFallback: bool
     * }
     */
    public static function describe(?string $tenantId, ?string $branchId = null): array
    {
        $cacheKey = ($tenantId ?? '').'|'.($branchId ?? '');
        if (isset(self::$contextCache[$cacheKey])) {
            return self::$contextCache[$cacheKey];
        }

        $fallbackIana = (string) config('app.timezone', 'UTC');
        $now = CarbonImmutable::now($fallbackIana);

        $described = [
            'ianaId' => $fallbackIana,
            'scopeType' => 'APP',
            'inherited' => true,
            'sourceLabel' => 'App timezone',
            'localNow' => $now->format('M j, Y H:i'),
            'abbreviation' => $now->format('T'),
            'businessDate' => $now->toDateString(),
            'usedFallback' => true,
        ];

        if (! $tenantId || ! Schema::hasTable('core_time_zone_policies')) {
            return self::$contextCache[$cacheKey] = $described;
        }

        try {
            $resolved = self::gateway()->resolution()->resolve(
                new TimeContext(tenantId: $tenantId, branchId: $branchId, purpose: PolicyPurpose::OPERATIONAL),
                $tenantId,
            );
            $instant = UtcInstant::fromDateTime(CarbonImmutable::now('UTC'));
            $presented = self::gateway()->present($instant, $resolved->ianaId->value());
            $local = CarbonImmutable::parse($presented['utc'])->setTimezone($resolved->ianaId->value());
            $inherited = $resolved->scopeType !== PolicyScopeType::BRANCH;
            $sourceLabel = match ($resolved->scopeType) {
                PolicyScopeType::BRANCH => 'Branch override',
                PolicyScopeType::TENANT => $branchId ? 'Inherited from business' : 'Business timezone',
                default => $resolved->usedFallback ? 'Platform fallback' : $resolved->scopeType->value,
            };

            $described = [
                'ianaId' => $resolved->ianaId->value(),
                'scopeType' => $resolved->scopeType->value,
                'inherited' => $inherited,
                'sourceLabel' => $sourceLabel,
                'localNow' => $local->format('M j, Y H:i'),
                'abbreviation' => $presented['abbreviation'] ?? $local->format('T'),
                'businessDate' => self::businessToday($tenantId, $branchId),
                'usedFallback' => $resolved->usedFallback,
            ];
        } catch (\Throwable) {
            // Keep app-timezone fallback.
        }

        return self::$contextCache[$cacheKey] = $described;
    }

    public static function displayTimezone(?string $tenantId = null, ?string $branchId = null): string
    {
        return $tenantId
            ? self::describe($tenantId, $branchId)['ianaId']
            : self::currentUserContext()['ianaId'];
    }

    public static function timezoneForRecord(?object $record): string
    {
        if (! $record) {
            return self::displayTimezone();
        }

        if ($record instanceof Business) {
            return self::displayTimezone((string) $record->id);
        }

        if ($record instanceof Branch) {
            return self::displayTimezone((string) $record->business_id, (string) $record->id);
        }

        $tenantId = $record->business_id ?? $record->tenant_key ?? null;
        $branchId = $record->branch_id ?? null;

        return self::displayTimezone(
            $tenantId !== null && $tenantId !== '' ? (string) $tenantId : null,
            $branchId !== null && $branchId !== '' ? (string) $branchId : null,
        );
    }

    public static function nowLocal(?string $tenantId = null, ?string $branchId = null): CarbonImmutable
    {
        return self::nowUtc()->setTimezone(self::displayTimezone($tenantId, $branchId));
    }

    /**
     * @return array{start: string, end: string, localDate: string, ianaId: string}
     */
    public static function dayWindow(?string $localDate = null, ?string $tenantId = null, ?string $branchId = null): array
    {
        $context = $tenantId
            ? self::describe($tenantId, $branchId)
            : self::currentUserContext();
        $iana = $context['ianaId'];
        $dateString = $localDate ?: ($context['businessDate'] ?? self::businessToday($tenantId, $branchId));
        $storageTz = (string) config('app.timezone', 'UTC');

        try {
            $tenantForOffset = $tenantId ?: (Auth::user()?->business_id ? (string) Auth::user()?->business_id : null);
            $window = self::gateway()->businessDates()->localDayWindow(
                LocalDate::parse($dateString),
                $iana,
                $tenantForOffset ? self::gateway()->businessDates()->rolloverOffsetMinutes($tenantForOffset) : null,
            );

            return [
                'start' => $window['start']->toCarbon()->setTimezone($storageTz)->format('Y-m-d H:i:s'),
                'end' => $window['end']->toCarbon()->setTimezone($storageTz)->format('Y-m-d H:i:s'),
                'localDate' => $window['localDate'],
                'ianaId' => $iana,
            ];
        } catch (\Throwable) {
            $start = CarbonImmutable::parse($dateString.' 00:00:00', $iana)->setTimezone($storageTz);

            return [
                'start' => $start->format('Y-m-d H:i:s'),
                'end' => $start->addDay()->format('Y-m-d H:i:s'),
                'localDate' => $dateString,
                'ianaId' => $iana,
            ];
        }
    }

    public static function constrainToOperationalDay(mixed $query, string $column, ?string $localDate = null, ?string $tenantId = null, ?string $branchId = null): mixed
    {
        $window = self::dayWindow($localDate, $tenantId, $branchId);

        return $query->where($column, '>=', $window['start'])->where($column, '<', $window['end']);
    }

    public static function constrainToOperationalPeriod(mixed $query, string $column, string $period = 'today'): mixed
    {
        $now = self::nowLocal();
        $storageTz = (string) config('app.timezone', 'UTC');

        [$start, $end] = match ($period) {
            'yesterday' => [
                self::dayWindow($now->subDay()->toDateString())['start'],
                self::dayWindow($now->subDay()->toDateString())['end'],
            ],
            'this_week', 'week' => [
                $now->startOfWeek()->setTimezone($storageTz)->format('Y-m-d H:i:s'),
                $now->startOfWeek()->addWeek()->setTimezone($storageTz)->format('Y-m-d H:i:s'),
            ],
            'this_month', 'month' => [
                $now->startOfMonth()->setTimezone($storageTz)->format('Y-m-d H:i:s'),
                $now->startOfMonth()->addMonth()->setTimezone($storageTz)->format('Y-m-d H:i:s'),
            ],
            'last_month' => [
                $now->copy()->startOfMonth()->subMonth()->setTimezone($storageTz)->format('Y-m-d H:i:s'),
                $now->startOfMonth()->setTimezone($storageTz)->format('Y-m-d H:i:s'),
            ],
            default => [
                self::dayWindow()['start'],
                self::dayWindow()['end'],
            ],
        };

        return $query->where($column, '>=', $start)->where($column, '<', $end);
    }

    public static function formatLocal(
        CarbonInterface|string|null $instant,
        ?string $tenantId = null,
        ?string $branchId = null,
        string $format = 'M j, Y H:i:s',
    ): string {
        if ($instant === null || $instant === '') {
            return '—';
        }

        $context = $tenantId
            ? self::describe($tenantId, $branchId)
            : self::currentUserContext();
        $carbon = $instant instanceof CarbonInterface
            ? CarbonImmutable::parse($instant->toDateTimeString(), $instant->getTimezone())
            : CarbonImmutable::parse((string) $instant);

        return $carbon->setTimezone($context['ianaId'])->format($format).' '.$context['abbreviation'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function currentUserContext(): array
    {
        $user = Auth::user();
        if (! $user?->business_id) {
            return self::describe(null);
        }

        $branchId = $user->current_branch?->id ?? $user->branch_id;

        return self::describe((string) $user->business_id, $branchId ? (string) $branchId : null);
    }

    public static function defaultTimezoneId(): string
    {
        $options = self::timezoneSelectOptions();
        $preferred = 'Africa/Kampala';
        if (isset($options[$preferred])) {
            return $preferred;
        }

        $appTz = (string) config('app.timezone', 'UTC');
        if (isset($options[$appTz])) {
            return $appTz;
        }

        return (string) array_key_first($options) ?: $appTz;
    }
}
