<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_supplier_quotations')) {
            Schema::create('inventory_supplier_quotations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->foreignId('inventory_order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
                $table->string('reference_number')->nullable();
                $table->string('status', 32)->default('draft');
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['inventory_order_id', 'supplier_id'], 'inv_sq_order_supplier_unique');
            });
        }

        if (! Schema::hasTable('inventory_supplier_quotation_lines')) {
            Schema::create('inventory_supplier_quotation_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('inventory_supplier_quotation_id');
                $table->unsignedBigInteger('inventory_order_line_id');
                $table->unsignedBigInteger('item_id');
                $table->decimal('quoted_quantity_suom', 14, 4)->default(0);
                $table->decimal('unit_price', 14, 2)->default(0);
                $table->decimal('line_total', 14, 2)->default(0);
                $table->timestamps();

                $table->foreign('inventory_supplier_quotation_id', 'inv_sql_quotation_fk')
                    ->references('id')->on('inventory_supplier_quotations')->cascadeOnDelete();
                $table->foreign('inventory_order_line_id', 'inv_sql_order_line_fk')
                    ->references('id')->on('inventory_order_lines')->cascadeOnDelete();
                $table->foreign('item_id', 'inv_sql_item_fk')
                    ->references('id')->on('items')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_supplier_quotation_lines');
        Schema::dropIfExists('inventory_supplier_quotations');
    }
};
