<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_approved_pool_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('source_fulfillment_line_id')
                ->nullable()
                ->constrained('inventory_fulfillment_lines')
                ->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->decimal('quantity_original', 12, 2);
            $table->decimal('quantity_remaining', 12, 2);
            $table->timestamps();

            $table->index(['business_id', 'client_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_approved_pool_lines');
    }
};
