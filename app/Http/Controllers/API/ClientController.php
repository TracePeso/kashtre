<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClientController extends Controller
{
    /**
     * Get the amount of deductible that has been used by a client.
     * This calculates from payment responsibility payments and invoices where the client paid their portion.
     */
    public function getDeductibleUsed(Client $client)
    {
        try {
            // If client doesn't have deductible, return 0
            if (!$client->has_deductible || !$client->deductible_amount) {
                return response()->json([
                    'success' => true,
                    'deductible_used' => 0,
                    'deductible_remaining' => 0,
                    'deductible_amount' => 0
                ]);
            }

            $deductibleUsed = 0;
            
            // 1. Get deductible payments from MoneyTransfer records (payment responsibility payments)
            $deductiblePayments = \App\Models\MoneyTransfer::where('client_id', $client->id)
                ->where('status', 'completed')
                ->where('transfer_type', 'payment_received')
                ->get()
                ->filter(function($transfer) {
                    $metadata = $transfer->metadata ?? [];
                    return isset($metadata['payment_responsibility_type']) && 
                           $metadata['payment_responsibility_type'] === 'deductible';
                });
            
            foreach ($deductiblePayments as $transfer) {
                $deductibleUsed += $transfer->amount ?? 0;
            }

            // When co-pay counts toward the annual deductible, include completed co-pay payments.
            if ($client->copay_contributes_to_deductible) {
                $copayTowardDeductible = \App\Models\MoneyTransfer::where('client_id', $client->id)
                    ->where('status', 'completed')
                    ->where('transfer_type', 'payment_received')
                    ->get()
                    ->filter(function ($transfer) {
                        $metadata = $transfer->metadata ?? [];

                        return isset($metadata['payment_responsibility_type'])
                            && $metadata['payment_responsibility_type'] === 'copay';
                    });
                foreach ($copayTowardDeductible as $transfer) {
                    $deductibleUsed += (float) ($transfer->amount ?? 0);
                }
            }

            // When co-insurance counts toward deductible, include those payments if tagged in metadata.
            if ($client->coinsurance_contributes_to_deductible) {
                $coinsuranceTowardDeductible = \App\Models\MoneyTransfer::where('client_id', $client->id)
                    ->where('status', 'completed')
                    ->where('transfer_type', 'payment_received')
                    ->get()
                    ->filter(function ($transfer) {
                        $metadata = $transfer->metadata ?? [];

                        return isset($metadata['payment_responsibility_type'])
                            && $metadata['payment_responsibility_type'] === 'coinsurance';
                    });
                foreach ($coinsuranceTowardDeductible as $transfer) {
                    $deductibleUsed += (float) ($transfer->amount ?? 0);
                }
            }
            
            Log::info('Deductible payments found', [
                'client_id' => $client->id,
                'count' => $deductiblePayments->count(),
                'total_amount' => $deductiblePayments->sum('amount'),
                'copay_contributes_to_deductible' => (bool) $client->copay_contributes_to_deductible,
                'coinsurance_contributes_to_deductible' => (bool) $client->coinsurance_contributes_to_deductible,
            ]);
            
            // 2. Heuristic from invoices (legacy). When co-pay contributes to deductible, co-pay amounts are
            // already counted via MoneyTransfer above — do not also attribute invoice client payments or we double-count.
            if (! $client->copay_contributes_to_deductible) {
                $invoices = Invoice::where('client_id', $client->id)
                    ->whereNull('parent_invoice_id')
                    ->whereIn('payment_status', ['paid', 'partial'])
                    ->get();

                foreach ($invoices as $invoice) {
                    if ($deductibleUsed < $client->deductible_amount) {
                        $clientPaidAmount = $invoice->amount_paid ?? 0;

                        if ($clientPaidAmount > 0) {
                            $remainingDeductible = $client->deductible_amount - $deductibleUsed;
                            $deductibleUsed += min($clientPaidAmount, $remainingDeductible);
                        }
                    }
                }
            }

            // Ensure we don't exceed the total deductible amount
            $deductibleUsed = min($deductibleUsed, $client->deductible_amount);
            $deductibleRemaining = max(0, $client->deductible_amount - $deductibleUsed);

            Log::info('Deductible calculation', [
                'client_id' => $client->id,
                'deductible_amount' => $client->deductible_amount,
                'deductible_used' => $deductibleUsed,
                'deductible_remaining' => $deductibleRemaining,
                'direct_payments_count' => $deductiblePayments->count(),
            ]);

            return response()->json([
                'success' => true,
                'deductible_used' => round($deductibleUsed, 2),
                'deductible_remaining' => round($deductibleRemaining, 2),
                'deductible_amount' => round($client->deductible_amount, 2)
            ]);

        } catch (\Exception $e) {
            Log::error('Error calculating deductible used', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error calculating deductible used',
                'deductible_used' => 0,
                'deductible_remaining' => $client->deductible_amount ?? 0,
                'deductible_amount' => $client->deductible_amount ?? 0
            ], 500);
        }
    }

    /**
     * Check if co-pay has been paid for the current visit.
     * Co-pay is typically paid per visit, so we check if there's a payment for this visit.
     */
    public function getCopayPaidStatus(Client $client)
    {
        try {
            // If client doesn't have co-pay, return not required
            if (!$client->copay_amount) {
                return response()->json([
                    'success' => true,
                    'copay_required' => false,
                    'copay_paid' => false,
                    'copay_amount' => 0,
                    'message' => 'Client does not have a co-pay requirement'
                ]);
            }

            $copayPaid = false;
            $copayPaidAmount = 0;
            $visitId = $client->visit_id;

            // Check for co-pay payments from MoneyTransfer records for this visit
            if ($visitId) {
                $copayPayments = \App\Models\MoneyTransfer::where('client_id', $client->id)
                    ->where('status', 'completed')
                    ->where('transfer_type', 'payment_received')
                    ->get()
                    ->filter(function($transfer) use ($visitId) {
                        $metadata = $transfer->metadata ?? [];
                        return isset($metadata['payment_responsibility_type']) && 
                               $metadata['payment_responsibility_type'] === 'copay' &&
                               ($metadata['visit_id'] ?? null) === $visitId;
                    });
                
                $copayPaidAmount = $copayPayments->sum('amount');
                $copayPaid = $copayPaidAmount >= $client->copay_amount;
            }

            // Also check recent payments (within last 24 hours) if no visit_id match
            if (!$copayPaid) {
                $recentCopayPayments = \App\Models\MoneyTransfer::where('client_id', $client->id)
                    ->where('status', 'completed')
                    ->where('transfer_type', 'payment_received')
                    ->where('created_at', '>=', now()->subDay())
                    ->get()
                    ->filter(function($transfer) {
                        $metadata = $transfer->metadata ?? [];
                        return isset($metadata['payment_responsibility_type']) && 
                               $metadata['payment_responsibility_type'] === 'copay';
                    });
                
                if ($recentCopayPayments->sum('amount') >= $client->copay_amount) {
                    $copayPaid = true;
                    $copayPaidAmount = $recentCopayPayments->sum('amount');
                }
            }

            Log::info('Co-pay status check', [
                'client_id' => $client->id,
                'visit_id' => $visitId,
                'copay_amount' => $client->copay_amount,
                'copay_paid' => $copayPaid,
                'copay_paid_amount' => $copayPaidAmount,
            ]);

            return response()->json([
                'success' => true,
                'copay_required' => true,
                'copay_paid' => $copayPaid,
                'copay_amount' => round($client->copay_amount, 2),
                'copay_paid_amount' => round($copayPaidAmount, 2),
                'visit_id' => $visitId,
            ]);

        } catch (\Exception $e) {
            Log::error('Error checking co-pay paid status', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error checking co-pay paid status',
                'copay_required' => $client->copay_amount > 0,
                'copay_paid' => false,
                'copay_amount' => $client->copay_amount ?? 0
            ], 500);
        }
    }

    /**
     * Get insurance company registration settings (e.g., show_policy_details_at_registration)
     */
    public function getInsuranceCompanySettings(Request $request, $insuranceCompanyId)
    {
        try {
            $thirdPartyService = app(\App\Services\ThirdPartyApiService::class);
            $settings = $thirdPartyService->getInsuranceCompanySettings($insuranceCompanyId);

            if ($settings) {
                return response()->json([
                    'success' => true,
                    'data' => $settings
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Insurance company settings not found'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error fetching insurance company settings', [
                'insurance_company_id' => $insuranceCompanyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching insurance company settings'
            ], 500);
        }
    }
}
