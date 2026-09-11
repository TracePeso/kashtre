<?php

namespace App\Services\Inventory;

use App\Http\Controllers\InvoiceController;
use App\Models\Client;
use App\Models\InventoryUsageEvent;
use App\Models\Invoice;
use App\Models\MaturationPeriod;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MoneyTrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class InventoryUsagePaymentService
{
    /**
     * @return array<string, string>
     */
    public static function paymentMethodLabels(): array
    {
        return [
            'mobile_money' => 'Mobile money',
            'cash' => 'Cash',
        ];
    }

    public function canCollect(InventoryUsageEvent $event): bool
    {
        $invoice = $this->resolveInvoice($event);

        if (! $invoice) {
            return false;
        }

        return $this->isCollectableInvoice($invoice);
    }

    public function isCollectableInvoice(Invoice $invoice): bool
    {
        if (! $invoice->isInventoryUsagePostpaid()) {
            return false;
        }

        if ($invoice->status === 'cancelled') {
            return false;
        }

        return (float) $invoice->balance_due > 0.0001
            && ! in_array($invoice->payment_status, ['paid'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(
        InventoryUsageEvent $event,
        string $paymentMethod,
        ?string $phoneNumber,
        User $user
    ): array {
        $event->loadMissing(['client', 'invoice', 'item']);
        $invoice = $this->resolveInvoice($event);

        if (! $invoice || ! $this->isCollectableInvoice($invoice)) {
            throw ValidationException::withMessages([
                'payment_method' => 'This usage charge has already been paid or cannot be collected.',
            ]);
        }

        $client = $event->client ?? Client::query()->find($event->client_id);
        if (! $client) {
            throw ValidationException::withMessages([
                'client_id' => 'Client is required to collect payment.',
            ]);
        }

        if ($paymentMethod === 'mobile_money') {
            return $this->collectMobileMoney($invoice, $client, $phoneNumber);
        }

        if ($paymentMethod === 'cash') {
            return $this->collectCash($invoice, $client, $user);
        }

        throw ValidationException::withMessages([
            'payment_method' => 'Unsupported payment method.',
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function availablePaymentMethods(int $businessId): array
    {
        $allowed = MaturationPeriod::activePaymentMethodsForBusiness($businessId);
        $preferred = ['mobile_money', 'cash'];

        return array_values(array_filter(
            $preferred,
            fn (string $method): bool => in_array($method, $allowed, true)
        ));
    }

    protected function resolveInvoice(InventoryUsageEvent $event): ?Invoice
    {
        if (! $event->billed_main_module || ! $event->invoice_id) {
            return null;
        }

        return $event->invoice ?? Invoice::query()->find($event->invoice_id);
    }

    /**
     * @return array<string, mixed>
     */
    protected function collectCash(Invoice $invoice, Client $client, User $user): array
    {
        return DB::transaction(function () use ($invoice, $client, $user) {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $amount = (float) $locked->balance_due;

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Nothing left to pay on this invoice.',
                ]);
            }

            $locked->update([
                'amount_paid' => round((float) $locked->amount_paid + $amount, 2),
                'balance_due' => 0,
                'payment_status' => 'paid',
                'payment_methods' => [['method' => 'cash', 'amount' => $amount]],
            ]);

            $moneyTracking = app(MoneyTrackingService::class);
            $moneyTracking->processPaymentReceived(
                $client,
                $amount,
                $locked->invoice_number,
                'cash',
                ['invoice_id' => $locked->id]
            );
            $moneyTracking->processPaymentCompleted($locked->fresh(), $locked->items ?? []);

            if (! Transaction::query()
                ->where('invoice_id', $locked->id)
                ->where('method', 'cash')
                ->where('status', 'completed')
                ->exists()) {
                Transaction::create([
                    'business_id' => $locked->business_id,
                    'branch_id' => $locked->branch_id,
                    'client_id' => $client->id,
                    'invoice_id' => $locked->id,
                    'amount' => $amount,
                    'reference' => $locked->invoice_number,
                    'description' => 'Inventory postpaid usage — '.$locked->invoice_number,
                    'status' => 'completed',
                    'payment_status' => 'Paid',
                    'type' => 'debit',
                    'origin' => 'web',
                    'phone_number' => $locked->payment_phone ?? $client->phone_number,
                    'provider' => 'cash',
                    'service' => 'inventory_usage_payment',
                    'date' => now(),
                    'currency' => strtoupper($locked->currency ?? 'UGX'),
                    'names' => $client->name,
                    'method' => 'cash',
                    'transaction_for' => 'main',
                ]);
            }

            Log::info('Inventory usage postpaid invoice collected (cash)', [
                'invoice_id' => $locked->id,
                'invoice_number' => $locked->invoice_number,
                'amount' => $amount,
                'user_id' => $user->id,
            ]);

            return [
                'success' => true,
                'status' => 'paid',
                'message' => 'Cash payment recorded. Invoice '.$locked->invoice_number.' is now paid.',
                'invoice_number' => $locked->invoice_number,
                'amount' => $amount,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function collectMobileMoney(Invoice $invoice, Client $client, ?string $phoneNumber): array
    {
        $phone = trim((string) ($phoneNumber ?: $invoice->payment_phone ?: $client->payment_phone_number ?: $client->phone_number ?: ''));
        if ($phone === '') {
            throw ValidationException::withMessages([
                'phone_number' => 'Enter the mobile money number for this client.',
            ]);
        }

        $request = Request::create('/invoices/mobile-money-payment', 'POST', [
            'amount' => (float) $invoice->balance_due,
            'phone_number' => $phone,
            'client_id' => $client->id,
            'business_id' => $invoice->business_id,
            'items' => $invoice->items ?? [],
            'invoice_number' => $invoice->invoice_number,
        ]);
        $request->headers->set('Accept', 'application/json');

        $response = app(InvoiceController::class)->processMobileMoneyPayment($request);
        $payload = $response->getData(true);

        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'phone_number' => 'Could not start mobile money payment.',
            ]);
        }

        if (($payload['credit_client'] ?? false) || ($payload['skip_payment'] ?? false)) {
            return [
                'success' => true,
                'status' => 'credit_client',
                'message' => $payload['message'] ?? 'Credit client — no handset prompt sent. Debt stays on account.',
            ];
        }

        if (! ($payload['success'] ?? false)) {
            throw ValidationException::withMessages([
                'phone_number' => $payload['message'] ?? 'Mobile money payment could not be started.',
            ]);
        }

        $invoice->update([
            'payment_methods' => [['method' => 'mobile_money', 'amount' => (float) $invoice->balance_due]],
        ]);

        return [
            'success' => true,
            'status' => $payload['status'] ?? 'pending',
            'message' => $payload['message'] ?? 'Payment prompt sent to the client phone.',
            'transaction_id' => $payload['transaction_id'] ?? null,
            'internal_transaction_id' => $payload['internal_transaction_id'] ?? null,
            'yo_simulated' => $payload['yo_simulated'] ?? false,
            'invoice_number' => $invoice->invoice_number,
            'amount' => (float) $invoice->balance_due,
        ];
    }
}
