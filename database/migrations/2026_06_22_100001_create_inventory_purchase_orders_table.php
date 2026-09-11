<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('inventory_supplier_quotation_id');
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('po_number')->unique();
            $table->string('status', 32)->default('draft');
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('inventory_supplier_quotation_id', 'inv_po_quotation_fk')
                ->references('id')->on('inventory_supplier_quotations')->cascadeOnDelete();
        });

        Schema::create('inventory_purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_purchase_order_id');
            $table->unsignedBigInteger('inventory_order_line_id');
            $table->unsignedBigInteger('item_id');
            $table->decimal('quantity_suom', 14, 4)->default(0);
            $table->decimal('received_quantity_suom', 14, 4)->default(0);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->timestamps();

            $table->foreign('inventory_purchase_order_id', 'inv_pol_po_fk')
                ->references('id')->on('inventory_purchase_orders')->cascadeOnDelete();
            $table->foreign('inventory_order_line_id', 'inv_pol_order_line_fk')
                ->references('id')->on('inventory_order_lines')->cascadeOnDelete();
            $table->foreign('item_id', 'inv_pol_item_fk')
                ->references('id')->on('items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_purchase_order_lines');
        Schema::dropIfExists('inventory_purchase_orders');
    }
};
