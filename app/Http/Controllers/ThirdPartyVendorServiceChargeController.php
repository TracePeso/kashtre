<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\InsuranceCompany;
use App\Models\ThirdPartyVendorServiceCharge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ThirdPartyVendorServiceChargeController extends Controller
{
    public function index()
    {
        $this->authorizeIndex();

        return view('third-party-vendor-service-charges.index');
    }

    public function create()
    {
        $this->authorizeMutate();

        $user = Auth::user();
        $businesses = $this->businessesForForm($user);
        $defaultTiers = config('third_party_vendor_service_charges.default_tiers', []);

        $insuranceCompaniesForClinic = collect();
        if ((int) $user->business_id !== 1) {
            $insuranceCompaniesForClinic = InsuranceCompany::query()
                ->where('business_id', (int) $user->business_id)
                ->orderBy('name')
                ->get();
        }

        return view('third-party-vendor-service-charges.create', compact(
            'businesses',
            'defaultTiers',
            'insuranceCompaniesForClinic'
        ));
    }

    public function insuranceCompaniesForBusiness(Business $business)
    {
        $this->authorizeMutate();
        $this->ensureUserMayManageBusiness(Auth::user(), (int) $business->id);

        return response()->json(
            InsuranceCompany::query()
                ->where('business_id', $business->id)
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
        );
    }

    public function store(Request $request)
    {
        $this->authorizeMutate();

        $user = Auth::user();
        $businessId = (int) $user->business_id === 1
            ? (int) $request->input('entity_id')
            : (int) $user->business_id;

        $validator = $this->validatorForTiers($request, $user, $businessId, true);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $this->ensureUserMayManageBusiness($user, $businessId);

        $insuranceCompanyId = $this->resolveTargetInsuranceCompanyId($request);

        $rows = $this->normalizeTierRows($request->input('service_charges', []));

        DB::transaction(function () use ($businessId, $insuranceCompanyId, $rows, $user): void {
            $q = ThirdPartyVendorServiceCharge::where('business_id', $businessId);
            if ($insuranceCompanyId !== null) {
                $q->where('insurance_company_id', $insuranceCompanyId);
            } else {
                $q->whereNull('insurance_company_id');
            }
            $q->delete();

            foreach ($rows as $index => $chargeData) {
                ThirdPartyVendorServiceCharge::create([
                    'business_id' => $businessId,
                    'insurance_company_id' => $insuranceCompanyId,
                    'lower_bound' => $chargeData['lower_bound'],
                    'upper_bound' => $chargeData['upper_bound'],
                    'amount' => $chargeData['amount'],
                    'type' => $chargeData['type'],
                    'is_active' => true,
                    'sort_order' => $index,
                    'created_by' => $user->id,
                ]);
            }
        });

        return redirect()
            ->route('third-party-vendor-service-charges.index')
            ->with('success', 'Third-party vendor service charges saved.');
    }

    public function edit(Business $business, Request $request)
    {
        $this->authorizeMutate();
        $this->ensureUserMayManageBusiness(Auth::user(), (int) $business->id);

        $insuranceCompanyId = $this->parseInsuranceCompanyQuery($request, $business);

        $tiers = ThirdPartyVendorServiceCharge::query()
            ->where('business_id', $business->id)
            ->when(
                $insuranceCompanyId !== null,
                fn ($q) => $q->where('insurance_company_id', $insuranceCompanyId),
                fn ($q) => $q->whereNull('insurance_company_id')
            )
            ->orderBy('sort_order')
            ->orderBy('lower_bound')
            ->get();

        $defaultTiers = config('third_party_vendor_service_charges.default_tiers', []);

        $insuranceCompany = $insuranceCompanyId !== null
            ? InsuranceCompany::find($insuranceCompanyId)
            : null;

        return view('third-party-vendor-service-charges.edit', compact(
            'business',
            'tiers',
            'defaultTiers',
            'insuranceCompanyId',
            'insuranceCompany'
        ));
    }

    public function update(Request $request, Business $business)
    {
        $this->authorizeMutate();
        $this->ensureUserMayManageBusiness(Auth::user(), (int) $business->id);

        $request->merge(['entity_id' => $business->id]);
        $validator = $this->validatorForTiers($request, Auth::user(), (int) $business->id, false);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $insuranceCompanyId = $this->resolveTargetInsuranceCompanyIdForUpdate($request, $business);

        $rows = $this->normalizeTierRows($request->input('service_charges', []));

        DB::transaction(function () use ($business, $insuranceCompanyId, $rows): void {
            $q = ThirdPartyVendorServiceCharge::where('business_id', $business->id);
            if ($insuranceCompanyId !== null) {
                $q->where('insurance_company_id', $insuranceCompanyId);
            } else {
                $q->whereNull('insurance_company_id');
            }
            $q->delete();

            foreach ($rows as $index => $chargeData) {
                ThirdPartyVendorServiceCharge::create([
                    'business_id' => $business->id,
                    'insurance_company_id' => $insuranceCompanyId,
                    'lower_bound' => $chargeData['lower_bound'],
                    'upper_bound' => $chargeData['upper_bound'],
                    'amount' => $chargeData['amount'],
                    'type' => $chargeData['type'],
                    'is_active' => true,
                    'sort_order' => $index,
                    'created_by' => Auth::id(),
                ]);
            }
        });

        return redirect()
            ->route('third-party-vendor-service-charges.index')
            ->with('success', 'Third-party vendor service charges updated.');
    }

    public function destroy(ThirdPartyVendorServiceCharge $thirdPartyVendorServiceCharge)
    {
        $this->authorizeMutate();
        $this->ensureUserMayManageBusiness(Auth::user(), (int) $thirdPartyVendorServiceCharge->business_id);

        $thirdPartyVendorServiceCharge->delete();

        return redirect()
            ->route('third-party-vendor-service-charges.index')
            ->with('success', 'Service charge tier removed.');
    }

    protected function authorizeIndex(): void
    {
        $user = Auth::user();
        $permissions = $user->permissions ?? [];
        if (
            (int) ($user->business_id ?? 0) !== 1
            && ! in_array('View Insurance Companies', $permissions)
            && ! in_array('Manage Service Charges', $permissions)
        ) {
            abort(403, 'Unauthorized.');
        }
    }

    protected function authorizeMutate(): void
    {
        $user = Auth::user();
        if (
            (int) ($user->business_id ?? 0) !== 1
            && ! in_array('Manage Service Charges', $user->permissions ?? [])
        ) {
            abort(403, 'You need the Manage Service Charges permission.');
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Business>
     */
    protected function businessesForForm($user)
    {
        if ((int) $user->business_id === 1) {
            return Business::where('id', '!=', 1)->orderBy('name')->get();
        }

        return Business::where('id', $user->business_id)->get();
    }

    protected function ensureUserMayManageBusiness($user, int $businessId): void
    {
        if ((int) $user->business_id === 1) {
            if ($businessId === 1) {
                abort(422, 'Select a clinic business (not the platform root).');
            }
            if (! Business::whereKey($businessId)->exists()) {
                abort(404);
            }

            return;
        }

        if ((int) $user->business_id !== $businessId) {
            abort(403, 'You can only manage charges for your business.');
        }
    }

    protected function validatorForTiers(Request $request, $user, ?int $insuranceValidationBusinessId, bool $isStore): \Illuminate\Validation\Validator
    {
        $rules = [
            'service_charges' => 'required|array|min:1',
            'service_charges.*.lower_bound' => 'required|numeric|min:0',
            'service_charges.*.upper_bound' => 'nullable|numeric|min:0',
            'service_charges.*.amount' => 'required|numeric|min:0',
            'service_charges.*.type' => 'required|in:fixed,percentage',
        ];

        if ((int) $user->business_id === 1) {
            $rules['entity_id'] = 'required|integer|exists:businesses,id';
        }

        if ($isStore) {
            $rules['charge_scope'] = 'required|in:all,vendor';
            $rules['insurance_company_id'] = 'nullable|required_if:charge_scope,vendor|exists:insurance_companies,id';
        } else {
            $rules['insurance_company_id'] = 'nullable|integer|exists:insurance_companies,id';
        }

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($validator) use ($request, $insuranceValidationBusinessId, $isStore): void {
            foreach ($request->service_charges ?? [] as $index => $chargeData) {
                $upper = $chargeData['upper_bound'] ?? null;
                $lower = $chargeData['lower_bound'] ?? null;
                if ($upper !== null && $upper !== '' && $lower !== null && $lower !== '') {
                    if ((float) $upper <= (float) $lower) {
                        $validator->errors()->add(
                            "service_charges.{$index}.upper_bound",
                            'Upper bound must be greater than lower bound.'
                        );
                    }
                }

                if (($chargeData['type'] ?? '') === 'percentage' && isset($chargeData['amount'])) {
                    if ((float) $chargeData['amount'] > 100) {
                        $validator->errors()->add(
                            "service_charges.{$index}.amount",
                            'Percentage cannot exceed 100%.'
                        );
                    }
                }
            }

            if ($insuranceValidationBusinessId === null || $insuranceValidationBusinessId < 1) {
                return;
            }

            $shouldCheckVendorBelongs = false;
            if ($isStore && $request->input('charge_scope') === 'vendor') {
                $shouldCheckVendorBelongs = true;
            }
            if (! $isStore) {
                $raw = $request->input('insurance_company_id');
                $shouldCheckVendorBelongs = $raw !== null && $raw !== '';
            }

            if (! $shouldCheckVendorBelongs) {
                return;
            }

            $icId = (int) $request->input('insurance_company_id');
            $ok = InsuranceCompany::where('id', $icId)
                ->where('business_id', $insuranceValidationBusinessId)
                ->exists();

            if (! $ok) {
                $validator->errors()->add(
                    'insurance_company_id',
                    'Select a third-party vendor that belongs to this clinic.'
                );
            }
        });

        return $validator;
    }

    protected function resolveTargetInsuranceCompanyId(Request $request): ?int
    {
        if ($request->input('charge_scope') !== 'vendor') {
            return null;
        }

        $id = (int) $request->input('insurance_company_id');

        return $id > 0 ? $id : null;
    }

    protected function resolveTargetInsuranceCompanyIdForUpdate(Request $request, Business $business): ?int
    {
        $raw = $request->input('insurance_company_id');
        if ($raw === null || $raw === '') {
            return null;
        }

        return (int) $raw;
    }

    protected function parseInsuranceCompanyQuery(Request $request, Business $business): ?int
    {
        $raw = $request->query('insurance_company_id');
        if ($raw === null || $raw === '') {
            return null;
        }

        $id = (int) $raw;
        if ($id < 1) {
            return null;
        }

        $exists = InsuranceCompany::where('id', $id)
            ->where('business_id', $business->id)
            ->exists();

        if (! $exists) {
            abort(404);
        }

        return $id;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{lower_bound: float, upper_bound: ?float, amount: float, type: string}>
     */
    protected function normalizeTierRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $upper = $row['upper_bound'] ?? null;
            if ($upper === '' || $upper === null) {
                $upper = null;
            } else {
                $upper = (float) $upper;
            }

            $out[] = [
                'lower_bound' => (float) ($row['lower_bound'] ?? 0),
                'upper_bound' => $upper,
                'amount' => (float) ($row['amount'] ?? 0),
                'type' => (string) ($row['type'] ?? 'percentage'),
            ];
        }

        return $out;
    }
}
