<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * PHP port of the hospital daily-consumption matrix generator (seed 42, 180 days from 2025-06-01).
 */
class HospitalConsumptionMatrix
{
    public const RANDOM_SEED = 42;

    public const DAY_COUNT = 180;

    /**
     * @param  array<int, array{name: string, unit: string, base_avg: float|int}>  $items
     * @return array<int, array{item_name: string, unit: string, date: string, quantity: int}>
     */
    public function generate(array $items, ?Carbon $endDate = null): array
    {
        mt_srand(self::RANDOM_SEED);

        $end = ($endDate ?? Carbon::yesterday())->copy()->startOfDay();
        $start = $end->copy()->subDays(self::DAY_COUNT - 1);
        $rows = [];

        foreach ($items as $item) {
            $baseAvg = (float) $item['base_avg'];

            for ($offset = 0; $offset < self::DAY_COUNT; $offset++) {
                $date = $start->copy()->addDays($offset);
                $weekendFactor = $date->dayOfWeek >= Carbon::SATURDAY ? 0.8 : 1.0;
                $adjustedAvg = $baseAvg * $weekendFactor;
                $quantity = $this->sampleDailyQuantity($adjustedAvg);

                if ($quantity <= 0) {
                    continue;
                }

                $rows[] = [
                    'item_name' => $item['name'],
                    'unit' => $item['unit'],
                    'date' => $date->toDateString(),
                    'quantity' => $quantity,
                ];
            }
        }

        return $rows;
    }

    public static function dateRangeLabel(?Carbon $endDate = null): string
    {
        $end = ($endDate ?? Carbon::yesterday())->copy()->startOfDay();
        $start = $end->copy()->subDays(self::DAY_COUNT - 1);

        return $start->toDateString().' → '.$end->toDateString();
    }

    private function sampleDailyQuantity(float $adjustedAvg): int
    {
        if ($adjustedAvg <= 2) {
            return $this->poisson($adjustedAvg);
        }

        $stdDeviation = max(1, $adjustedAvg * 0.15);

        return max(0, (int) round($this->normal($adjustedAvg, $stdDeviation)));
    }

    private function poisson(float $lambda): int
    {
        if ($lambda <= 0) {
            return 0;
        }

        $limit = exp(-$lambda);
        $product = 1.0;
        $count = 0;

        while ($product > $limit) {
            $count++;
            $product *= mt_rand() / mt_getrandmax();
        }

        return $count - 1;
    }

    private function normal(float $mean, float $std): float
    {
        $u1 = max(1e-10, mt_rand() / mt_getrandmax());
        $u2 = mt_rand() / mt_getrandmax();
        $z = sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);

        return $mean + ($std * $z);
    }
}
