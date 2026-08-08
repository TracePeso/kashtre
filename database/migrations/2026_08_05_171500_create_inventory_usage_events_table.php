<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_usage_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('context', 32); // patient | administrative
            $table->string('classification', 48)->default('PATIENT');
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->string('resolution', 32); // approved_pool | physical_stock
            $table->boolean('billed_main_module')->default(false);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->text('notes')->nullable();
            $table->json('pool_allocations')->nullable(); // [{pool_line_id, qty}, ...]
            $table->timestamps();

            $table->index(['business_id', 'occurred_at']);
            $table->index(['business_id', 'client_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_usage_events');
    }
};
