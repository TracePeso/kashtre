<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pillar 9.1: Contrast Chemical Asset Monitoring Lifecycle. Tracks a
        // real physical vial/kit through UNOPENED -> ONBOARD -> EXPIRED/
        // EXHAUSTED, independent of the free-text ContrastAdministration
        // flow it optionally feeds into.
        Schema::create('imaging_contrast_vials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            // Plain indexed reference to Item.id, not a FK — same
            // cross-domain-decoupling rule as everywhere else in this
            // module. Nullable because, like ContrastAdministration's own
            // contrast_agent_name today, a vial doesn't require a real
            // catalog Item to exist.
            $table->unsignedBigInteger('item_id')->nullable()->index();
            $table->string('agent_name');
            $table->string('lot_number')->nullable();
            $table->decimal('total_volume_ml', 8, 2);
            $table->decimal('remaining_volume_ml', 8, 2);
            $table->unsignedInteger('stability_hours')->nullable();
            $table->string('status')->default('UNOPENED')->index();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imaging_contrast_vials');
    }
};
