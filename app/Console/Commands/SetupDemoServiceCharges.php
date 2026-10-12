<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\ServiceCharge;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetupDemoServiceCharges extends Command
{
    protected $signature = 'kashtre:setup-demo-service-charges
        {--only= : Comma-separated entity codes (MCC,LSH,CCTH)}
        {--force : Replace business service charges that are already set}';

    protected $description = 'Set business service-charge tiers for Metropolitan Care Clinic, London Surgery Hospital, and Central City Teaching Hospital';

    /**
     * @var array<string, string>
     */
    private const FACILITIES = [
        'MCC' => 'Metropolitan Care Clinic',
        'LSH' => 'London Surgery Hospital',
        'CCTH' => 'Central City Teaching Hospital',
    ];

    public function handle(): int
    {
        $only = collect(explode(',', (string) $this->option('only')))
            ->map(fn (string $code) => strtoupper(trim($code)))
            ->filter()
            ->values()
            ->all();

        $unknown = array_diff($only, array_keys(self::FACILITIES));
        if ($unknown !== []) {
            $this->error('Unknown entity code: '.implode(', ', $unknown).'. Use MCC, LSH, or CCTH.');

            return self::FAILURE;
        }

        $codes = $only === [] ? array_keys(self::FACILITIES) : $only;
        $tiers = $this->tiers();
        $failed = false;

        foreach ($codes as $code) {
            $business = Business::query()->where('entity_code', $code)->orderBy('id')->first();
            if ($business === null) {
                $this->error($code.' ('.self::FACILITIES[$code].') was not found. Import the workbook first.');
                $failed = true;

                continue;
            }

            $createdBy = User::query()
                ->where('business_id', $business->id)
                ->orderBy('id')
                ->value('id')
                ?? User::query()->orderBy('id')->value('id');

            if ($createdBy === null) {
                $this->error($code.' has no user to record as the creator of the service charges.');
                $failed = true;

                continue;
            }

            $existing = ServiceCharge::query()
                ->where('business_id', $business->id)
                ->where('entity_type', 'business')
                ->where('entity_id', $business->id)
                ->count();

            if ($existing > 0 && ! $this->option('force')) {
                $this->line($code.' already has '.$existing.' business service charge '.($existing === 1 ? 'tier' : 'tiers').'. Left unchanged.');

                continue;
            }

            DB::transaction(function () use ($business, $tiers, $createdBy): void {
                if ($this->option('force')) {
                    ServiceCharge::query()
                        ->where('business_id', $business->id)
                        ->where('entity_type', 'business')
                        ->where('entity_id', $business->id)
                        ->delete();
                }

                foreach ($tiers as $tier) {
                    ServiceCharge::query()->create([
                        'entity_type' => 'business',
                        'entity_id' => $business->id,
                        'lower_bound' => $tier['lower_bound'],
                        'upper_bound' => $tier['upper_bound'],
                        'amount' => $tier['amount'],
                        'type' => $tier['type'],
                        'is_active' => true,
                        'business_id' => $business->id,
                        'created_by' => $createdBy,
                    ]);
                }
            });

            $this->info($code.' — '.self::FACILITIES[$code].': '.count($tiers).' service charge tiers saved.');
        }

        if (! $failed) {
            $this->newLine();
            $this->comment('Tiers are a percentage of the invoice amount: 2% up to 100,000, 1.5% from 100,000 to 500,000, and 1% above 500,000.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<array{lower_bound: float, upper_bound: float, amount: float, type: string}>
     */
    private function tiers(): array
    {
        $configured = config('third_party_vendor_service_charges.default_tiers', []);
        $tiers = [];

        foreach ($configured as $row) {
            if (! is_array($row)) {
                continue;
            }

            $upper = $row['upper_bound'] ?? null;
            $tiers[] = [
                'lower_bound' => (float) ($row['lower_bound'] ?? 0),
                'upper_bound' => $upper === null || $upper === '' ? 99999999.99 : (float) $upper,
                'amount' => (float) ($row['amount'] ?? 0),
                'type' => (string) ($row['type'] ?? 'percentage'),
            ];
        }

        if ($tiers === []) {
            $tiers = [
                ['lower_bound' => 0, 'upper_bound' => 100000, 'amount' => 2, 'type' => 'percentage'],
                ['lower_bound' => 100000, 'upper_bound' => 500000, 'amount' => 1.5, 'type' => 'percentage'],
                ['lower_bound' => 500000, 'upper_bound' => 99999999.99, 'amount' => 1, 'type' => 'percentage'],
            ];
        }

        return $tiers;
    }
}
