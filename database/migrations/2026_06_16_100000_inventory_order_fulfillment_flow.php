<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('current_approval_order')->nullable()->after('status');
            $table->foreignId('submitted_by_user_id')->nullable()->after('created_by_user_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('submitted_at');
            $table->text('rejection_reason')->nullable()->after('approved_at');
        });

        DB::table('inventory_orders')
            ->where('status', 'submitted')
            ->update(['status' => 'approved', 'approved_at' => now()]);

        Schema::table('inventory_order_lines', function (Blueprint $table) {
            $table->decimal('received_quantity_suom', 14, 4)
                ->default(0)
                ->after('order_quantity_suom');
        });

        Schema::create('inventory_order_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_order_id')->constrained('inventory_orders')->cascadeOnDelete();
            $table->foreignId('approver_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('approval_order');
            $table->string('status')->default('pending');
            $table->text('comment')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->unique(['inventory_order_id', 'approval_order'], 'inv_order_approval_order_unique');
        });

        Schema::table('goods_received_notes', function (Blueprint $table) {
            $table->foreignId('inventory_order_id')->nullable()->after('store_id')
                ->constrained('inventory_orders')->nullOnDelete();
        });

        Schema::table('goods_received_note_lines', function (Blueprint $table) {
            $table->foreignId('inventory_order_line_id')->nullable()->after('item_id')
                ->constrained('inventory_order_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('goods_received_note_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_order_line_id');
        });

        Schema::table('goods_received_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_order_id');
        });

        Schema::dropIfExists('inventory_order_approvals');

        Schema::table('inventory_order_lines', function (Blueprint $table) {
            $table->dropColumn('received_quantity_suom');
        });

        Schema::table('inventory_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by_user_id');
            $table->dropColumn([
                'current_approval_order',
                'approved_at',
                'rejection_reason',
            ]);
        });
    }
};
