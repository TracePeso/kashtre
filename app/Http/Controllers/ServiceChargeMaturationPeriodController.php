<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\ServiceChargeMaturationPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ServiceChargeMaturationPeriodController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (auth()->user()->business_id !== 1) {
                abort(403, 'Access denied. This feature is only available to Kashtre administrators.');
            }

            if (! in_array('View Maturation Periods', auth()->user()->permissions ?? [])) {
                abort(403, 'Access denied. You do not have permission to view maturation periods.');
            }

            return $next($request);
        });
    }

    public function create()
    {
        if (! in_array('Add Maturation Periods', auth()->user()->permissions ?? [])) {
            abort(403, 'Access denied. You do not have permission to add maturation periods.');
        }

        $businesses = Business::where('id', '!=', 1)->orderBy('name')->get();
        $paymentMethods = ['insurance', 'credit_arrangement', 'mobile_money', 'v_card', 'p_card', 'bank_transfer', 'cash'];

        return view('settings.maturation-periods.service-charges.create', compact('businesses', 'paymentMethods'));
    }

    public function store(Request $request)
    {
        if (! in_array('Add Maturation Periods', auth()->user()->permissions ?? [])) {
            abort(403, 'Access denied. You do not have permission to add maturation periods.');
        }

        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id|not_in:1',
            'payment_method' => 'required|in:insurance,credit_arrangement,mobile_money,v_card,p_card,bank_transfer,cash',
            'maturation_days' => 'required|integer|min:0|max:365',
            'description' => 'nullable|string|max:1000',
        ]);

        $existing = ServiceChargeMaturationPeriod::where('business_id', $validated['business_id'])
            ->where('payment_method', $validated['payment_method'])
            ->first();

        if ($existing) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'A service charge maturation period already exists for this entity and payment method.');
        }

        $validated['created_by'] = Auth::id();
        $validated['is_active'] = $request->boolean('is_active');

        ServiceChargeMaturationPeriod::create($validated);

        return redirect()->route('maturation-periods.index', ['tab' => 'service-charges'])
            ->with('success', 'Service charge maturation period created successfully.');
    }

    public function show(ServiceChargeMaturationPeriod $serviceChargeMaturationPeriod)
    {
        if (! in_array('View Maturation Periods', auth()->user()->permissions ?? [])) {
            abort(403, 'Access denied.');
        }

        $serviceChargeMaturationPeriod->load(['business', 'createdBy', 'updatedBy']);

        return view('settings.maturation-periods.service-charges.show', compact('serviceChargeMaturationPeriod'));
    }

    public function edit(ServiceChargeMaturationPeriod $serviceChargeMaturationPeriod)
    {
        if (! in_array('Edit Maturation Periods', auth()->user()->permissions ?? [])) {
            abort(403, 'Access denied.');
        }

        $businesses = Business::where('id', '!=', 1)->orderBy('name')->get();
        $paymentMethods = ['insurance', 'credit_arrangement', 'mobile_money', 'v_card', 'p_card', 'bank_transfer', 'cash'];

        return view('settings.maturation-periods.service-charges.edit', compact('serviceChargeMaturationPeriod', 'businesses', 'paymentMethods'));
    }

    public function update(Request $request, ServiceChargeMaturationPeriod $serviceChargeMaturationPeriod)
    {
        if (! in_array('Edit Maturation Periods', auth()->user()->permissions ?? [])) {
            abort(403, 'Access denied.');
        }

        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id|not_in:1',
            'payment_method' => 'required|in:insurance,credit_arrangement,mobile_money,v_card,p_card,bank_transfer,cash',
            'maturation_days' => 'required|integer|min:0|max:365',
            'description' => 'nullable|string|max:1000',
        ]);

        $existing = ServiceChargeMaturationPeriod::where('business_id', $validated['business_id'])
            ->where('payment_method', $validated['payment_method'])
            ->where('id', '!=', $serviceChargeMaturationPeriod->id)
            ->first();

        if ($existing) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Another service charge maturation period already exists for this entity and payment method.');
        }

        $validated['updated_by'] = Auth::id();
        $validated['is_active'] = $request->boolean('is_active');

        $serviceChargeMaturationPeriod->update($validated);

        return redirect()->route('maturation-periods.index', ['tab' => 'service-charges'])
            ->with('success', 'Service charge maturation period updated successfully.');
    }

    public function destroy(ServiceChargeMaturationPeriod $serviceChargeMaturationPeriod)
    {
        if (! in_array('Delete Maturation Periods', auth()->user()->permissions ?? [])) {
            abort(403, 'Access denied.');
        }

        $serviceChargeMaturationPeriod->delete();

        return redirect()->route('maturation-periods.index', ['tab' => 'service-charges'])
            ->with('success', 'Service charge maturation period deleted successfully.');
    }

    public function toggleStatus(ServiceChargeMaturationPeriod $serviceChargeMaturationPeriod)
    {
        if (! in_array('Manage Maturation Periods', auth()->user()->permissions ?? [])) {
            abort(403, 'Access denied.');
        }

        $serviceChargeMaturationPeriod->update([
            'is_active' => ! $serviceChargeMaturationPeriod->is_active,
            'updated_by' => Auth::id(),
        ]);

        $status = $serviceChargeMaturationPeriod->is_active ? 'activated' : 'deactivated';

        return redirect()->route('maturation-periods.index', ['tab' => 'service-charges'])
            ->with('success', "Service charge maturation period {$status} successfully.");
    }
}
