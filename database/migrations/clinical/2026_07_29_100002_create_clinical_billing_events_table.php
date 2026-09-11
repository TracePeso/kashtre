<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        // Records the *intent* to bill excess/non-approved consumption —
        // there is no postpaid billing pipeline anywhere in this app yet
        // (confirmed: no Invoice/InvoiceLine creation exists in the
        // Imaging or Inventory consumption paths either), so this is the
        // same maturity level as InventoryConsumptionEvent: a durable
        // fact for Main Module/Finance to pick up later, not a fabricated
        // invoice. status stays PENDING until that pipeline exists.
        Schema::connection('clinical')->create('clinical_billing_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('client_id')->index();
            $table->string('visit_id')->nullable();
            $table->unsignedBigInteger('consumption_event_id'); // clinical_consumption_events.id, same connection
            $table->string('reason', 64); // reconciliation_scenario that triggered this
            $table->string('item_code', 64);
            $table->decimal('quantity', 12, 4);
            $table->decimal('amount', 12, 2)->nullable(); // Item::default_price * quantity, when resolvable
            $table->string('status', 32)->default('PENDING');
            $table->timestamps();

            $table->foreign('consumption_event_id')->references('id')->on('clinical_consumption_events');
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('clinical_billing_events');
    }
};
