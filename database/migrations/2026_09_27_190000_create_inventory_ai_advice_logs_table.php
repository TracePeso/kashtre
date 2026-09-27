<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_ai_advice_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('use_case', 32);
            $table->string('capability', 64);
            $table->string('title')->nullable();
            $table->text('question')->nullable();
            $table->string('request_id', 64)->nullable();
            $table->boolean('ok')->default(false);
            $table->string('error_code', 64)->nullable();
            $table->text('error')->nullable();
            $table->text('summary')->nullable();
            $table->json('request_payload');
            $table->json('response_payload')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'created_at'], 'iail_business_created');
            $table->index(['business_id', 'store_id', 'created_at'], 'iail_business_store_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_ai_advice_logs');
    }
};
