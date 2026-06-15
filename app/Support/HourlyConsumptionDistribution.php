<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Split a daily consumption total into hourly buckets (6:00–21:59) for display when
 * only a daily aggregate exists. Uses a deterministic seed so the same day always
 * gets the same shape.
 */
class HourlyConsumptionDistribution
{
    /**
     * @return array<int, int> hour (0–23) => quantity
     */
    public function distribute(int $totalQuantity, int $itemId, string $date): array
    {
        if ($totalQuantity <= 0) {
            return [];
        }

        $activeHours = range(6, 21);
        $weights = [];

        foreach ($activeHours as $hour) {
            $peak = 1 + sin(($hour - 6) / 15 * M_PI);
            $jitter = 0.85 + (($this->seededUnit($itemId, $date, $hour) % 30) / 100);
            $weights[$hour] = max(1, (int) round($peak * 100 * $jitter));
        }

        $weightSum = array_sum($weights);
        $allocated = [];
        $remainder = $totalQuantity;

        foreach ($activeHours as $hour) {
            $share = (int) floor($totalQuantity * $weights[$hour] / $weightSum);
            $allocated[$hour] = $share;
            $remainder -= $share;
        }

        $hour = 12;

        while ($remainder > 0) {
            $allocated[$hour] = ($allocated[$hour] ?? 0) + 1;
            $remainder--;
            $hour = $hour >= 21 ? 6 : $hour + 1;
        }

        return array_filter($allocated, fn (int $qty): bool => $qty > 0);
    }

    /**
     * @return list<object{hour: int, label: string, quantity_suom: int}>
     */
    public function hourlyRows(int $totalQuantity, int $itemId, string $date): array
    {
        $buckets = $this->distribute($totalQuantity, $itemId, $date);
        ksort($buckets);
        $rows = [];

        foreach ($buckets as $hour => $quantity) {
            $start = Carbon::parse($date)->setTime($hour, 0);
            $rows[] = (object) [
                'hour' => $hour,
                'label' => $start->format('g:i A').' – '.$start->copy()->addHour()->subMinute()->format('g:i A'),
                'quantity_suom' => $quantity,
            ];
        }

        return $rows;
    }

    private function seededUnit(int $itemId, string $date, int $hour): int
    {
        return abs(crc32($itemId.'|'.$date.'|'.$hour));
    }
}
