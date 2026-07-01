<?php

namespace App\Services;

use App\Models\Business;
use Carbon\Carbon;

class FinancialYearService
{
    public function periodStart(Business|int $business, ?Carbon $asOf = null): Carbon
    {
        $business = $this->resolveBusiness($business);
        $asOf = ($asOf ?? Carbon::today())->copy()->startOfDay();

        $month = max(1, min(12, (int) ($business->financial_year_start_month ?? 1)));
        $day = max(1, min(31, (int) ($business->financial_year_start_day ?? 1)));

        $daysInMonth = Carbon::create($asOf->year, $month, 1)->daysInMonth;
        $day = min($day, $daysInMonth);

        $start = Carbon::create($asOf->year, $month, $day)->startOfDay();

        if ($asOf->lt($start)) {
            $previousYearDays = Carbon::create($asOf->year - 1, $month, 1)->daysInMonth;
            $start = Carbon::create($asOf->year - 1, $month, min($day, $previousYearDays))->startOfDay();
        }

        return $start;
    }

    public function periodEnd(Business|int $business, ?Carbon $asOf = null): Carbon
    {
        $start = $this->periodStart($business, $asOf);
        $business = $this->resolveBusiness($business);

        $month = max(1, min(12, (int) ($business->financial_year_start_month ?? 1)));
        $day = max(1, min(31, (int) ($business->financial_year_start_day ?? 1)));

        $nextYear = $start->year + 1;
        $daysInMonth = Carbon::create($nextYear, $month, 1)->daysInMonth;
        $nextStartDay = min($day, $daysInMonth);

        return Carbon::create($nextYear, $month, $nextStartDay)
            ->startOfDay()
            ->subDay()
            ->endOfDay();
    }

    public function periodLabel(Business|int $business, ?Carbon $asOf = null): string
    {
        $start = $this->periodStart($business, $asOf);
        $end = $this->periodEnd($business, $asOf);

        return $start->format('M j, Y').' – '.$end->format('M j, Y');
    }

    private function resolveBusiness(Business|int $business): Business
    {
        if ($business instanceof Business) {
            return $business;
        }

        return Business::query()->findOrFail($business);
    }
}
