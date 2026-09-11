<?php

namespace App\Domain\Time\Services;

use App\Domain\Time\Enums\FinancialPeriodStatus;
use App\Domain\Time\Exceptions\TimeEngineException;
use App\Domain\Time\Models\FinancialPeriod;
use App\Domain\Time\Models\TimeAudit;
use App\Domain\Time\ValueObjects\LocalDate;
use App\Domain\Time\ValueObjects\UtcInstant;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 — financial / accounting periods with close & reopen.
 */
final class FinancialPeriodService
{
    public function __construct(
        private readonly \App\Domain\Time\Contracts\Clock $clock,
        private readonly TimeZoneCatalogueService $catalogue,
    ) {}

    public function open(
        string $tenantKey,
        string $code,
        string $name,
        LocalDate $start,
        LocalDate $end,
        string $periodType = 'MONTH',
        ?string $ianaId = null,
    ): FinancialPeriod {
        if ($ianaId) {
            $this->catalogue->ensureKnown($ianaId);
        }

        $period = FinancialPeriod::query()->create([
            'tenant_key' => $tenantKey,
            'code' => strtoupper($code),
            'name' => $name,
            'period_type' => strtoupper($periodType),
            'local_start_date' => $start->toString(),
            'local_end_date' => $end->toString(),
            'status' => FinancialPeriodStatus::OPEN->value,
            'iana_id' => $ianaId,
        ]);

        TimeAudit::query()->create([
            'tenant_key' => $tenantKey,
            'actor_user_id' => Auth::id(),
            'action' => 'PERIOD_OPEN',
            'object_type' => 'core_financial_periods',
            'object_public_id' => $period->public_id,
            'after' => $period->toArray(),
        ]);

        return $period;
    }

    public function close(FinancialPeriod $period, ?string $reason = null): FinancialPeriod
    {
        if ($period->status === FinancialPeriodStatus::CLOSED->value) {
            return $period;
        }

        $before = $period->toArray();
        $period->update([
            'status' => FinancialPeriodStatus::CLOSED->value,
            'closed_at_utc' => $this->clock->now()->toCarbon(),
            'closed_by' => Auth::id(),
        ]);
        $fresh = $period->fresh();

        TimeAudit::query()->create([
            'tenant_key' => $period->tenant_key,
            'actor_user_id' => Auth::id(),
            'action' => 'PERIOD_CLOSE',
            'object_type' => 'core_financial_periods',
            'object_public_id' => $fresh->public_id,
            'before' => $before,
            'after' => $fresh->toArray(),
            'reason' => $reason,
        ]);

        return $fresh;
    }

    public function reopen(FinancialPeriod $period, ?string $reason = null): FinancialPeriod
    {
        $before = $period->toArray();
        $period->update([
            'status' => FinancialPeriodStatus::REOPENED->value,
            'closed_at_utc' => null,
            'closed_by' => null,
        ]);
        $fresh = $period->fresh();

        TimeAudit::query()->create([
            'tenant_key' => $period->tenant_key,
            'actor_user_id' => Auth::id(),
            'action' => 'PERIOD_REOPEN',
            'object_type' => 'core_financial_periods',
            'object_public_id' => $fresh->public_id,
            'before' => $before,
            'after' => $fresh->toArray(),
            'reason' => $reason,
        ]);

        return $fresh;
    }

    public function forLocalDate(string $tenantKey, LocalDate $date): ?FinancialPeriod
    {
        return FinancialPeriod::query()
            ->where('tenant_key', $tenantKey)
            ->whereDate('local_start_date', '<=', $date->toString())
            ->whereDate('local_end_date', '>=', $date->toString())
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Assert posting is allowed; closed periods reject late events unless reopened.
     */
    public function assertOpenFor(string $tenantKey, LocalDate $date): FinancialPeriod
    {
        $period = $this->forLocalDate($tenantKey, $date);
        if (! $period) {
            throw new TimeEngineException('TIME_PERIOD_NOT_FOUND', 'No financial period covers this date.', [
                'date' => $date->toString(),
            ]);
        }
        if (! $period->isOpen()) {
            throw TimeEngineException::periodClosed($period->code);
        }

        return $period;
    }

    /**
     * Classify a late event relative to a closed period.
     */
    public function lateEventTreatment(FinancialPeriod $period, UtcInstant $eventAt): string
    {
        if ($period->isOpen()) {
            return 'IN_OPEN_PERIOD';
        }

        $closedAt = $period->closed_at_utc;
        if ($closedAt && $eventAt->toCarbon()->gt($closedAt)) {
            return 'POST_CLOSE_LATE';
        }

        return 'IN_CLOSED_PERIOD';
    }
}
