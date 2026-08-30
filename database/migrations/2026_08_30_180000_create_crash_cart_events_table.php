<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crash_cart_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('parent_store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('event_type', 40);
            $table->string('seal_number')->nullable();
            $table->string('previous_seal_number')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('lines')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['store_id', 'occurred_at']);
            $table->index(['business_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crash_cart_events');
    }
};
