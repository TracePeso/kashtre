<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\ThirdPartyPayer;
use App\Models\ThirdPartyPayerBalanceHistory;
use App\Models\BalanceHistory;
use App\Models\AccountsReceivable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProcessInsuranceInvoiceEntries extends Command
{
    protected $signature = 'invoices:process-insurance-entries 
                            {--invoice-number= : Process a specific invoice by number}
                            {--all : Process all insurance invoices missing entries}';
    
    protected $description = 'Retroactively create balance history entries for insurance invoices that are missing them';

    public function handle()
    {
        $invoiceNumber = $this->option('invoice-number');
        $processAll = $this->option('all');

        if (!$invoiceNumber && !$processAll) {
            $this->error('Please specify either --invoice-number or --all option');
            return 1;
        }

        if ($invoiceNumber) {
            $invoices = Invoice::where('invoice_number', $invoiceNumber)->get();
        } else {
            // Find all invoices with insurance payment method that don't have third-party payer balance history entries
            $invoices = Invoice::whereJsonContains('payment_methods', 'insurance')
                ->whereNull('parent_invoice_id')
                ->whereDoesntHave('thirdPartyPayerBalanceHistories')
                ->get();
        }

        if ($invoices->isEmpty()) {
            $this->info('No invoices found to process.');
            return 0;
        }

        $this->info("Found {$invoices->count()} invoice(s) to process.");

        $processed = 0;
        $errors = 0;

        foreach ($invoices as $invoice) {
            try {
                $this->info("Processing invoice: {$invoice->invoice_number}");
                
                $client = $invoice->client;
                if (!$client) {
                    $this->warn("  ⚠ Client not found for invoice {$invoice->invoice_number}");
                    $errors++;
                    continue;
                }

                // Check if insurance is in payment methods
                $paymentMethods = $invoice->payment_methods ?? [];
                if (!in_array('insurance', $paymentMethods)) {
                    $this->warn("  ⚠ Invoice {$invoice->invoice_number} does not have insurance in payment methods");
                    continue;
                }

                // Check if client has insurance_company_id
                if (!$client->insurance_company_id) {
                    $this->warn("  ⚠ Client {$client->name} does not have insurance_company_id set");
                    $errors++;
                    continue;
                }

                // Find or create third-party payer
                $thirdPartyPayer = ThirdPartyPayer::where('insurance_company_id', $client->insurance_company_id)
                    ->where('business_id', $invoice->business_id)
                    ->where('type', 'insurance_company')
                    ->whereNull('client_id') // Business-level
                    ->where('status', 'active')
                    ->first();

                if (!$thirdPartyPayer) {
                    // Try to find insurance company in Kashtre database
                    $insuranceCompany = \App\Models\InsuranceCompany::find($client->insurance_company_id);
                    
                    // If not found, try to fetch from third-party API or create a placeholder
                    if (!$insuranceCompany) {
                        $this->warn("  ⚠ Insurance company with ID {$client->insurance_company_id} not found in Kashtre database");
                        
                        // Try to fetch from third-party API
                        try {
                            $apiService = new \App\Services\ThirdPartyApiService();
                            // Note: We'd need an API method to get insurance company by ID
                            // For now, create a third-party payer with the ID and a placeholder name
                            $insuranceCompanyName = "Insurance Company #{$client->insurance_company_id}";
                            
                            $this->warn("  ⚠ Creating third-party payer with placeholder name: {$insuranceCompanyName}");
                        } catch (\Exception $e) {
                            $insuranceCompanyName = "Insurance Company #{$client->insurance_company_id}";
                        }
                        
                        // Create third-party payer without insurance_company_id (since it doesn't exist in Kashtre)
                        $thirdPartyPayer = ThirdPartyPayer::create([
                            'business_id' => $invoice->business_id,
                            'type' => 'insurance_company',
                            'insurance_company_id' => $client->insurance_company_id, // Store the ID even if company doesn't exist
                            'client_id' => null, // Business-level account
                            'name' => $insuranceCompanyName ?? "Insurance Company #{$client->insurance_company_id}",
                            'status' => 'active',
                            'credit_limit' => 0,
                        ]);

                        $this->info("  ✓ Created third-party payer: {$thirdPartyPayer->name}");
                    } else {
                        $thirdPartyPayer = ThirdPartyPayer::create([
                            'business_id' => $invoice->business_id,
                            'type' => 'insurance_company',
                            'insurance_company_id' => $insuranceCompany->id,
                            'client_id' => null, // Business-level account
                            'name' => $insuranceCompany->name,
                            'status' => 'active',
                            'credit_limit' => 0,
                        ]);

                        $this->info("  ✓ Created third-party payer: {$thirdPartyPayer->name}");
                    }
                }

                // Check if entries already exist
                $existingEntries = ThirdPartyPayerBalanceHistory::where('invoice_id', $invoice->id)->count();
                if ($existingEntries > 0) {
                    $this->warn("  ⚠ Invoice {$invoice->invoice_number} already has {$existingEntries} balance history entries. Skipping.");
                    continue;
                }

                DB::beginTransaction();

                try {
                    // Create accounts receivable if it doesn't exist
                    $existingAR = AccountsReceivable::where('invoice_id', $invoice->id)
                        ->where('third_party_payer_id', $thirdPartyPayer->id)
                        ->first();

                    if (!$existingAR) {
                        $business = $invoice->business;
                        $dueDate = now()->addDays($business->default_payment_terms_days ?? 30)->toDateString();
                        
                        $accountsReceivable = AccountsReceivable::create([
                            'client_id' => $client->id,
                            'third_party_payer_id' => $thirdPartyPayer->id,
                            'business_id' => $invoice->business_id,
                            'branch_id' => $invoice->branch_id,
                            'invoice_id' => $invoice->id,
                            'created_by' => $invoice->created_by,
                            'amount_due' => $invoice->total_amount,
                            'amount_paid' => 0,
                            'balance' => $invoice->total_amount,
                            'invoice_date' => $invoice->created_at->toDateString(),
                            'due_date' => $dueDate,
                            'status' => 'current',
                            'payer_type' => 'third_party',
                            'notes' => "Insurance transaction - Invoice #{$invoice->invoice_number}",
                        ]);

                        $this->info("  ✓ Created accounts receivable entry");
                    }

                    // Update third-party payer balance
                    $previousPayerBalance = $thirdPartyPayer->current_balance ?? 0;
                    $amountToDebit = $invoice->balance_due > 0 ? $invoice->balance_due : $invoice->total_amount;
                    $newPayerBalance = $previousPayerBalance - $amountToDebit;
                    
                    $thirdPartyPayer->update(['current_balance' => $newPayerBalance]);

                    // Create balance history entries for each item
                    $itemsCollection = collect($invoice->items ?? []);
                    $debitCount = 0;
                    $primaryMethod = 'insurance';
                    $invoiceNumber = $invoice->invoice_number;

                    $thirdPartyPayer->loadMissing('insuranceCompany');
                    $insuranceTrackingPayerBracketName = $thirdPartyPayer->insuranceCompany->name ?? $thirdPartyPayer->name;

                    foreach ($itemsCollection as $itemData) {
                        $itemId = $itemData['id'] ?? $itemData['item_id'] ?? null;
                        if (!$itemId) {
                            continue;
                        }

                        $item = \App\Models\Item::find($itemId);
                        if (!$item) {
                            continue;
                        }

                        $quantity = $itemData['quantity'] ?? 1;
                        $itemTotalAmount = $itemData['total_amount'] ?? ($itemData['price'] ?? $item->default_price ?? 0) * $quantity;

                        if ($itemTotalAmount <= 0) {
                            continue;
                        }

                        // Skip package adjustment items
                        $isPackageAdjustmentItem = false;
                        if ($invoice->package_adjustment > 0) {
                            $validPackages = \App\Models\PackageTracking::where('client_id', $client->id)
                                ->where('business_id', $invoice->business_id)
                                ->where('status', 'active')
                                ->where('remaining_quantity', '>', 0)
                                ->get();

                            foreach ($validPackages as $packageTracking) {
                                $packageItems = $packageTracking->packageItem->packageItems ?? collect();
                                foreach ($packageItems as $packageItem) {
                                    if ($packageItem->included_item_id == $itemId) {
                                        $isPackageAdjustmentItem = true;
                                        break 2;
                                    }
                                }
                            }
                        }

                        if ($isPackageAdjustmentItem) {
                            continue;
                        }

                        // Create debit entry for third-party payer
                        $itemDisplayName = $item->name;
                        $debitDescription = "{$itemDisplayName} (x{$quantity})";
                        $debitNotes = "Insurance purchase - {$itemDisplayName} (x{$quantity}) - Invoice #{$invoiceNumber}";

                        $payerBalanceHistory = ThirdPartyPayerBalanceHistory::recordDebit(
                            $thirdPartyPayer,
                            $itemTotalAmount,
                            $debitDescription,
                            $invoiceNumber,
                            $debitNotes,
                            $primaryMethod,
                            $invoice->id,
                            $client->id
                        );

                        $debitCount++;
                    }

                    // Create debit entry for service charge if applicable
                    if ($invoice->service_charge > 0) {
                        $serviceChargeBalanceHistory = ThirdPartyPayerBalanceHistory::recordDebit(
                            $thirdPartyPayer,
                            $invoice->service_charge,
                            "Service Fee",
                            $invoiceNumber,
                            "Insurance purchase - Service Fee - Invoice #{$invoiceNumber}",
                            $primaryMethod,
                            $invoice->id,
                            $client->id
                        );

                        $debitCount++;
                    }

                    // Create tracking entries in client's BalanceHistory (for display purposes only)
                    $itemsCollectionForTracking = collect($invoice->items ?? []);
                    $trackingCount = 0;

                    foreach ($itemsCollectionForTracking as $itemData) {
                        $itemId = $itemData['id'] ?? $itemData['item_id'] ?? null;
                        if (!$itemId) {
                            continue;
                        }

                        $item = \App\Models\Item::find($itemId);
                        if (!$item) {
                            continue;
                        }

                        $quantity = $itemData['quantity'] ?? 1;
                        $itemTotalAmount = $itemData['total_amount'] ?? ($itemData['price'] ?? $item->default_price ?? 0) * $quantity;

                        if ($itemTotalAmount <= 0) {
                            continue;
                        }

                        // Skip package adjustment items
                        $isPackageAdjustmentItem = false;
                        if ($invoice->package_adjustment > 0) {
                            $validPackages = \App\Models\PackageTracking::where('client_id', $client->id)
                                ->where('business_id', $invoice->business_id)
                                ->where('status', 'active')
                                ->where('remaining_quantity', '>', 0)
                                ->get();

                            foreach ($validPackages as $packageTracking) {
                                $packageItems = $packageTracking->packageItem->packageItems ?? collect();
                                foreach ($packageItems as $packageItem) {
                                    if ($packageItem->included_item_id == $itemId) {
                                        $isPackageAdjustmentItem = true;
                                        break 2;
                                    }
                                }
                            }
                        }

                        if ($isPackageAdjustmentItem) {
                            continue;
                        }

                        // Check if tracking entry already exists
                        $existingTracking = BalanceHistory::where('invoice_id', $invoice->id)
                            ->where('client_id', $client->id)
                            ->where('description', 'like', "%{$item->name}%")
                            ->where('payment_method', 'insurance')
                            ->where('change_amount', 0)
                            ->first();

                        if ($existingTracking) {
                            continue;
                        }

                        // Create tracking entry (no balance change - just for display)
                        $itemDisplayName = $item->name;
                        $trackingDescription = "{$itemDisplayName} (x{$quantity}) [Insurance]";
                        $trackingNotes = "Insurance payment - {$itemDisplayName} (x{$quantity}) - Invoice #{$invoiceNumber} - Paid by {$insuranceTrackingPayerBracketName}";

                        $currentBalance = BalanceHistory::where('client_id', $client->id)
                            ->orderBy('created_at', 'desc')
                            ->value('new_balance') ?? ($client->balance ?? 0);

                        $trackingEntry = BalanceHistory::create([
                            'client_id' => $client->id,
                            'business_id' => $client->business_id,
                            'branch_id' => $client->branch_id,
                            'invoice_id' => $invoice->id,
                            'user_id' => $invoice->created_by ?? 1,
                            'previous_balance' => $currentBalance,
                            'change_amount' => 0, // No balance change - just tracking
                            'new_balance' => $currentBalance, // Balance stays the same
                            'transaction_type' => 'debit', // Use debit type but with 0 amount
                            'description' => $trackingDescription,
                            'reference_number' => $invoiceNumber,
                            'notes' => $trackingNotes,
                            'payment_method' => 'insurance',
                            'payment_status' => 'paid', // Insurance payments are considered paid
                        ]);

                        $trackingCount++;
                    }

                    // Create tracking entry for service charge if applicable
                    if ($invoice->service_charge > 0) {
                        $existingServiceTracking = BalanceHistory::where('invoice_id', $invoice->id)
                            ->where('client_id', $client->id)
                            ->where('description', 'like', '%Service Fee%')
                            ->where('payment_method', 'insurance')
                            ->where('change_amount', 0)
                            ->first();

                        if (!$existingServiceTracking) {
                            $currentBalance = BalanceHistory::where('client_id', $client->id)
                                ->orderBy('created_at', 'desc')
                                ->value('new_balance') ?? ($client->balance ?? 0);

                            $trackingEntry = BalanceHistory::create([
                                'client_id' => $client->id,
                                'business_id' => $client->business_id,
                                'branch_id' => $client->branch_id,
                                'invoice_id' => $invoice->id,
                                'user_id' => $invoice->created_by ?? 1,
                                'previous_balance' => $currentBalance,
                                'change_amount' => 0, // No balance change
                                'new_balance' => $currentBalance,
                                'transaction_type' => 'debit',
                                'description' => "Service Fee [Insurance]",
                                'reference_number' => $invoiceNumber,
                                'notes' => "Insurance payment - Service Fee - Invoice #{$invoiceNumber} - Paid by {$insuranceTrackingPayerBracketName}",
                                'payment_method' => 'insurance',
                                'payment_status' => 'paid',
                            ]);

                            $trackingCount++;
                        }
                    }

                    DB::commit();

                    $this->info("  ✓ Created {$debitCount} third-party payer debit entries");
                    $this->info("  ✓ Created {$trackingCount} client tracking entries");
                    $this->info("  ✓ Updated third-party payer balance: UGX " . number_format($newPayerBalance, 2));
                    
                    $processed++;

                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("  ✗ Error processing invoice {$invoice->invoice_number}: {$e->getMessage()}");
                    Log::error("Error processing insurance invoice entries", [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $errors++;
                }

            } catch (\Exception $e) {
                $this->error("  ✗ Error processing invoice {$invoice->invoice_number}: {$e->getMessage()}");
                Log::error("Error processing insurance invoice", [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'error' => $e->getMessage(),
                ]);
                $errors++;
            }
        }

        $this->info("\n=== Summary ===");
        $this->info("Processed: {$processed}");
        $this->info("Errors: {$errors}");

        return 0;
    }
}
