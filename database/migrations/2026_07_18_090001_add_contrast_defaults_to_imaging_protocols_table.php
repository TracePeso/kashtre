<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imaging_protocols', function (Blueprint $table) {
            // Plain indexed reference to Item.id, not a FK — same cross-domain
            // decoupling rule as every other Main Module reference in this module.
            $table->unsignedBigInteger('default_contrast_item_id')->nullable()->index()->after('consumables_recipe');
            $table->decimal('default_contrast_volume_ml', 8, 2)->nullable()->after('default_contrast_item_id');
            $table->string('default_kvp_metrics')->nullable()->after('default_contrast_volume_ml');
        });
    }

    public function down(): void
    {
        Schema::table('imaging_protocols', function (Blueprint $table) {
            $table->dropColumn(['default_contrast_item_id', 'default_contrast_volume_ml', 'default_kvp_metrics']);
        });
    }
};
