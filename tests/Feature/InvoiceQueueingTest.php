<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchServicePoint;
use App\Models\Business;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\ServiceCharge;
use App\Models\ServiceDeliveryQueue;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\CallingServiceClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class InvoiceQueueingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $callingServiceClient = Mockery::mock(CallingServiceClient::class);
        $callingServiceClient->shouldReceive('syncQueue')->andReturnNull();
        $callingServiceClient->shouldReceive('deleteQueueItem')->andReturnNull();
        $this->app->instance(CallingServiceClient::class, $callingServiceClient);

        $moneyTrackingService = Mockery::mock('overload:App\Services\MoneyTrackingService');
        $moneyTrackingService->shouldIgnoreMissing();
        $moneyTrackingService->shouldReceive('processOrderConfirmed')->andReturn([]);
        $moneyTrackingService->shouldReceive('processSuspenseAccountMovements')->andReturn([]);
        $moneyTrackingService->shouldReceive('processClientDeposit')->andReturnNull();
        $moneyTrackingService->shouldReceive('processPaymentCompleted')->andReturn([]);
        $moneyTrackingService->shouldReceive('processPaymentReceived')->andReturnNull();
    }

    public function test_fully_paid_mobile_money_invoice_is_queued_on_save(): void
    {
        $suffix = Str::lower(Str::random(8));

        $business = Business::unguarded(function () use ($suffix) {
            return Business::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'City Health Clinic',
                'email' => 'clinic+' . $suffix . '@example.com',
                'phone' => '0700000000',
                'address' => 'Kampala',
                'account_number' => 'ACC001',
                'currency_code' => 'UGX',
                'default_payment_terms_days' => 30,
                'date' => now()->toDateString(),
            ]);
        });

        $branch = Branch::unguarded(function () use ($business, $suffix) {
            return Branch::create([
                'uuid' => (string) Str::uuid(),
                'business_id' => $business->id,
                'name' => 'Main Branch',
                'email' => 'main-branch+' . $suffix . '@example.com',
                'phone' => '0700000001',
                'address' => 'Kampala',
            ]);
        });

        $user = User::unguarded(function () use ($business, $branch, $suffix) {
            return User::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Cashier',
                'email' => 'cashier+' . $suffix . '@example.com',
                'email_verified_at' => now(),
                'two_factor_confirmed_at' => now(),
                'password' => Hash::make('password'),
                'status' => 'active',
                'business_id' => $business->id,
                'branch_id' => $branch->id,
                'permissions' => ['Cashier'],
            ]);
        });

        $client = Client::unguarded(function () use ($business, $branch, $suffix) {
            return Client::create([
                'uuid' => (string) Str::uuid(),
                'business_id' => $business->id,
                'branch_id' => $branch->id,
                'type' => 'Out Patient',
                'client_id' => 'CHC' . strtoupper(Str::random(6)),
                'visit_id' => 'CH01M',
                'name' => 'Louis Draleti',
                'phone_number' => '0700000002',
                'payment_phone_number' => '0700000002',
                'village' => 'Kampala',
                'county' => 'Kampala',
                'email' => 'louis+' . $suffix . '@example.com',
                'balance' => 0,
                'status' => 'active',
                'client_type' => 'individual',
            ]);
        });

        $servicePoint = ServicePoint::unguarded(function () use ($business, $branch) {
            return ServicePoint::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Main Branch - Pharmacy',
                'business_id' => $business->id,
                'branch_id' => $branch->id,
            ]);
        });

        $item = Item::unguarded(function () use ($business, $suffix) {
            return Item::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Plasters',
                'code' => 'ITM' . strtoupper(Str::random(7)),
                'type' => 'good',
                'default_price' => 50,
                'hospital_share' => 100,
                'business_id' => $business->id,
            ]);
        });

        BranchServicePoint::create([
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'service_point_id' => $servicePoint->id,
            'item_id' => $item->id,
        ]);

        ServiceCharge::create([
            'entity_type' => 'business',
            'entity_id' => $business->id,
            'amount' => 1.50,
            'type' => 'fixed',
            'is_active' => true,
            'business_id' => $business->id,
            'created_by' => $user->id,
        ]);

        $payload = [
            'invoice_number' => 'INV-MM-' . strtoupper(Str::random(8)),
            'client_id' => $client->id,
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'created_by' => $user->id,
            'client_name' => $client->name,
            'client_phone' => $client->phone_number,
            'payment_phone' => $client->payment_phone_number,
            'visit_id' => $client->visit_id,
            'items' => [
                [
                    'id' => $item->id,
                    'item_id' => $item->id,
                    'name' => $item->name,
                    'quantity' => 1,
                    'price' => 50,
                    'total_amount' => 50,
                ],
            ],
            'subtotal' => 50,
            'package_adjustment' => 0,
            'account_balance_adjustment' => 0,
            'service_charge' => 1.5,
            'total_amount' => 51.5,
            'amount_paid' => 51.5,
            'balance_due' => 0,
            'payment_methods' => ['mobile_money'],
            'payment_status' => 'paid',
            'status' => 'confirmed',
            'notes' => 'Queue test',
        ];

        $response = $this->actingAs($user)->post(route('invoices.store'), $payload);

        $response->assertOk();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('invoices', [
            'invoice_number' => $payload['invoice_number'],
            'client_id' => $client->id,
        ]);

        $invoice = Invoice::where('invoice_number', $payload['invoice_number'])->firstOrFail();

        $this->assertDatabaseHas('service_delivery_queues', [
            'invoice_id' => $invoice->id,
            'client_id' => $client->id,
            'item_id' => $item->id,
            'service_point_id' => $servicePoint->id,
            'status' => 'pending',
        ]);

        $this->assertSame(1, ServiceDeliveryQueue::where('invoice_id', $invoice->id)->count());
    }
}
