<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pillar 12.1: Material Consumable Tracking. Wired into the real Inventory
        // module in a later chunk — this table just records what a study used.
        Schema::create('imaging_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imaging_study_id')->constrained('imaging_studies')->cascadeOnDelete();
            $table->unsignedBigInteger('item_id')->nullable()->index(); // Main Module Item, not constrained
            $table->string('inventory_sku')->nullable();
            $table->decimal('quantity_used', 10, 4);
            $table->string('consumption_type')->default('PATIENT_PROCEDURE')->index(); // PATIENT_PROCEDURE, WASTAGE, CALIBRATION_RUN
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imaging_consumptions');
    }
};
