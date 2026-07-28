<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imaging_protocols', function (Blueprint $table) {
            // Default preserves today's behavior (always deplete at Image
            // Acquired) for every existing protocol with zero data migration.
            $table->string('consumption_trigger_status')->default('IMAGE_ACQUIRED')->after('consumables_recipe');
        });
    }

    public function down(): void
    {
        Schema::table('imaging_protocols', function (Blueprint $table) {
            $table->dropColumn('consumption_trigger_status');
        });
    }
};
