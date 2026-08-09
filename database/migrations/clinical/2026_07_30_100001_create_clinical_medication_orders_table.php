<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        Schema::connection('clinical')->create('clinical_medication_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('client_id')->index();
            $table->string('visit_id')->nullable();
            $table->unsignedBigInteger('ordering_user_id');
            // Resolved Inventory Item.code when the Translator Engine finds
            // an internal SKU; null when is_external — a plain logical
            // key, not a FK (different connection).
            $table->string('drug_code', 64)->nullable();
            $table->string('drug_display_name');
            $table->decimal('dose_amount', 12, 4);
            $table->unsignedBigInteger('dose_uom_id')->nullable();
            $table->string('route_code', 32); // pharmacy_route_frequency_master (type=ROUTE)
            $table->string('frequency_code', 32); // pharmacy_route_frequency_master (type=FREQUENCY)
            $table->timestamp('start_at');
            $table->timestamp('end_at')->nullable();
            $table->enum('status', ['ACTIVE', 'DISCONTINUED', 'COMPLETED'])->default('ACTIVE');
            $table->boolean('is_external')->default(false); // SRD §6.2 external fulfillment fallback
            $table->string('external_referral_path')->nullable(); // stored PDF path
            $table->text('cdss_override_reason')->nullable(); // recorded when a HARD_BLOCK was overridden
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('dose_uom_id')->references('id')->on('clinical_uom_master');
            $table->index(['business_id', 'client_id', 'status'], 'idx_medication_order_patient');
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('clinical_medication_orders');
    }
};
