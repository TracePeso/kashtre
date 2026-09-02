<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (! Schema::hasColumn('businesses', 'entity_code')) {
                $table->string('entity_code', 16)->nullable()->after('account_number');
            }
        });

        Schema::create('inventory_rfq_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_order_id')->constrained('inventory_orders')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('rfq_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['inventory_order_id', 'supplier_id']);
        });

        Schema::table('goods_received_notes', function (Blueprint $table) {
            $table->string('inspection_status', 20)->nullable()->after('status');
            $table->text('inspection_notes')->nullable()->after('inspection_status');
            $table->foreignId('inspected_by_user_id')->nullable()->after('inspection_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at')->nullable()->after('inspected_by_user_id');
        });

        Schema::table('goods_received_note_lines', function (Blueprint $table) {
            $table->decimal('ordered_quantity', 16, 4)->nullable()->after('quantity');
            $table->decimal('variance_quantity', 16, 4)->nullable()->after('ordered_quantity');
            $table->string('condition_status', 20)->nullable()->after('variance_quantity');
        });

        Schema::table('inventory_stock_levels', function (Blueprint $table) {
            $table->decimal('quantity_in_transit_suom', 16, 4)->default(0)->after('quantity_suom');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stock_levels', function (Blueprint $table) {
            $table->dropColumn('quantity_in_transit_suom');
        });

        Schema::table('goods_received_note_lines', function (Blueprint $table) {
            $table->dropColumn(['ordered_quantity', 'variance_quantity', 'condition_status']);
        });

        Schema::table('goods_received_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inspected_by_user_id');
            $table->dropColumn(['inspection_status', 'inspection_notes', 'inspected_at']);
        });

        Schema::dropIfExists('inventory_rfq_suppliers');

        Schema::table('businesses', function (Blueprint $table) {
            if (Schema::hasColumn('businesses', 'entity_code')) {
                $table->dropColumn('entity_code');
            }
        });
    }
};
