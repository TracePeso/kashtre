<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_order_lines', function (Blueprint $table) {
            $table->text('quotation_analysis_comment')->nullable()->after('line_total');
        });

        Schema::table('inventory_supplier_quotation_lines', function (Blueprint $table) {
            $table->text('comments')->nullable()->after('line_total');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_order_lines', function (Blueprint $table) {
            $table->dropColumn('quotation_analysis_comment');
        });

        Schema::table('inventory_supplier_quotation_lines', function (Blueprint $table) {
            $table->dropColumn('comments');
        });
    }
};
