<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['supplier_id', 'item_id']);
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->index();
            $table->string('status', 32)->default('draft');
            $table->foreignId('from_store_id')->constrained('stores');
            $table->foreignId('to_store_id')->constrained('stores');
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'reference']);
        });

        Schema::create('stock_transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->decimal('requested_quantity_suom', 16, 4)->default(0);
            $table->decimal('approved_quantity_suom', 16, 4)->default(0);
            $table->decimal('received_quantity_suom', 16, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('goods_return_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->index();
            $table->string('status', 32)->default('draft');
            $table->date('return_date');
            $table->string('reason_code', 8)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'reference']);
        });

        Schema::create('goods_return_note_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_return_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->decimal('quantity_suom', 16, 4);
            $table->string('batch_number')->nullable();
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('default_store_id')->nullable()->after('branch_id')->constrained('stores')->nullOnDelete();
        });

        Schema::table('inventory_stock_movements', function (Blueprint $table) {
            $table->foreignId('stock_transfer_id')->nullable()->after('goods_received_note_line_id')->constrained()->nullOnDelete();
            $table->foreignId('goods_return_note_id')->nullable()->after('stock_transfer_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('goods_return_note_id');
            $table->dropConstrainedForeignId('stock_transfer_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_store_id');
        });

        Schema::dropIfExists('goods_return_note_lines');
        Schema::dropIfExists('goods_return_notes');
        Schema::dropIfExists('stock_transfer_lines');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('supplier_item');
    }
};
