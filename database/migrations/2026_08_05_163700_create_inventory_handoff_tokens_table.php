<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_handoff_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('client_space_id')
                ->nullable()
                ->constrained('client_spaces')
                ->nullOnDelete();
            $table->string('basket_key')->nullable()->index();
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('fulfillment_line_ids');
            $table->timestamps();

            $table->index(['store_id', 'used_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_handoff_tokens');
    }
};
