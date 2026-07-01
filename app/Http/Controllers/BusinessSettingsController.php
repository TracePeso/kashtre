<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\User;
use App\Models\CreditLimitApprovalApprover;
use App\Models\Item;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BusinessSettingsController extends Controller
{
    /**
     * Show the form for editing business settings.
     */
    public function edit()
    {
        // Check if user has permission
        if (!in_array('View Business Settings', Auth::user()->permissions ?? [])) {
            return redirect()->route('dashboard')->with('error', 'You do not have permission to view business settings.');
        }

        $business = Business::with('country')->findOrFail(Auth::user()->business_id);
        
        // Get users for the business
        $users = User::where('business_id', $business->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'business_id']);

        // Get items for this business
        $items = Item::where('business_id', $business->id)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type']);

        $countries = Country::with('currency')->orderedDefaultUsFirst()->get();

        return view('business-settings.edit', compact('business', 'users', 'items', 'countries'));
    }

    /**
     * Update business settings.
     */
    public function update(Request $request)
    {
        // Check if user has permission
        if (!in_array('Edit Business Settings', Auth::user()->permissions ?? [])) {
            return redirect()->route('dashboard')->with('error', 'You do not have permission to edit business settings.');
        }

        $business = Business::findOrFail(Auth::user()->business_id);

        if ($request->input('exchange_rate_to_usd') === '' || $request->input('exchange_rate_to_usd') === null) {
            $request->merge(['exchange_rate_to_usd' => null]);
        }

        $validated = $request->validate([
            'max_third_party_credit_limit' => 'nullable|numeric|min:0',
            'max_first_party_credit_limit' => 'nullable|numeric|min:0',
            'country_id' => 'nullable|exists:countries,id',
            'currency_code' => 'nullable|string|max:10',
            'exchange_rate_to_usd' => 'nullable|numeric|min:0.000001',
            'financial_year_start_month' => 'required|integer|min:1|max:12',
            'financial_year_start_day' => 'required|integer|min:1|max:31',
            'admit_button_label' => 'nullable|string|max:255',
            'discharge_button_label' => 'nullable|string|max:255',
            'default_payment_terms_days' => 'nullable|integer|min:1|max:365',
            'admit_enable_credit' => 'nullable|boolean',
            'admit_enable_long_stay' => 'nullable|boolean',
            'discharge_remove_credit' => 'nullable|boolean',
            'discharge_remove_long_stay' => 'nullable|boolean',
            'credit_limit_initiators' => 'nullable|array',
            'credit_limit_initiators.*' => 'string',
            'credit_limit_authorizers' => 'nullable|array',
            'credit_limit_authorizers.*' => 'string',
            'credit_limit_approvers' => 'nullable|array',
            'credit_limit_approvers.*' => 'string',
            'credit_excluded_items' => 'nullable|array',
            'credit_excluded_items.*' => 'integer|exists:items,id',
            'third_party_excluded_items' => 'nullable|array',
            'third_party_excluded_items.*' => 'integer|exists:items,id',
        ]);

        $countryId = $validated['country_id'] ?? null;
        $currencyCode = strtoupper((string) ($validated['currency_code'] ?? ''));
        if ($countryId) {
            $country = Country::with('currency')->find($countryId);
            $currencyCode = strtoupper((string) ($country?->currency_code ?? $country?->currency?->code ?? 'USD'));
        } elseif ($currencyCode === '') {
            $currencyCode = strtoupper((string) ($business->currency_code ?? 'USD'));
        }

        $exchangeRate = array_key_exists('exchange_rate_to_usd', $validated) && $validated['exchange_rate_to_usd'] !== null && $validated['exchange_rate_to_usd'] !== ''
            ? (float) $validated['exchange_rate_to_usd']
            : null;

        $business->update([
            'max_third_party_credit_limit' => $validated['max_third_party_credit_limit'] ?? null,
            'max_first_party_credit_limit' => $validated['max_first_party_credit_limit'] ?? null,
            'country_id' => $countryId,
            'currency_code' => $currencyCode,
            'exchange_rate_to_usd' => $exchangeRate,
            'financial_year_start_month' => (int) $validated['financial_year_start_month'],
            'financial_year_start_day' => (int) $validated['financial_year_start_day'],
            'admit_button_label' => $validated['admit_button_label'] ?? 'Admit Patient',
            'discharge_button_label' => $validated['discharge_button_label'] ?? 'Discharge Patient',
            'default_payment_terms_days' => $validated['default_payment_terms_days'] ?? 30,
            'admit_enable_credit' => $request->has('admit_enable_credit') ? (bool)$validated['admit_enable_credit'] : false,
            'admit_enable_long_stay' => $request->has('admit_enable_long_stay') ? (bool)$validated['admit_enable_long_stay'] : false,
            'discharge_remove_credit' => $request->has('discharge_remove_credit') ? (bool)$validated['discharge_remove_credit'] : false,
            'discharge_remove_long_stay' => $request->has('discharge_remove_long_stay') ? (bool)$validated['discharge_remove_long_stay'] : true,
            'credit_excluded_items' => $validated['credit_excluded_items'] ?? [],
            'third_party_excluded_items' => $validated['third_party_excluded_items'] ?? [],
        ]);

        // Handle credit limit approval approvers
        DB::transaction(function () use ($business, $request) {
            // Delete existing approvers
            CreditLimitApprovalApprover::where('business_id', $business->id)->delete();

            // Add initiators
            if ($request->has('credit_limit_initiators')) {
                foreach ($request->input('credit_limit_initiators', []) as $approver) {
                    if (strpos($approver, 'user:') === 0) {
                        $userId = (int) str_replace('user:', '', $approver);
                        CreditLimitApprovalApprover::create([
                            'business_id' => $business->id,
                            'approver_id' => $userId,
                            'approver_type' => 'user',
                            'approval_level' => 'initiator',
                        ]);
                    }
                }
            }

            // Add authorizers
            if ($request->has('credit_limit_authorizers')) {
                foreach ($request->input('credit_limit_authorizers', []) as $approver) {
                    if (strpos($approver, 'user:') === 0) {
                        $userId = (int) str_replace('user:', '', $approver);
                        CreditLimitApprovalApprover::create([
                            'business_id' => $business->id,
                            'approver_id' => $userId,
                            'approver_type' => 'user',
                            'approval_level' => 'authorizer',
                        ]);
                    }
                }
            }

            // Add approvers
            if ($request->has('credit_limit_approvers')) {
                foreach ($request->input('credit_limit_approvers', []) as $approver) {
                    if (strpos($approver, 'user:') === 0) {
                        $userId = (int) str_replace('user:', '', $approver);
                        CreditLimitApprovalApprover::create([
                            'business_id' => $business->id,
                            'approver_id' => $userId,
                            'approver_type' => 'user',
                            'approval_level' => 'approver',
                        ]);
                    }
                }
            }
        });

        return redirect()->route('business-settings.edit')
            ->with('success', 'Business settings updated successfully.');
    }
}
