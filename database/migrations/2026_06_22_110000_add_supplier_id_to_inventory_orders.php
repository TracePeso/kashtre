<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inventory_orders', 'supplier_id')) {
            Schema::table('inventory_orders', function (Blueprint $table) {
                $table->foreignId('supplier_id')->nullable()->after('store_id')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('inventory_orders', 'supplier_id')) {
            Schema::table('inventory_orders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('supplier_id');
            });
        }
    }
};
