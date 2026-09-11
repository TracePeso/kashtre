<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        // Clinical's own local ledger of consumption facts it has emitted,
        // mirroring the existing ImagingConsumption pattern — a record
        // kept on this side regardless of whether the Inventory-side
        // depletion succeeded, so Clinical always has an audit trail even
        // for exception cases.
        Schema::connection('clinical')->create('clinical_consumption_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('client_id')->index();
            $table->string('visit_id')->nullable();
            $table->string('fact_token', 64); // MEDICATION_ADMINISTERED, MEDICATION_WASTED, NON_APPROVED_FLOOR_STOCK_USAGE, CRASH_CART_CONSUMPTION
            $table->string('usage_context', 64); // PATIENT, ADMINISTRATIVE, CRASH_CART, WASTAGE_OPERATIONAL, WASTAGE_EXPIRED
            $table->string('item_code', 64); // Item::code, plain logical key
            $table->decimal('quantity', 12, 4);
            $table->unsignedBigInteger('inventory_store_id')->nullable(); // plain logical key -> Inventory Store, resolved before dispatch
            $table->string('reconciliation_scenario', 64)->nullable();
            $table->boolean('physical_stock_reduced')->default(false);
            $table->boolean('approved_pool_reduced')->default(false);
            $table->boolean('billing_triggered')->default(false);
            $table->unsignedBigInteger('recorded_by_user_id');
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['business_id', 'client_id', 'occurred_at'], 'idx_consumption_patient_time');
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('clinical_consumption_events');
    }
};
