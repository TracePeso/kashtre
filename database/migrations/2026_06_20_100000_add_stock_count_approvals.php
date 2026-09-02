<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_stock_counts', function (Blueprint $table) {
            $table->unsignedSmallInteger('current_approval_order')->nullable()->after('status');
            $table->foreignId('submitted_by_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('submitted_by_user_id');
            $table->timestamp('approved_at')->nullable()->after('finalized_at');
            $table->timestamp('stock_applied_at')->nullable()->after('approved_at');
            $table->text('rejection_reason')->nullable()->after('stock_applied_at');
        });

        Schema::create('inventory_stock_count_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_stock_count_id')->constrained('inventory_stock_counts')->cascadeOnDelete();
            $table->foreignId('approver_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('approval_order');
            $table->string('status', 32)->default('pending');
            $table->text('comment')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['inventory_stock_count_id', 'approval_order'],
                'inv_stock_count_approvals_count_order_unique'
            );
        });

        DB::table('inventory_stock_counts')
            ->where('status', 'finalized')
            ->update([
                'status' => 'approved',
                'approved_at' => DB::raw('finalized_at'),
                'stock_applied_at' => DB::raw('finalized_at'),
            ]);
    }

    public function down(): void
    {
        DB::table('inventory_stock_counts')
            ->where('status', 'approved')
            ->whereNotNull('finalized_at')
            ->update(['status' => 'finalized']);

        Schema::dropIfExists('inventory_stock_count_approvals');

        Schema::table('inventory_stock_counts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by_user_id');
            $table->dropColumn([
                'current_approval_order',
                'submitted_at',
                'approved_at',
                'stock_applied_at',
                'rejection_reason',
            ]);
        });
    }
};
