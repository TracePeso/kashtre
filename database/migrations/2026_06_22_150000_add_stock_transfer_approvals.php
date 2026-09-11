<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->unsignedTinyInteger('current_approval_order')->nullable()->after('status');
        });

        Schema::create('stock_transfer_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approver_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('approval_order');
            $table->string('status')->default('pending');
            $table->text('comment')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->unique(['stock_transfer_id', 'approval_order'], 'stock_transfer_approval_order_unique');
        });

        $pendingTransfers = \Illuminate\Support\Facades\DB::table('stock_transfers')
            ->where('status', 'pending_approval')
            ->get(['id', 'business_id']);

        foreach ($pendingTransfers as $transfer) {
            $exists = \Illuminate\Support\Facades\DB::table('stock_transfer_approvals')
                ->where('stock_transfer_id', $transfer->id)
                ->exists();

            if ($exists) {
                continue;
            }

            $configId = \Illuminate\Support\Facades\DB::table('inventory_module_configs')
                ->where('business_id', $transfer->business_id)
                ->where('is_active', true)
                ->value('id');

            if (! $configId) {
                continue;
            }

            $approvers = \Illuminate\Support\Facades\DB::table('inventory_module_approvers')
                ->where('inventory_module_config_id', $configId)
                ->orderBy('approval_order')
                ->get(['user_id', 'approval_order']);

            $firstOrder = null;

            foreach ($approvers as $approver) {
                if ($firstOrder === null) {
                    $firstOrder = $approver->approval_order;
                }

                \Illuminate\Support\Facades\DB::table('stock_transfer_approvals')->insert([
                    'stock_transfer_id' => $transfer->id,
                    'approver_user_id' => $approver->user_id,
                    'approval_order' => $approver->approval_order,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($firstOrder !== null) {
                \Illuminate\Support\Facades\DB::table('stock_transfers')
                    ->where('id', $transfer->id)
                    ->update(['current_approval_order' => $firstOrder]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_approvals');

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropColumn('current_approval_order');
        });
    }
};
