<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        // SRD §6.4 / Engineering DDL clinical_work_orders — general-purpose
        // work order entity (imaging orders now; MAR/ward-round/discharge
        // work items reuse this same table in later chunks). Chunk 3 only
        // populates order_type='RAD_{protocol_code}' rows.
        Schema::connection('clinical')->create('clinical_work_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('client_id')->index(); // Client::client_id (business-scoped)
            $table->string('visit_id')->nullable()->index();
            $table->string('order_type', 64); // e.g. 'RAD_CT_HEAD_PLAIN'
            $table->unsignedBigInteger('ordering_user_id');
            $table->unsignedBigInteger('assigned_to_user_id')->nullable();
            $table->string('assigned_role_code', 64)->nullable();
            $table->enum('status', ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'])->default('PENDING');
            // Which downstream module is fulfilling this order, and its own
            // plain logical key for that order there (e.g. imaging_orders.id)
            // — no FK, different connection.
            $table->string('external_module', 32)->nullable();
            $table->string('external_reference')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();

            $table->index(['business_id', 'assigned_to_user_id', 'status'], 'idx_work_order_assignee');
            $table->index(['external_module', 'external_reference'], 'idx_work_order_external_ref');
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('clinical_work_orders');
    }
};
