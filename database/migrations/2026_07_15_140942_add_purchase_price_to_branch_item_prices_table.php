<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('branch_item_prices', function (Blueprint $table) {
            $table->decimal('purchase_price', 14, 2)->nullable()->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branch_item_prices', function (Blueprint $table) {
            $table->dropColumn('purchase_price');
        });
    }
};
