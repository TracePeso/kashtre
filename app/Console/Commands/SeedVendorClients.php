<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Support\SharedTime;
use App\Models\Branch;
use App\Models\Client;
use App\Models\InsuranceCompany;
use App\Services\ThirdPartyApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SeedVendorClients extends Command
{
    protected $signature = 'seed:vendor-clients {code : The insurance company code (same as used in seed:vendor-data)}
                            {--clients=5 : Number of clients to seed (must match or be <= what seed:vendor-data created)}
                            {--fresh : Delete previously seeded clients for this company first}';

    protected $description = 'Seed properly registered clients in Kashtre for a regular vendor (after running seed:vendor-data on the third-party)';

    public function handle(): int
    {
        $code        = $this->argument('code');
        $clientCount = (int) $this->option('clients');

        // ── 1. Find insurance company in Kashtre ───────────────────────────
        $company = InsuranceCompany::where('code', $code)->first();

        if (! $company) {
            $this->error("No insurance company found in Kashtre with code: {$code}");
            return 1;
        }

        if (! $company->third_party_business_id) {
            $this->error("Insurance company has no third_party_business_id. Was it registered via Kashtre?");
            return 1;
        }

        $this->info("Vendor: {$company->name} (Kashtre ID: {$company->id}, Third-party business_id: {$company->third_party_business_id})");

        // ── 2. Get business / branch ───────────────────────────────────────
        $business = Business::first();
        $branch   = Branch::where('business_id', $business->id)->first();

        if (! $business || ! $branch) {
            $this->error('No business or branch found in Kashtre.');
            return 1;
        }

        // ── 3. Clean existing seeded clients if --fresh ────────────────────
        if ($this->option('fresh')) {
            $prefix = $this->policyPrefix($code);
            $year   = now()->year;
            $deleted = Client::where('business_id', $business->id)
                ->where('policy_number', 'like', "{$prefix}-{$year}-%")
                ->delete();
            $this->warn("--fresh: removed {$deleted} existing seeded client(s).");
        }

        // ── 4. Build the policy numbers to look up ─────────────────────────
        $prefix  = $this->policyPrefix($code);
        $year    = now()->year;
        $apiService = new ThirdPartyApiService();

        $dummies = [
            ['surname' => 'Nakato',    'first_name' => 'Sarah',  'sex' => 'Female', 'dob' => '1990-05-14', 'phone' => '0701000001', 'category' => 'outpatient'],
            ['surname' => 'Okello',    'first_name' => 'James',  'sex' => 'Male',   'dob' => '1985-03-22', 'phone' => '0701000002', 'category' => 'outpatient'],
            ['surname' => 'Tumukunde', 'first_name' => 'Grace',  'sex' => 'Female', 'dob' => '1978-11-08', 'phone' => '0701000003', 'category' => 'outpatient'],
            ['surname' => 'Mugisha',   'first_name' => 'Brian',  'sex' => 'Male',   'dob' => '1995-07-30', 'phone' => '0701000004', 'category' => 'outpatient'],
            ['surname' => 'Apio',      'first_name' => 'Faith',  'sex' => 'Female', 'dob' => '1988-01-19', 'phone' => '0701000005', 'category' => 'outpatient'],
            ['surname' => 'Ssemakula', 'first_name' => 'Robert', 'sex' => 'Male',   'dob' => '1975-09-03', 'phone' => '0701000006', 'category' => 'outpatient'],
            ['surname' => 'Nabirye',   'first_name' => 'Lydia',  'sex' => 'Female', 'dob' => '1992-12-25', 'phone' => '0701000007', 'category' => 'outpatient'],
            ['surname' => 'Mwesigwa',  'first_name' => 'Peter',  'sex' => 'Male',   'dob' => '1983-06-17', 'phone' => '0701000008', 'category' => 'outpatient'],
        ];

        $dummies = array_slice($dummies, 0, $clientCount);
        $this->newLine();

        foreach ($dummies as $i => $d) {
            $seq       = str_pad($i + 1, 3, '0', STR_PAD_LEFT);
            $policyNum = "{$prefix}-{$year}-{$seq}";
            $fullName  = trim($d['surname'] . ' ' . $d['first_name']);

            // Skip if already exists
            if (Client::where('business_id', $business->id)->where('policy_number', $policyNum)->exists()) {
                $this->line("  skip {$policyNum} — already in Kashtre");
                continue;
            }

            // ── 5. Verify policy against third-party API ───────────────────
            $this->line("  Verifying {$policyNum} ({$fullName})...");

            $verification = $apiService->verifyPolicyNumber(
                $company->third_party_business_id,
                $policyNum,
                $fullName,
                $d['dob'],
                $d['category']
            );

            if (! $verification || ! ($verification['success'] ?? false) || ! ($verification['exists'] ?? false)) {
                $this->warn("    ! Verification failed for {$policyNum}: " . ($verification['message'] ?? 'unknown error'));
                $this->warn("    ! Skipping — make sure seed:vendor-data was run on the third-party first.");
                continue;
            }

            $pr = $verification['data']['payment_responsibility'] ?? [];

            // ── 6. Create the Kashtre client ───────────────────────────────
            $client = Client::create([
                'business_id'                        => $business->id,
                'branch_id'                          => $branch->id,
                'client_type'                        => 'individual',
                'client_id'                          => strtoupper(substr($d['surname'], 0, 3)) . '-' . rand(10000, 99999),
                'visit_id'                           => 'VIS-' . strtoupper(Str::random(8)),
                'visit_expires_at'                   => now()->addDays(7),
                'surname'                            => $d['surname'],
                'first_name'                         => $d['first_name'],
                'name'                               => $d['first_name'] . ' ' . $d['surname'],
                'email'                              => strtolower($d['first_name'] . '.' . $d['surname']) . '@seed.test',
                'sex'                                => $d['sex'],
                'date_of_birth'                      => $d['dob'],
                'phone_number'                       => $d['phone'],
                'payment_methods'                    => json_encode(['insurance']),
                'insurance_company_id'               => $company->id,
                'policy_number'                      => $policyNum,
                'services_category'                  => $d['category'],
                'status'                             => 'active',
                // Payment responsibility from verified policy
                'has_deductible'                     => $pr['has_deductible'] ?? false,
                'copay_amount'                       => $pr['copay_amount'] ?? null,
                'deductible_amount'                  => $pr['deductible_amount'] ?? null,
                'coinsurance_percentage'             => $pr['coinsurance_percentage'] ?? null,
                'copay_max_limit'                    => $pr['copay_max_limit'] ?? null,
                'copay_contributes_to_deductible'    => $pr['copay_contributes_to_deductible'] ?? false,
                'coinsurance_contributes_to_deductible' => $pr['coinsurance_contributes_to_deductible'] ?? false,
            ]);

            // Issue a proper visit ID via the model method
            $client->issueNewVisitId();

            // ── 7. Register the authorized visit with the third-party ──────
            $visitResult = $apiService->registerAuthorizedVisit(
                $client,
                $client->visit_id,
                SharedTime::businessToday(),
                $client->visit_expires_at?->toDateTimeString(),
                $d['category'],
                $company->third_party_business_id
            );

            $visitStatus = $visitResult ? 'visit registered' : 'visit registration failed (non-fatal)';

            $this->info("  + {$d['surname']} {$d['first_name']} → {$policyNum} | copay: " . number_format($pr['copay_amount'] ?? 0) . " | deductible: " . number_format($pr['deductible_amount'] ?? 0) . " | {$visitStatus}");
        }

        $this->newLine();
        $this->info('Kashtre client seeding complete.');
        return 0;
    }

    private function policyPrefix(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', substr($code, 0, 3)));
    }
}
