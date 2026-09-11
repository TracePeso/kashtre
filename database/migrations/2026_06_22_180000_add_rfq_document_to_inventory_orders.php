<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_orders', function (Blueprint $table) {
            $table->string('rfq_document_path')->nullable()->after('notes');
            $table->string('rfq_document_original_name')->nullable()->after('rfq_document_path');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_orders', function (Blueprint $table) {
            $table->dropColumn(['rfq_document_path', 'rfq_document_original_name']);
        });
    }
};
