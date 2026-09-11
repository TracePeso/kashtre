<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventory_rfq_line_awards')) {
            return;
        }

        Schema::create('inventory_rfq_line_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_order_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('inventory_supplier_quotation_line_id')->nullable();
            $table->decimal('awarded_quantity_suom', 14, 4);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->timestamps();

            $table->foreign('inventory_supplier_quotation_line_id', 'inv_rfq_award_quote_line_fk')
                ->references('id')
                ->on('inventory_supplier_quotation_lines')
                ->nullOnDelete();

            $table->unique(
                ['inventory_order_line_id', 'supplier_id'],
                'inv_rfq_award_line_supplier_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_rfq_line_awards');
    }
};
