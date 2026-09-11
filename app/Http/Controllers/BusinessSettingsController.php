<?php

namespace App\Http\Controllers;

use App\Domain\Time\Enums\PolicyPurpose;
use App\Domain\Time\Enums\PolicyScopeType;
use App\Domain\Time\Exceptions\TimeEngineException;
use App\Domain\Time\Models\CoreTimeZone;
use App\Domain\Time\Services\SharedTimeGateway;
use App\Domain\Time\ValueObjects\TimeContext;
use App\Http\Controllers\Concerns\HandlesBusinessBranding;
use App\Models\Business;
use App\Models\Country;
use App\Models\CreditLimitApprovalApprover;
use App\Models\Item;
use App\Models\User;
use App\Support\BusinessBranding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class BusinessSettingsController extends Controller
{
    use HandlesBusinessBranding;

    /**
     * Show the form for editing business settings.
     */
    public function edit(SharedTimeGateway $time)
    {
        if (! in_array('View Business Settings', Auth::user()->permissions ?? [])) {
            return redirect()->route('dashboard')->with('error', 'You do not have permission to view business settings.');
        }

        $business = Business::with('country')->findOrFail(Auth::user()->business_id);

        $users = User::where('business_id', $business->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'business_id']);

        $items = Item::where('business_id', $business->id)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type']);

        $countries = Country::with('currency')->orderedDefaultUsFirst()->get();

        $documentBranding = BusinessBranding::for($business);

        [$timeZones, $currentTimezone, $timezoneResolution] = $this->timezoneFormData($time, (string) $business->id);

        $tenantSettings = Schema::hasTable('core_time_tenant_settings')
            ? $time->tenantSettings((string) $business->id)
            : null;

        $branches = \App\Models\Branch::query()
            ->where('business_id', $business->id)
            ->orderBy('name')
            ->get(['id', 'uuid', 'name']);

        $branchTimezones = [];
        foreach ($branches as $branch) {
            try {
                $branchTimezones[$branch->id] = $time->resolveTimezone(
                    (string) $business->id,
                    (string) $branch->id,
                )->toArray();
            } catch (\Throwable) {
                $branchTimezones[$branch->id] = null;
            }
        }

        $openPeriods = Schema::hasTable('core_financial_periods')
            ? \App\Domain\Time\Models\FinancialPeriod::query()
                ->where('tenant_key', (string) $business->id)
                ->orderByDesc('local_start_date')
                ->limit(12)
                ->get()
            : collect();

        return view('business-settings.edit', compact(
            'business',
            'users',
            'items',
            'countries',
            'documentBranding',
            'timeZones',
            'currentTimezone',
            'timezoneResolution',
            'tenantSettings',
            'branches',
            'branchTimezones',
            'openPeriods',
        ));
    }

    /**
     * Update business settings.
     */
    public function update(Request $request, SharedTimeGateway $time)
    {
        if (! in_array('Edit Business Settings', Auth::user()->permissions ?? [])) {
            return redirect()->route('dashboard')->with('error', 'You do not have permission to edit business settings.');
        }

        $business = Business::findOrFail(Auth::user()->business_id);

        if ($request->input('settings_section') === 'time') {
            return $this->updateTimeSettings($request, $time, $business);
        }

        if ($request->input('exchange_rate_to_usd') === '' || $request->input('exchange_rate_to_usd') === null) {
            $request->merge(['exchange_rate_to_usd' => null]);
        }

        $validated = $request->validate(array_merge(
            BusinessBranding::validationRules($business->id),
            [
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
                'grn_technical_supervisor_required' => 'nullable|boolean',
            ]
        ));

        $validated = $this->applyBrandingFromRequest($request, $business, $validated);

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
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'address' => $validated['address'],
            'logo' => $validated['logo'] ?? $business->logo,
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
            'admit_enable_credit' => $request->has('admit_enable_credit') ? (bool) $validated['admit_enable_credit'] : false,
            'admit_enable_long_stay' => $request->has('admit_enable_long_stay') ? (bool) $validated['admit_enable_long_stay'] : false,
            'discharge_remove_credit' => $request->has('discharge_remove_credit') ? (bool) $validated['discharge_remove_credit'] : false,
            'discharge_remove_long_stay' => $request->has('discharge_remove_long_stay') ? (bool) $validated['discharge_remove_long_stay'] : true,
            'credit_excluded_items' => $validated['credit_excluded_items'] ?? [],
            'third_party_excluded_items' => $validated['third_party_excluded_items'] ?? [],
            'grn_technical_supervisor_required' => $request->boolean('grn_technical_supervisor_required'),
        ]);

        DB::transaction(function () use ($business, $request) {
            CreditLimitApprovalApprover::where('business_id', $business->id)->delete();

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

        return redirect()->route('business-settings.edit', ['tab' => 'general'])
            ->with('success', 'Business settings updated successfully.');
    }

    private function updateTimeSettings(Request $request, SharedTimeGateway $time, Business $business)
    {
        if (! in_array('Edit Business Settings', Auth::user()->permissions ?? [])
            && ! in_array('Edit Time Settings', Auth::user()->permissions ?? [])) {
            return redirect()->route('dashboard')->with('error', 'You do not have permission to edit time settings.');
        }

        $timezoneRule = ['required', 'string', 'max:64'];
        if (Schema::hasTable('core_time_zones') && CoreTimeZone::query()->exists()) {
            $timezoneRule[] = Rule::exists('core_time_zones', 'iana_id')->where('status', 'ACTIVE');
        } else {
            $timezoneRule[] = Rule::in(timezone_identifiers_list());
        }

        $validated = $request->validate([
            'operational_timezone' => $timezoneRule,
            'day_rollover_offset_minutes' => ['nullable', 'integer', 'min:0', 'max:1439'],
            'enforce_financial_periods' => ['nullable', 'boolean'],
            'allow_user_presentation_timezone' => ['nullable', 'boolean'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'branch_timezone' => ['nullable', 'string', 'max:64'],
            'period_code' => ['nullable', 'string', 'max:64'],
            'period_name' => ['nullable', 'string', 'max:180'],
            'period_start' => ['nullable', 'date_format:Y-m-d'],
            'period_end' => ['nullable', 'date_format:Y-m-d'],
            'time_action' => ['nullable', 'string', 'in:save_timezone,open_period,close_period'],
        ]);

        $tenantKey = (string) $business->id;
        $messages = [];

        try {
            if (($validated['time_action'] ?? 'save_timezone') === 'open_period'
                && ! empty($validated['period_code'])
                && ! empty($validated['period_start'])
                && ! empty($validated['period_end'])) {
                $time->periods()->open(
                    $tenantKey,
                    $validated['period_code'],
                    $validated['period_name'] ?: $validated['period_code'],
                    \App\Domain\Time\ValueObjects\LocalDate::parse($validated['period_start']),
                    \App\Domain\Time\ValueObjects\LocalDate::parse($validated['period_end']),
                    'MONTH',
                    $validated['operational_timezone'],
                );
                $messages[] = 'Financial period opened.';
            } elseif (($validated['time_action'] ?? '') === 'close_period' && $request->filled('period_id')) {
                $period = \App\Domain\Time\Models\FinancialPeriod::query()
                    ->where('tenant_key', $tenantKey)
                    ->where('id', (int) $request->input('period_id'))
                    ->firstOrFail();
                $time->periods()->close($period, 'Closed from Business Settings');
                $messages[] = 'Financial period closed.';
            } else {
                $messages[] = $this->syncBusinessTimezone($time, $tenantKey, $validated['operational_timezone']);

                if (Schema::hasTable('core_time_tenant_settings')) {
                    $time->updateTenantSettings(
                        $tenantKey,
                        isset($validated['day_rollover_offset_minutes']) ? (int) $validated['day_rollover_offset_minutes'] : null,
                        $request->boolean('enforce_financial_periods'),
                        $request->boolean('allow_user_presentation_timezone'),
                    );
                    $messages[] = 'Tenant time settings saved.';
                }

                if (! empty($validated['branch_id']) && ! empty($validated['branch_timezone'])) {
                    $branch = \App\Models\Branch::query()
                        ->where('business_id', $business->id)
                        ->where('id', $validated['branch_id'])
                        ->firstOrFail();
                    $time->setScopeTimezone(
                        $tenantKey,
                        PolicyScopeType::BRANCH,
                        (string) $branch->id,
                        $validated['branch_timezone'],
                        PolicyPurpose::OPERATIONAL,
                        'Branch timezone from Business Settings',
                    );
                    $messages[] = 'Branch timezone set for '.$branch->name.'.';
                }
            }
        } catch (TimeEngineException $e) {
            return redirect()->route('business-settings.edit', ['tab' => 'time'])
                ->with('error', 'Timezone was not updated: '.$e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->route('business-settings.edit', ['tab' => 'time'])
                ->with('error', 'Time settings failed: '.$e->getMessage());
        }

        return redirect()->route('business-settings.edit', ['tab' => 'time'])
            ->with('success', implode(' ', $messages));
    }

    /**
     * @return array{0: \Illuminate\Support\Collection, 1: string, 2: array|null}
     */
    private function timezoneFormData(SharedTimeGateway $time, string $tenantKey): array
    {
        $timeZones = collect();
        $currentTimezone = (string) config('time.default_timezone', 'UTC');
        $timezoneResolution = null;

        if (! Schema::hasTable('core_time_zones')) {
            return [$timeZones, $currentTimezone, $timezoneResolution];
        }

        try {
            $timeZones = CoreTimeZone::query()
                ->where('status', 'ACTIVE')
                ->orderBy('region_code')
                ->orderBy('iana_id')
                ->get(['iana_id', 'display_name', 'region_code']);

            $resolved = $time->resolution()->resolve(
                new TimeContext(tenantId: $tenantKey, purpose: PolicyPurpose::OPERATIONAL),
                $tenantKey,
            );
            $currentTimezone = $resolved->ianaId->value();
            $timezoneResolution = $resolved->toArray();
        } catch (\Throwable) {
            // Catalogue may be empty before time:install.
        }

        return [$timeZones, $currentTimezone, $timezoneResolution];
    }

    private function syncBusinessTimezone(SharedTimeGateway $time, string $tenantKey, string $ianaId): string
    {
        $resolved = $time->resolution()->resolve(
            new TimeContext(tenantId: $tenantKey, purpose: PolicyPurpose::OPERATIONAL),
            $tenantKey,
        );

        if (
            ! $resolved->usedFallback
            && $resolved->scopeType === PolicyScopeType::TENANT
            && $resolved->ianaId->value() === $ianaId
        ) {
            return 'Timezone unchanged ('.$ianaId.').';
        }

        $time->catalogue()->ensureKnown($ianaId);
        $draft = $time->policies()->draft(
            $tenantKey,
            PolicyScopeType::TENANT,
            $tenantKey,
            $ianaId,
            PolicyPurpose::OPERATIONAL,
            reason: 'Updated from Business Settings',
        );
        $time->policies()->activate($draft, 'Updated from Business Settings');

        return 'Operational timezone set to '.$ianaId.'.';
    }
}
