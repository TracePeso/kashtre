<?php

namespace App\Http\Controllers;

use App\Models\MaturationPeriod;
use App\Models\MaturationSystemDefault;
use App\Models\Business;
use App\Models\PaymentMethodAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MaturationPeriodController extends Controller
{
    private function permissions(): array
    {
        return (array) (auth()->user()->permissions ?? []);
    }

    private function hasSettingsAdminAccess(): bool
    {
        return auth()->user()->business_id === 1;
    }

    private function can(string $permission): bool
    {
        return in_array($permission, $this->permissions()) || $this->hasSettingsAdminAccess();
    }

    public function __construct()
    {
        // Only allow Kashtre users (business_id = 1) with proper permissions to access these settings
        $this->middleware(function ($request, $next) {
            if (auth()->user()->business_id !== 1) {
                abort(403, 'Access denied. This feature is only available to Kashtre administrators.');
            }

            // Check for View Maturation Periods permission
            if (!$this->can('View Maturation Periods')) {
                abort(403, 'Access denied. You do not have permission to view maturation periods.');
            }

            return $next($request);
        });
    }

    /**
     * Payment methods index (Livewire tables + system defaults summary).
     */
    public function indexLivewire()
    {
        return view('settings.maturation-periods.index-livewire', [
            'entityDefaultsMap' => MaturationSystemDefault::entityDefaultsMap(),
            'serviceChargeDefaultsMap' => MaturationSystemDefault::serviceChargeDefaultsMap(),
            'maturationDefaultLabels' => self::maturationPaymentMethodLabelMap(),
        ]);
    }

    public function editSystemDefaults()
    {
        if (! in_array('Edit Maturation Periods', auth()->user()->permissions ?? [])) {
            abort(403, 'Access denied. You do not have permission to edit maturation periods.');
        }

        return view('settings.maturation-periods.system-defaults.edit', [
            'methods' => MaturationSystemDefault::orderedPaymentMethods(),
            'entityMap' => MaturationSystemDefault::entityDefaultsMap(),
            'serviceChargeMap' => MaturationSystemDefault::serviceChargeDefaultsMap(),
            'labels' => self::maturationPaymentMethodLabelMap(),
        ]);
    }

    public function updateSystemDefaults(Request $request)
    {
        if (! in_array('Edit Maturation Periods', auth()->user()->permissions ?? [])) {
            abort(403, 'Access denied. You do not have permission to edit maturation periods.');
        }

        $allowed = MaturationSystemDefault::allowedPaymentMethods();
        $rules = [];
        foreach ($allowed as $method) {
            $rules["entity.$method"] = 'required|integer|min:0|max:365';
            $rules["service_charge.$method"] = 'required|integer|min:0|max:365';
        }

        $validated = $request->validate($rules);

        MaturationSystemDefault::syncFromArrays($validated['entity'], $validated['service_charge']);

        return redirect()->route('maturation-periods.index', ['tab' => 'system-defaults'])
            ->with('success', 'System default maturation periods saved. They apply to all businesses until overridden per entity.');
    }

    protected static function maturationPaymentMethodLabelMap(): array
    {
        return [
            'insurance' => 'Insurance',
            'credit_arrangement' => 'Credit Arrangement',
            'mobile_money' => 'Mobile Money',
            'v_card' => 'V Card (Virtual Card)',
            'p_card' => 'P Card (Physical Card)',
            'bank_transfer' => 'Bank Transfer',
            'cash' => 'Cash',
        ];
    }

    public function index()
    {
        $maturationPeriods = MaturationPeriod::with(['business', 'paymentMethodAccount', 'createdBy', 'updatedBy'])
            ->orderBy('business_id')
            ->orderBy('payment_method')
            ->get();

        $businesses = Business::where('id', '!=', 1)->orderBy('name')->get();
        $paymentMethods = ['insurance', 'credit_arrangement', 'mobile_money', 'v_card', 'p_card', 'bank_transfer', 'cash'];

        return view('settings.maturation-periods.index', compact('maturationPeriods', 'businesses', 'paymentMethods'));
    }

    public function create()
    {
        // Check for Add Maturation Periods permission
        if (!$this->can('Add Maturation Periods')) {
            abort(403, 'Access denied. You do not have permission to add maturation periods.');
        }

        $businesses = Business::where('id', '!=', 1)->orderBy('name')->get();
        $paymentMethods = ['insurance', 'credit_arrangement', 'mobile_money', 'v_card', 'p_card', 'bank_transfer', 'cash'];
        
        // Get payment method accounts (separate model, not money_accounts)
        $paymentMethodAccounts = PaymentMethodAccount::whereIn('business_id', $businesses->pluck('id'))
            ->with(['business'])
            ->orderBy('business_id')
            ->orderBy('payment_method')
            ->get();

        return view('settings.maturation-periods.create', compact('businesses', 'paymentMethods', 'paymentMethodAccounts'));
    }

    public function checkAccount(Request $request)
    {
        $businessId = $request->input('business_id');
        $paymentMethod = $request->input('payment_method');
        
        if (!$businessId || !$paymentMethod) {
            return response()->json(['exists' => false]);
        }
        
        // Check if an account exists for this business and payment method
        // Since we want one account per payment method per business, we'll get the first one
        $account = PaymentMethodAccount::where('business_id', $businessId)
            ->where('payment_method', $paymentMethod)
            ->first();
        
        if ($account) {
            return response()->json([
                'exists' => true,
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'provider' => $account->provider,
                    'account_number' => $account->account_number,
                    'account_holder_name' => $account->account_holder_name,
                    'balance' => number_format($account->balance, 2),
                    'currency' => $account->currency ?? 'USD',
                ]
            ]);
        }
        
        return response()->json(['exists' => false]);
    }

    public function store(Request $request)
    {
        // Check for Add Maturation Periods permission
        if (!$this->can('Add Maturation Periods')) {
            abort(403, 'Access denied. You do not have permission to add maturation periods.');
        }

        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'payment_method' => 'required|in:insurance,credit_arrangement,mobile_money,v_card,p_card,bank_transfer,cash',
            'maturation_days' => 'required|integer|min:0|max:365',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'payment_method_account_id' => 'nullable|exists:payment_method_accounts,id',
            'account_provider' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'account_holder_name' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            // Handle payment method account for mobile_money and bank_transfer
            if (in_array($validated['payment_method'], ['mobile_money', 'bank_transfer'])) {
                // Check if account already exists for this business and payment method
                $existingAccount = PaymentMethodAccount::where('business_id', $validated['business_id'])
                    ->where('payment_method', $validated['payment_method'])
                    ->first();
                
                if ($existingAccount) {
                    // Use existing account automatically
                    $validated['payment_method_account_id'] = $existingAccount->id;
                } else {
                    // Validate that account provider is provided when creating new account
                    if (empty($validated['account_provider'])) {
                        DB::rollBack();
                        return redirect()->back()
                            ->withInput()
                            ->withErrors(['account_provider' => 'Provider is required when creating a new payment method account.'])
                            ->with('error', 'Please provide the account provider details.');
                    }
                    
                    // Create new payment method account with provided details
                    $business = Business::find($validated['business_id']);
                    $paymentMethodName = match($validated['payment_method']) {
                        'mobile_money' => 'Mobile Money',
                        'bank_transfer' => 'Bank Transfer',
                        default => ucfirst(str_replace('_', ' ', $validated['payment_method'])),
                    };
                    
                    $account = PaymentMethodAccount::create([
                        'name' => $paymentMethodName . ' Account - ' . $business->name,
                        'business_id' => $validated['business_id'],
                        'payment_method' => $validated['payment_method'],
                        'provider' => $validated['account_provider'] ?? null,
                        'account_number' => $validated['account_number'] ?? null,
                        'account_holder_name' => $validated['account_holder_name'] ?? null,
                        'balance' => 0.00,
                        'currency' => 'UGX',
                        'description' => "Payment method account for {$paymentMethodName}",
                        'is_active' => true,
                        'created_by' => Auth::id(),
                    ]);
                    
                    $validated['payment_method_account_id'] = $account->id;
                }
            }

            // Check if a maturation period already exists for this business and payment method
            $existing = MaturationPeriod::where('business_id', $validated['business_id'])
                ->where('payment_method', $validated['payment_method'])
                ->first();

            if ($existing) {
                DB::rollBack();
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'A maturation period already exists for this business and payment method combination.');
            }

            $validated['created_by'] = Auth::id();
            $validated['is_active'] = $validated['is_active'] ?? true;
            
            // Remove fields that aren't in the model
            unset(
                $validated['account_provider'],
                $validated['account_number'],
                $validated['account_holder_name']
            );

            MaturationPeriod::create($validated);
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create maturation period: ' . $e->getMessage());
        }

        return redirect()->route('maturation-periods.index', ['tab' => 'entities'])
            ->with('success', 'Maturation period created successfully.');
    }

    public function show(MaturationPeriod $maturationPeriod)
    {
        // Check for View Maturation Periods permission
        if (!$this->can('View Maturation Periods')) {
            abort(403, 'Access denied. You do not have permission to view maturation periods.');
        }

        $maturationPeriod->load(['business', 'paymentMethodAccount', 'createdBy', 'updatedBy']);

        return view('settings.maturation-periods.show', compact('maturationPeriod'));
    }

    public function edit(MaturationPeriod $maturationPeriod)
    {
        // Check for Edit Maturation Periods permission
        if (!$this->can('Edit Maturation Periods')) {
            abort(403, 'Access denied. You do not have permission to edit maturation periods.');
        }

        $businesses = Business::where('id', '!=', 1)->orderBy('name')->get();
        $paymentMethods = ['insurance', 'credit_arrangement', 'mobile_money', 'v_card', 'p_card', 'bank_transfer', 'cash'];
        
        // Get payment method accounts
        $paymentMethodAccounts = PaymentMethodAccount::where('business_id', $maturationPeriod->business_id)
            ->with(['business'])
            ->orderBy('payment_method')
            ->orderBy('name')
            ->get();

        return view('settings.maturation-periods.edit', compact('maturationPeriod', 'businesses', 'paymentMethods', 'paymentMethodAccounts'));
    }

    public function update(Request $request, MaturationPeriod $maturationPeriod)
    {
        // Check for Edit Maturation Periods permission
        if (!$this->can('Edit Maturation Periods')) {
            abort(403, 'Access denied. You do not have permission to edit maturation periods.');
        }

        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'payment_method' => 'required|in:insurance,credit_arrangement,mobile_money,v_card,p_card,bank_transfer,cash',
            'maturation_days' => 'required|integer|min:0|max:365',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'payment_method_account_id' => 'nullable|exists:payment_method_accounts,id',
            'account_provider' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'account_holder_name' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            // Handle payment method account for mobile_money and bank_transfer
            if (in_array($validated['payment_method'], ['mobile_money', 'bank_transfer'])) {
                // Check if account already exists for this business and payment method
                $existingAccount = PaymentMethodAccount::where('business_id', $validated['business_id'])
                    ->where('payment_method', $validated['payment_method'])
                    ->first();
                
                if ($existingAccount) {
                    // Use existing account automatically
                    $validated['payment_method_account_id'] = $existingAccount->id;
                } else {
                    // Validate that account provider is provided when creating new account
                    if (empty($validated['account_provider'])) {
                        DB::rollBack();
                        return redirect()->back()
                            ->withInput()
                            ->withErrors(['account_provider' => 'Provider is required when creating a new payment method account.'])
                            ->with('error', 'Please provide the account provider details.');
                    }
                    
                    // Create new payment method account with provided details
                    $business = Business::find($validated['business_id']);
                    $paymentMethodName = match($validated['payment_method']) {
                        'mobile_money' => 'Mobile Money',
                        'bank_transfer' => 'Bank Transfer',
                        default => ucfirst(str_replace('_', ' ', $validated['payment_method'])),
                    };
                    
                    $account = PaymentMethodAccount::create([
                        'name' => $paymentMethodName . ' Account - ' . $business->name,
                        'business_id' => $validated['business_id'],
                        'payment_method' => $validated['payment_method'],
                        'provider' => $validated['account_provider'] ?? null,
                        'account_number' => $validated['account_number'] ?? null,
                        'account_holder_name' => $validated['account_holder_name'] ?? null,
                        'balance' => 0.00,
                        'currency' => 'UGX',
                        'description' => "Payment method account for {$paymentMethodName}",
                        'is_active' => true,
                        'created_by' => Auth::id(),
                    ]);
                    
                    $validated['payment_method_account_id'] = $account->id;
                }
            }

            // Check if another maturation period exists for this business and payment method combination
            $existing = MaturationPeriod::where('business_id', $validated['business_id'])
                ->where('payment_method', $validated['payment_method'])
                ->where('id', '!=', $maturationPeriod->id)
                ->first();

            if ($existing) {
                DB::rollBack();
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'A maturation period already exists for this business and payment method combination.');
            }

            $validated['updated_by'] = Auth::id();
            $validated['is_active'] = $validated['is_active'] ?? true;

            $maturationPeriod->update($validated);
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to update maturation period: ' . $e->getMessage());
        }

        return redirect()->route('maturation-periods.index', ['tab' => 'entities'])
            ->with('success', 'Maturation period updated successfully.');
    }

    public function destroy(MaturationPeriod $maturationPeriod)
    {
        // Check for Delete Maturation Periods permission
        if (!$this->can('Delete Maturation Periods')) {
            abort(403, 'Access denied. You do not have permission to delete maturation periods.');
        }

        $maturationPeriod->delete();

        return redirect()->route('maturation-periods.index', ['tab' => 'entities'])
            ->with('success', 'Maturation period deleted successfully.');
    }

    public function toggleStatus(MaturationPeriod $maturationPeriod)
    {
        // Check for Manage Maturation Periods permission
        if (!$this->can('Manage Maturation Periods')) {
            abort(403, 'Access denied. You do not have permission to manage maturation periods.');
        }

        $maturationPeriod->update([
            'is_active' => !$maturationPeriod->is_active,
            'updated_by' => Auth::id(),
        ]);

        $status = $maturationPeriod->is_active ? 'activated' : 'deactivated';

        return redirect()->route('maturation-periods.index', ['tab' => 'entities'])
            ->with('success', "Maturation period {$status} successfully.");
    }
}
