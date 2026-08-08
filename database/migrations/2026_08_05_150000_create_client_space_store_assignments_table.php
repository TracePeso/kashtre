<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_space_store_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_space_id')->constrained('client_spaces')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('fulfillment_strategy', 32)->default('DISCRETE_IMMEDIATE');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['client_space_id']);
            $table->index(['business_id', 'store_id']);
            $table->index(['business_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_space_store_assignments');
    }
};
