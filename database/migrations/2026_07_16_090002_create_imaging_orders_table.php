<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Minimal Clinical-Module stand-in: lets a clinician place an imaging order
        // inside this repo until a real Clinical Module exists to send orders in via API.
        Schema::create('imaging_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('client_id')->index(); // Client::client_id (permanent, business-scoped)
            $table->string('visit_id')->nullable()->index(); // Client::visit_id (day-scoped)
            $table->unsignedBigInteger('ordering_user_id')->index(); // clinician placing the order
            $table->string('protocol_code')->index();
            $table->text('clinical_indication')->nullable();
            $table->string('status')->default('PENDING'); // PENDING, ACCEPTED, CANCELLED
            // Plain indexed column, not a FK constraint: imaging_studies is created
            // by a later migration and references imaging_orders itself, so a hard
            // constraint here would create a circular dependency at migrate time.
            $table->unsignedBigInteger('imaging_study_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imaging_orders');
    }
};
