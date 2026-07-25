<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->foreignId('supplier_industry_id')
                ->nullable()
                ->after('registered_as_supplier')
                ->constrained('supplier_industries')
                ->nullOnDelete();
            $table->foreignId('supplier_sub_category_id')
                ->nullable()
                ->after('supplier_industry_id')
                ->constrained('supplier_sub_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_sub_category_id');
            $table->dropConstrainedForeignId('supplier_industry_id');
        });
    }
};
