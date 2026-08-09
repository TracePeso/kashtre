<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        Schema::connection('clinical')->create('cde_observations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('client_id')->index(); // Client::client_id (permanent, business-scoped)
            $table->string('visit_id')->nullable()->index(); // Client::visit_id (day-scoped)
            $table->string('cde_code', 64)->index();
            $table->decimal('captured_value_numeric', 12, 4)->nullable();
            $table->text('captured_value_text')->nullable();
            $table->json('captured_value_json')->nullable(); // multi-component, e.g. BP {systolic, diastolic}
            $table->unsignedBigInteger('input_uom_id')->nullable();
            $table->unsignedBigInteger('base_uom_id')->nullable();
            $table->decimal('base_value_numeric', 12, 4)->nullable(); // normalized to base unit
            $table->enum('capture_method', ['MANUAL', 'VOICE_DICTATION', 'DEVICE_IMPORT', 'CALCULATED', 'IMPORTED_DATA'])->default('MANUAL');
            $table->enum('validation_status', ['VALIDATED', 'UNVALIDATED'])->default('VALIDATED');
            $table->unsignedBigInteger('validated_by_user_id')->nullable(); // plain logical key -> core users table
            $table->timestamp('captured_at', 3)->useCurrent();

            $table->foreign('input_uom_id')->references('id')->on('clinical_uom_master');
            $table->foreign('base_uom_id')->references('id')->on('clinical_uom_master');
            $table->index(['business_id', 'client_id', 'cde_code', 'captured_at'], 'idx_cde_patient_time');
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('cde_observations');
    }
};
