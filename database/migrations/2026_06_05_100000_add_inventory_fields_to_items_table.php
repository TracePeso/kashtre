<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('importance_category', 32)->nullable()->after('type');
            $table->foreignId('order_unit_id')->nullable()->after('uom_id')->constrained('item_units')->nullOnDelete();
            $table->decimal('suom_per_ouom', 14, 4)->nullable()->after('order_unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_unit_id');
            $table->dropColumn(['importance_category', 'suom_per_ouom']);
        });
    }
};
