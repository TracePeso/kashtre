<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_received_notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('grn_number');
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->date('date_of_order');
            $table->date('date_of_delivery');
            $table->unsignedInteger('lead_time_days')->default(0);
            $table->string('delivery_note_path')->nullable();
            $table->string('delivery_note_original_name')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedTinyInteger('current_approval_order')->nullable();
            $table->foreignId('entry_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['business_id', 'grn_number']);
            $table->index(['business_id', 'status']);
        });

        Schema::create('goods_received_note_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_received_note_id')->constrained('goods_received_notes')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('category')->nullable();
            $table->string('item_name');
            $table->decimal('quantity', 14, 4);
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('duom')->nullable();
            $table->decimal('purchase_price', 14, 2)->default(0);
            $table->string('suom')->nullable();
            $table->decimal('sale_units_per_purchase_unit', 14, 4)->default(1);
            $table->decimal('sale_units_purchased', 14, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('goods_received_note_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_received_note_id')->constrained('goods_received_notes')->cascadeOnDelete();
            $table->foreignId('approver_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('approval_order');
            $table->string('status')->default('pending');
            $table->text('comment')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->unique(['goods_received_note_id', 'approval_order'], 'grn_approval_order_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_received_note_approvals');
        Schema::dropIfExists('goods_received_note_lines');
        Schema::dropIfExists('goods_received_notes');
    }
};
