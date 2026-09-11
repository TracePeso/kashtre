<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_orders', function (Blueprint $table) {
            $table->string('order_type', 16)->default('external')->after('store_id');
            $table->foreignId('source_store_id')->nullable()->after('order_type')->constrained('stores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_store_id');
            $table->dropColumn('order_type');
        });
    }
};
