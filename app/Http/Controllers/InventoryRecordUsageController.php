<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
use App\Models\InventoryUsageEvent;
use App\Services\Inventory\InventoryMainModuleSyncService;
use App\Services\Inventory\InventoryUsagePaymentService;
use App\Support\InventoryBusinessContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InventoryRecordUsageController extends Controller
{
    use RequiresInventoryModule;

    public function __construct()
    {
        $this->middleware($this->inventoryMiddleware(...));
    }

    public function index()
    {
        return view('inventory.usage.index');
    }

    public function show(InventoryUsageEvent $usageEvent, InventoryUsagePaymentService $payments)
    {
        $this->authorizeUsageEvent($usageEvent);

        $usageEvent->load([
            'client:id,name,client_id,visit_id,branch_id,phone_number,payment_phone_number,is_credit_eligible',
            'item:id,name,code,strength',
            'store:id,name,distribution_type',
            'recordedBy:id,name',
            'invoice:id,invoice_number,total_amount,balance_due,payment_status,status,payment_phone,currency,items',
        ]);

        return view('inventory.usage.show', [
            'event' => $usageEvent,
            'canCollectPayment' => $payments->canCollect($usageEvent),
            'paymentMethods' => $payments->availablePaymentMethods((int) $usageEvent->business_id),
            'defaultPhone' => $usageEvent->invoice?->payment_phone
                ?: $usageEvent->client?->payment_phone_number
                ?: $usageEvent->client?->phone_number,
        ]);
    }

    public function retryBilling(
        InventoryUsageEvent $usageEvent,
        InventoryMainModuleSyncService $sync
    ): RedirectResponse {
        InventoryBusinessContext::assertWritable();
        $this->authorizeUsageEvent($usageEvent);

        abort_unless(
            $usageEvent->billed_main_module && $usageEvent->main_billing_status === 'failed',
            422,
            'Only failed billable usage events can be retried.'
        );

        $usageEvent->forceFill([
            'main_billing_status' => 'pending',
            'main_billing_error' => null,
        ])->save();

        $sync->dispatchUsageBilling($usageEvent->fresh());

        return redirect()
            ->route('inventory.usage.show', $usageEvent)
            ->with('success', 'Billing retry queued.');
    }

    public function collectPayment(
        Request $request,
        InventoryUsageEvent $usageEvent,
        InventoryUsagePaymentService $payments
    ): JsonResponse|RedirectResponse {
        InventoryBusinessContext::assertWritable();
        $this->authorizeUsageEvent($usageEvent);

        $validated = $request->validate([
            'payment_method' => 'required|in:cash,mobile_money',
            'phone_number' => 'nullable|string|max:32',
        ]);

        try {
            $result = $payments->collect(
                $usageEvent,
                $validated['payment_method'],
                $validated['phone_number'] ?? null,
                $request->user()
            );
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => collect($e->errors())->flatten()->first(),
                    'errors' => $e->errors(),
                ], 422);
            }

            throw $e;
        }

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return redirect()
            ->back()
            ->with('success', $result['message'] ?? 'Payment collected.');
    }

    protected function authorizeUsageEvent(InventoryUsageEvent $usageEvent): void
    {
        abort_unless(
            (int) $usageEvent->business_id === InventoryBusinessContext::effectiveBusinessId(),
            404
        );
    }
}
