<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Multi-insurer cascade: optional child invoices (labeled A, B, C, …) linked to the primary POS invoice for traceability.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('parent_invoice_id')
                ->nullable()
                ->after('id')
                ->constrained('invoices')
                ->nullOnDelete();
            $table->string('vendor_portion_label', 4)->nullable()->after('parent_invoice_id');
            $table->unsignedTinyInteger('vendor_portion_priority')->nullable()->after('vendor_portion_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['parent_invoice_id']);
            $table->dropColumn(['parent_invoice_id', 'vendor_portion_label', 'vendor_portion_priority']);
        });
    }
};
