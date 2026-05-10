<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MaturationPeriod;
use App\Models\Business;
use App\Models\User;

class MaturationPeriodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all businesses except Kashtre (business_id = 1)
        $businesses = Business::where('id', '!=', 1)->get();
        
        // Get a Kashtre admin user for created_by/updated_by
        $kashtreAdmin = User::where('business_id', 1)->first();
        
        if ($businesses->isEmpty()) {
            $this->command->warn('No businesses found to create maturation periods for. Please run KashtreSeeder first.');
            return;
        }
        
        if (!$kashtreAdmin) {
            $this->command->warn('No Kashtre admin user found. Please ensure KashtreSeeder has been run.');
            return;
        }

        /** @var array<string, int> $paymentMethodDefaults */
        $paymentMethodDefaults = config('maturation_defaults.entity', []);

        if ($paymentMethodDefaults === []) {
            $this->command->warn('config/maturation_defaults.php entity map is empty; skipping MaturationPeriodSeeder.');

            return;
        }

        $this->command->info('Creating maturation periods for ' . $businesses->count() . ' businesses...');

        foreach ($businesses as $business) {
            $this->command->info("Creating maturation periods for: {$business->name}");
            
            foreach ($paymentMethodDefaults as $paymentMethod => $defaultDays) {
                // Create maturation period for this business and payment method
                MaturationPeriod::create([
                    'business_id' => $business->id,
                    'payment_method' => $paymentMethod,
                    'maturation_days' => $defaultDays,
                    'description' => $this->getDescriptionForPaymentMethod($paymentMethod, $defaultDays),
                    'is_active' => true,
                    'created_by' => $kashtreAdmin->id,
                    'updated_by' => $kashtreAdmin->id,
                ]);
                
                $this->command->line("  ✓ Created {$paymentMethod} maturation period: {$defaultDays} days");
            }
        }

        $this->command->info('Maturation periods created successfully!');
        $this->command->info('Total maturation periods created: ' . ($businesses->count() * count($paymentMethodDefaults)));
        $this->command->info('All businesses now have configured maturation periods for payment processing.');
    }

    /**
     * Get a descriptive text for the payment method and maturation period
     */
    private function getDescriptionForPaymentMethod(string $paymentMethod, int $days): string
    {
        $descriptions = [
            'insurance' => "Insurance payments require {$days} days for claim processing and verification before funds become available.",
            'credit_arrangement' => "Credit arrangement payments have a {$days}-day maturation period for credit assessment and approval.",
            'mobile_money' => "Mobile money payments typically mature within {$days} day" . ($days > 1 ? 's' : '') . " for transaction verification.",
            'v_card' => "Virtual card payments require {$days} days for card processing and settlement before funds are released.",
            'p_card' => "Physical card payments need {$days} days for card processing, settlement, and verification.",
            'bank_transfer' => "Bank transfer payments require {$days} days for inter-bank processing and settlement.",
            'cash' => "Cash payments are immediate with no maturation period required.",
        ];

        return $descriptions[$paymentMethod] ?? "Payment method requires {$days} days maturation period.";
    }
}