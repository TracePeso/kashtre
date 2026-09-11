<?php

namespace App\Livewire\Platform;

use App\Domain\Time\Enums\PolicyPurpose;
use App\Domain\Time\Enums\PolicyScopeType;
use App\Domain\Time\Exceptions\TimeEngineException;
use App\Domain\Time\Models\BusinessCalendar;
use App\Domain\Time\Models\DeviceTimeObservation;
use App\Domain\Time\Models\FinancialPeriod;
use App\Domain\Time\Models\ScheduleDefinition;
use App\Domain\Time\Models\TimeAudit;
use App\Domain\Time\Models\TimeZonePolicy;
use App\Domain\Time\Services\SharedTimeGateway;
use App\Domain\Time\ValueObjects\LocalDate;
use App\Domain\Time\ValueObjects\LocalDateTime;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TimeEngineConsole extends Component
{
    public string $activeTab = 'clock';

    public string $catalogueQuery = '';

    public string $policyIana = 'Africa/Kampala';

    public string $policyScope = 'TENANT';

    public string $policyPurpose = 'OPERATIONAL';

    public ?string $policyMessage = null;

    public ?string $policyError = null;

    public string $resolveAsOf = '';

    public ?array $resolveResult = null;

    public string $localInput = '';

    public string $localIana = 'Africa/Kampala';

    public ?array $convertResult = null;

    public ?string $convertError = null;

    public string $bizDateIana = 'Africa/Kampala';

    public ?string $bizDateResult = null;

    public string $scheduleCode = 'EOD_JOB';

    public string $scheduleName = 'End of day job';

    public string $scheduleTime = '18:00:00';

    public string $scheduleIana = 'Africa/Kampala';

    public string $scheduleRecurrence = 'DAILY';

    public ?string $scheduleMessage = null;

    public ?array $schedulePreview = null;

    public string $calendarCode = 'DEFAULT';

    public string $calendarName = 'Default business calendar';

    public ?string $calendarMessage = null;

    public string $periodCode = '';

    public string $periodName = '';

    public string $periodStart = '';

    public string $periodEnd = '';

    public ?string $periodMessage = null;

    public string $deviceId = 'DEVICE-1';

    public string $deviceRaw = '';

    public string $deviceTz = 'Africa/Kampala';

    public ?string $deviceMessage = null;

    public function setTab(string $tab): void
    {
        $allowed = ['clock', 'catalogue', 'policies', 'convert', 'business', 'schedules', 'calendars', 'periods', 'devices', 'audit'];
        if (! in_array($tab, $allowed, true)) {
            return;
        }
        $this->activeTab = $tab;
        $this->policyMessage = $this->policyError = null;
        $this->convertError = null;
        $this->scheduleMessage = null;
        $this->calendarMessage = null;
        $this->periodMessage = null;
        $this->deviceMessage = null;
    }

    public function activateTenantPolicy(SharedTimeGateway $time): void
    {
        $this->policyMessage = $this->policyError = null;
        try {
            $tenant = (string) (Auth::user()->business_id ?? 'SYSTEM');
            $draft = $time->policies()->draft(
                $tenant,
                PolicyScopeType::from($this->policyScope),
                $this->policyScope === 'PLATFORM' ? 'PLATFORM' : $tenant,
                $this->policyIana,
                PolicyPurpose::from($this->policyPurpose),
                reason: 'Console activation',
            );
            $time->policies()->activate($draft, 'Console activation');
            $this->policyMessage = "Activated {$this->policyScope} policy → {$this->policyIana}";
        } catch (TimeEngineException|\Throwable $e) {
            $this->policyError = $e->getMessage();
        }
    }

    public function runResolve(SharedTimeGateway $time): void
    {
        $tenant = (string) (Auth::user()->business_id ?? 'SYSTEM');
        $asOf = $this->resolveAsOf !== '' ? \App\Domain\Time\ValueObjects\UtcInstant::fromString($this->resolveAsOf) : null;
        $resolved = $time->resolution()->resolve(new \App\Domain\Time\ValueObjects\TimeContext(
            tenantId: $tenant,
            purpose: PolicyPurpose::from($this->policyPurpose),
            asOf: $asOf,
        ), $tenant);
        $this->resolveResult = $resolved->toArray();
    }

    public function convertLocal(SharedTimeGateway $time): void
    {
        $this->convertError = null;
        $this->convertResult = null;
        try {
            $local = LocalDateTime::parse($this->localInput !== '' ? $this->localInput : now()->format('Y-m-d H:i:s'));
            $result = $time->civil()->localToUtc($local, $this->localIana);
            $this->convertResult = [
                'utc' => $result['instant']->toIso8601(),
                'note' => $result['note'],
            ];
        } catch (\Throwable $e) {
            $this->convertError = $e->getMessage();
        }
    }

    public function showBusinessDate(SharedTimeGateway $time): void
    {
        $tenant = (string) (Auth::user()->business_id ?? 'SYSTEM');
        $date = $time->businessDate($tenant);
        $window = $time->businessDates()->localDayWindow($date, $this->bizDateIana);
        $this->bizDateResult = $date->toString().' | UTC ['.$window['start']->toIso8601().' , '.$window['end']->toIso8601().')';
    }

    public function createSchedule(SharedTimeGateway $time): void
    {
        $this->scheduleMessage = null;
        try {
            $tenant = (string) (Auth::user()->business_id ?? 'SYSTEM');
            $def = $time->schedules()->define(
                $tenant,
                $this->scheduleCode,
                $this->scheduleName,
                $this->scheduleIana,
                $this->scheduleTime,
                $this->scheduleRecurrence,
                LocalDate::parse(now($this->scheduleIana)->format('Y-m-d')),
            );
            $this->schedulePreview = $time->schedules()->preview($def, 5);
            $this->scheduleMessage = 'Schedule '.$def->code.' created.';
        } catch (\Throwable $e) {
            $this->scheduleMessage = $e->getMessage();
        }
    }

    public function createCalendar(SharedTimeGateway $time): void
    {
        $this->calendarMessage = null;
        try {
            $tenant = (string) (Auth::user()->business_id ?? 'SYSTEM');
            $cal = $time->calendars()->create($tenant, $this->calendarCode, $this->calendarName, 'Africa/Kampala');
            $from = LocalDate::parse(now()->startOfYear()->format('Y-m-d'));
            $to = LocalDate::parse(now()->endOfYear()->format('Y-m-d'));
            $n = $time->calendars()->seedWeekends($cal, $from, $to);
            $this->calendarMessage = "Calendar {$cal->code} created; seeded {$n} weekend days.";
        } catch (\Throwable $e) {
            $this->calendarMessage = $e->getMessage();
        }
    }

    public function openPeriod(SharedTimeGateway $time): void
    {
        $this->periodMessage = null;
        try {
            $tenant = (string) (Auth::user()->business_id ?? 'SYSTEM');
            $code = $this->periodCode !== '' ? $this->periodCode : now()->format('Y-m');
            $start = LocalDate::parse($this->periodStart !== '' ? $this->periodStart : now()->startOfMonth()->format('Y-m-d'));
            $end = LocalDate::parse($this->periodEnd !== '' ? $this->periodEnd : now()->endOfMonth()->format('Y-m-d'));
            $period = $time->periods()->open($tenant, $code, $this->periodName !== '' ? $this->periodName : $code, $start, $end);
            $this->periodMessage = "Opened period {$period->code}.";
        } catch (\Throwable $e) {
            $this->periodMessage = $e->getMessage();
        }
    }

    public function closePeriod(int $id, SharedTimeGateway $time): void
    {
        $period = FinancialPeriod::query()->findOrFail($id);
        $time->periods()->close($period, 'Console close');
        $this->periodMessage = "Closed {$period->code}.";
    }

    public function observeDevice(SharedTimeGateway $time): void
    {
        $this->deviceMessage = null;
        $tenant = (string) (Auth::user()->business_id ?? 'SYSTEM');
        $raw = $this->deviceRaw !== '' ? $this->deviceRaw : now()->toIso8601String();
        $row = $time->devices()->observe($tenant, $this->deviceId, $raw, $this->deviceTz ?: null);
        $this->deviceMessage = "{$row->status}: ".($row->quarantine_reason ?: $row->normalized_at_utc);
    }

    public function recordClockHealthy(SharedTimeGateway $time): void
    {
        $time->clockHealth()->recordCheck(gethostname() ?: 'app', 0, 'console');
    }

    public function render(SharedTimeGateway $time)
    {
        $tenant = (string) (Auth::user()->business_id ?? 'SYSTEM');

        return view('livewire.platform.time-engine-console', [
            'enabled' => $time->enabled(),
            'nowUtc' => $time->now()->toIso8601(),
            'clockHealth' => $time->clockHealth()->forNode(gethostname() ?: 'app'),
            'zones' => $time->catalogue()->search($this->catalogueQuery !== '' ? $this->catalogueQuery : null, 40),
            'policies' => TimeZonePolicy::query()->where('tenant_key', $tenant)->orWhere('tenant_key', 'SYSTEM')->latest()->limit(20)->get(),
            'schedules' => ScheduleDefinition::query()->where('tenant_key', $tenant)->latest()->limit(10)->get(),
            'calendars' => BusinessCalendar::query()->where('tenant_key', $tenant)->latest()->limit(10)->get(),
            'periods' => FinancialPeriod::query()->where('tenant_key', $tenant)->latest()->limit(10)->get(),
            'devices' => DeviceTimeObservation::query()->where('tenant_key', $tenant)->latest()->limit(15)->get(),
            'audits' => TimeAudit::query()->where('tenant_key', $tenant)->orWhere('tenant_key', 'SYSTEM')->latest()->limit(30)->get(),
            'tzdb' => $time->catalogue()->tzdbRelease(),
        ]);
    }
}
